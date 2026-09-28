<?php

namespace App\Console\Commands;

use App\Models\Pawn;
use App\PawnStatus;
use App\Services\PawnInterestService;
use Illuminate\Console\Command;

class MarkPawnsOverdueCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pawns:mark-overdue';

    /**
     * @var string
     */
    protected $description = 'Flag active pawns whose due date has passed and report what is owed';

    public function handle(PawnInterestService $interest): int
    {
        $pawns = Pawn::query()
            ->with('customer')
            ->where('status', PawnStatus::Active->value)
            ->whereDate('due_date', '<', today()->toDateString())
            ->orderBy('due_date')
            ->get();

        if ($pawns->isEmpty()) {
            $this->info('No overdue pawns.');

            return self::SUCCESS;
        }

        $rows = $pawns->map(function (Pawn $pawn) use ($interest): array {
            $summary = $interest->calculate($pawn);
            $flaggedToday = $pawn->overdue_flagged_at?->isToday() ?? false;

            if (! $flaggedToday) {
                $pawn->forceFill(['overdue_flagged_at' => now()])->saveQuietly();
            }

            return [
                $pawn->pawn_no,
                $pawn->customer?->name ?? '—',
                $pawn->due_date->toDateString(),
                $summary['outstanding_principal'],
                $summary['interest_due'],
                $summary['total_payable'],
                $flaggedToday ? 'already' : 'new',
            ];
        })->all();

        $this->table(
            ['Pawn', 'Customer', 'Due date', 'Principal', 'Interest due', 'Total payable', 'Flag'],
            $rows,
        );

        $this->info("{$pawns->count()} overdue pawn(s).");

        return self::SUCCESS;
    }
}
