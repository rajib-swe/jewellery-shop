<?php

namespace App\Services;

use App\Models\Pawn;
use App\Models\PawnPayment;
use App\PawnPartialMonthRule;
use App\PawnPaymentType;
use App\PawnStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Computes simple monthly interest for a pawn account from its ledger.
 *
 * No running total is stored on the pawn row. Every figure is replayed from the
 * recorded ledger so a renewal, a partial principal payment, or a corrected
 * entry can never drift away from the money actually collected. Interest is
 * charged on the outstanding principal only, which means paying principal lowers
 * future interest while interest already accrued stays payable. Interest runs
 * continuously from the disbursement date, so settling it on renewal clears the
 * arrears and the rate keeps accruing from that point.
 */
class PawnInterestService
{
    /**
     * Simple monthly interest is charged against a 30 day month, the convention
     * the counter staff are used to. A part month is either prorated by day or
     * rounded up to a full month, depending on the shop setting.
     */
    public const DAYS_PER_MONTH = 30;

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * @return array{
     *     outstanding_principal: string,
     *     principal_paid: string,
     *     interest_rate: string,
     *     interest_accrued: string,
     *     interest_paid: string,
     *     interest_due: string,
     *     total_paid: string,
     *     total_payable: string,
     *     as_of: string,
     *     partial_month_rule: string,
     *     periods: list<array{from: string, to: string, days: int, months: string, principal: string, interest: string}>,
     * }
     */
    public function calculate(Pawn $pawn, ?CarbonInterface $asOfDate = null): array
    {
        $asOf = $asOfDate === null
            ? CarbonImmutable::today()
            : CarbonImmutable::parse($asOfDate)->startOfDay();

        $closedOn = $this->closedOn($pawn);

        if ($closedOn !== null && $closedOn->lessThan($asOf)) {
            $asOf = $closedOn;
        }

        $rule = $this->partialMonthRule();
        $rate = (float) $pawn->interest_rate;
        $outstandingCents = $this->toCents((float) $pawn->principal);
        $principalPaidCents = 0;
        $interestAccruedCents = 0;
        $interestPaidCents = 0;
        $periods = [];
        $cursor = CarbonImmutable::parse($pawn->date)->startOfDay();

        foreach ($this->ledger($pawn) as $payment) {
            $paymentDate = CarbonImmutable::parse($payment->date)->startOfDay();

            if ($paymentDate->greaterThan($asOf)) {
                break;
            }

            $accrued = $this->accrue($periods, $cursor, $paymentDate, $outstandingCents, $rate, $rule);
            $interestAccruedCents += $accrued;
            $cursor = $paymentDate;

            $amountCents = $this->toCents((float) $payment->amount);

            if ($payment->type === PawnPaymentType::Interest) {
                $interestPaidCents += $amountCents;

                continue;
            }

            if ($payment->type === PawnPaymentType::Principal) {
                $principalPaidCents += $amountCents;
                $outstandingCents -= $amountCents;

                continue;
            }

            // A redemption settles the interest due first and the rest of the
            // amount clears the principal, which closes the account.
            $interestShare = min($amountCents, max(0, $interestAccruedCents - $interestPaidCents));
            $interestPaidCents += $interestShare;
            $outstandingCents -= $amountCents - $interestShare;
            $principalPaidCents += $amountCents - $interestShare;

            break;
        }

        $interestAccruedCents += $this->accrue($periods, $cursor, $asOf, $outstandingCents, $rate, $rule);

        if ($interestPaidCents > $interestAccruedCents) {
            $overpaid = $interestPaidCents - $interestAccruedCents;
            $interestPaidCents = $interestAccruedCents;
            $outstandingCents -= $overpaid;
            $principalPaidCents += $overpaid;
        }

        $outstandingCents = max(0, $outstandingCents);
        $interestDueCents = max(0, $interestAccruedCents - $interestPaidCents);

        return [
            'outstanding_principal' => $this->fromCents($outstandingCents),
            'principal_paid' => $this->fromCents($principalPaidCents),
            'interest_rate' => number_format($rate, 2, '.', ''),
            'interest_accrued' => $this->fromCents($interestAccruedCents),
            'interest_paid' => $this->fromCents($interestPaidCents),
            'interest_due' => $this->fromCents($interestDueCents),
            'total_paid' => $this->fromCents($principalPaidCents + $interestPaidCents),
            'total_payable' => $this->fromCents($outstandingCents + $interestDueCents),
            'as_of' => $asOf->toDateString(),
            'partial_month_rule' => $rule->value,
            'periods' => $periods,
        ];
    }

    public function partialMonthRule(): PawnPartialMonthRule
    {
        return PawnPartialMonthRule::from($this->settings->all()['pawn_partial_month_rule']);
    }

    /**
     * Interest charged for one constant principal run, in cents.
     *
     * @param  list<array{from: string, to: string, days: int, months: string, principal: string, interest: string}>  $periods
     */
    private function accrue(
        array &$periods,
        CarbonImmutable $from,
        CarbonImmutable $to,
        int $outstandingCents,
        float $rate,
        PawnPartialMonthRule $rule,
    ): int {
        $days = abs((int) $from->diffInDays($to));

        if ($days === 0 || $outstandingCents <= 0 || $rate <= 0) {
            return 0;
        }

        $months = $rule === PawnPartialMonthRule::RoundUpFullMonth
            ? ceil($days / self::DAYS_PER_MONTH)
            : $days / self::DAYS_PER_MONTH;

        $interestCents = $this->toCents(($outstandingCents / 100) * ($rate / 100) * $months);

        $periods[] = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
            'months' => number_format($months, 4, '.', ''),
            'principal' => $this->fromCents($outstandingCents),
            'interest' => $this->fromCents($interestCents),
        ];

        return $interestCents;
    }

    /**
     * The redemption or forfeiture date, so a closed account stops accruing.
     */
    private function closedOn(Pawn $pawn): ?CarbonImmutable
    {
        return match ($pawn->status) {
            PawnStatus::Redeemed => $pawn->redeemed_at?->toImmutable()->startOfDay(),
            PawnStatus::Forfeited => $pawn->forfeited_at?->toImmutable()->startOfDay(),
            default => null,
        };
    }

    /**
     * @return Collection<int, PawnPayment>
     */
    private function ledger(Pawn $pawn)
    {
        if ($pawn->relationLoaded('payments')) {
            return $pawn->getRelation('payments');
        }

        return $pawn->payments()->get();
    }

    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function fromCents(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
