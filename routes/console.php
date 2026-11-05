<?php

use App\Console\Commands\BackupDatabaseCommand;
use App\Console\Commands\MarkPawnsOverdueCommand;
use App\Console\Commands\SendPawnRemindersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(MarkPawnsOverdueCommand::class)
    ->dailyAt('06:00')
    ->withoutOverlapping();

// Overdue pawns are flagged first, so the reminder that follows already knows
// which accounts are past their date.
Schedule::command(SendPawnRemindersCommand::class)
    ->dailyAt('06:15')
    ->withoutOverlapping();

Schedule::command(BackupDatabaseCommand::class)
    ->dailyAt('02:00')
    ->withoutOverlapping();
