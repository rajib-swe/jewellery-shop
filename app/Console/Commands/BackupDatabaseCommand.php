<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'backup:database {--prune-only : Delete expired backups without taking a new one}';

    /**
     * @var string
     */
    protected $description = 'Write a gzipped SQL dump of the shop database and prune backups past the retention window';

    public function handle(BackupService $backups): int
    {
        if ($this->option('prune-only')) {
            $deleted = $backups->prune();
            $this->info(count($deleted).' expired backup(s) deleted.');

            return self::SUCCESS;
        }

        $backup = $backups->run();

        $this->info("{$backup['name']} written (".number_format($backup['rows'])." rows across {$backup['tables']} tables, "
            .number_format($backup['size'] / 1024, 1).' kB).');

        return self::SUCCESS;
    }
}
