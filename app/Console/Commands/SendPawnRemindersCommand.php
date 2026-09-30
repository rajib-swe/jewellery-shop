<?php

namespace App\Console\Commands;

use App\Jobs\SendPawnReminderJob;
use App\Models\Pawn;
use App\PawnReminderKind;
use App\PawnStatus;
use Illuminate\Console\Command;

class SendPawnRemindersCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pawns:send-reminders {--days=7 : Remind this many days before the due date} {--dry-run : List what would be sent without queueing anything}';

    /**
     * @var string
     */
    protected $description = 'Queue an SMS reminder for pawns due soon and for overdue pawns';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');

        $dueSoon = $this->pawnsDueBetween(today()->toDateString(), today()->addDays($days)->toDateString());
        $overdue = $this->overduePawns();

        $rows = [];

        foreach ($dueSoon as $pawn) {
            $rows[] = $this->queue($pawn, PawnReminderKind::DueSoon, $dryRun);
        }

        foreach ($overdue as $pawn) {
            $rows[] = $this->queue($pawn, PawnReminderKind::Overdue, $dryRun);
        }

        if ($rows === []) {
            $this->info("No pawns need a reminder in the next {$days} days.");

            return self::SUCCESS;
        }

        $this->table(['Pawn', 'Customer', 'Phone', 'Due date', 'Reminder'], $rows);
        $this->info(count($rows).' reminder(s) '.($dryRun ? 'would be sent' : 'queued').'.');

        return self::SUCCESS;
    }

    /**
     * Active pawns whose due date falls inside the given inclusive range and
     * whose customer can actually receive an SMS.
     *
     * @return list<Pawn>
     */
    private function pawnsDueBetween(string $from, string $to): array
    {
        return Pawn::query()
            ->with('customer')
            ->where('status', PawnStatus::Active->value)
            ->whereDate('due_date', '>=', $from)
            ->whereDate('due_date', '<=', $to)
            ->whereHas('customer', fn ($query) => $query->whereNotNull('phone')->where('phone', '!=', ''))
            ->orderBy('due_date')
            ->get()
            ->all();
    }

    /**
     * @return list<Pawn>
     */
    private function overduePawns(): array
    {
        return Pawn::query()
            ->with('customer')
            ->where('status', PawnStatus::Active->value)
            ->whereDate('due_date', '<', today()->toDateString())
            ->whereHas('customer', fn ($query) => $query->whereNotNull('phone')->where('phone', '!=', ''))
            ->orderBy('due_date')
            ->get()
            ->all();
    }

    /**
     * @return array{string, string, string, string, string}
     */
    private function queue(Pawn $pawn, PawnReminderKind $kind, bool $dryRun): array
    {
        if (! $dryRun) {
            SendPawnReminderJob::dispatch($pawn->id, $kind);
        }

        return [
            $pawn->pawn_no,
            $pawn->customer?->name ?? '—',
            $pawn->customer?->phone ?? '—',
            $pawn->due_date->toDateString(),
            $kind->value,
        ];
    }
}
