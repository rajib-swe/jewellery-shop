<?php

namespace App\Jobs;

use App\Models\Pawn;
use App\PawnReminderKind;
use App\Services\PawnInterestService;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Tells a customer their pawn is close to its due date, or already past it.
 *
 * The message body is composed here rather than in the command so the wording
 * lives next to the figures it quotes, and so the same job can be dispatched
 * for a single pawn by hand while debugging.
 */
class SendPawnReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $pawnId,
        public readonly PawnReminderKind $kind,
    ) {}

    public function handle(SmsService $sms, PawnInterestService $interest): void
    {
        $pawn = Pawn::query()->with('customer')->find($this->pawnId);

        if ($pawn === null || $pawn->customer?->phone === null) {
            return;
        }

        $sms->send($pawn->customer->phone, $this->message($pawn, $interest));
    }

    private function message(Pawn $pawn, PawnInterestService $interest): string
    {
        $summary = $interest->calculate($pawn);
        $shop = config('app.name');

        if ($this->kind === PawnReminderKind::Overdue) {
            return sprintf(
                '%s: আপনার বন্ধক নং %s এর মেয়াদ %s তারিখে পেরিয়েছে। বর্তমানে মোট পরিশোধযোগ্য %s টাকা। দয়া করে দ্রুত শোকে যোগাযোগ করুন।',
                $shop,
                $pawn->pawn_no,
                $pawn->due_date->toDateString(),
                $summary['total_payable'],
            );
        }

        return sprintf(
            '%s: আপনার বন্ধক নং %s এর মেয়াদ %s তারিখে শেষ হবে। বর্তমানে মোট পরিশোধযোগ্য %s টাকা। সময়মতো আসুন।',
            $shop,
            $pawn->pawn_no,
            $pawn->due_date->toDateString(),
            $summary['total_payable'],
        );
    }
}
