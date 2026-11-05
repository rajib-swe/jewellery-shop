<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Nightly database backups.
 *
 * The dump is written in PHP rather than by shelling out to `mysqldump`, because
 * the client binary is not installed on every host the shop runs on and the
 * backup that silently fails is worse than no backup at all. Rows are streamed
 * in chunks so a large `stock_movements` table never has to fit in memory, and
 * the file is gzipped as it goes.
 */
class BackupService
{
    private const CHUNK_SIZE = 500;

    /**
     * Writes a gzipped SQL dump and prunes anything past the retention window.
     *
     * @return array{name: string, path: string, size: int, tables: int, rows: int, created_at: string}
     */
    public function run(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $tables = match ($driver) {
            'mysql', 'mariadb' => $this->mysqlTables(),
            'sqlite' => $this->sqliteTables(),
            default => throw new RuntimeException("Backups are not supported for the [{$driver}] driver."),
        };

        $stamp = now()->format('Y-m-d_His');
        $filename = "backup-{$connection->getDatabaseName()}_{$stamp}.sql.gz";
        $directory = $this->directory();
        File::ensureDirectoryExists($directory);

        $path = "{$directory}/{$filename}";
        $rows = 0;
        $handle = gzopen($path, 'wb9');

        if ($handle === false) {
            throw new RuntimeException("Could not open [{$path}] for writing.");
        }

        try {
            $this->writeLine($handle, '-- Jewellery Shop backup taken '.now()->toIso8601String());
            $this->writeLine($handle, "-- Database: {$connection->getDatabaseName()}");
            $this->writeLine($handle, '');
            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=0;');
            $this->writeLine($handle, '');

            foreach ($tables as $table) {
                $this->writeSchema($handle, $driver, $table);
                $rows += $this->writeRows($handle, $driver, $table, $this->primaryKeyColumns($table));
            }

            // Tables come out in alphabetical order, so without this the first
            // sale row would be inserted before the customer it belongs to.
            $this->writeLine($handle, 'SET FOREIGN_KEY_CHECKS=1;');
        } finally {
            gzclose($handle);
        }

        $pruned = $this->prune();
        $size = (int) filesize($path);

        activity()
            ->event('backup')
            ->inLog('backups')
            ->withProperties([
                'filename' => $filename,
                'size' => $size,
                'tables' => count($tables),
                'pruned' => $pruned,
            ])
            ->log('Database backup taken');

        return [
            'name' => $filename,
            'path' => $path,
            'size' => $size,
            'tables' => count($tables),
            'rows' => $rows,
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Every existing backup, newest first, with its size in bytes.
     *
     * @return list<array{name: string, path: string, size: int, created_at: string}>
     */
    public function all(): array
    {
        $directory = $this->directory();

        if (! File::isDirectory($directory)) {
            return [];
        }

        $backups = [];

        foreach (File::files($directory) as $file) {
            if (! str_ends_with($file->getFilename(), '.sql.gz')) {
                continue;
            }

            $backups[] = [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
                'created_at' => date('c', $file->getMTime()),
            ];
        }

        usort($backups, fn (array $a, array $b) => strcmp($b['name'], $a['name']));

        return $backups;
    }

    public function find(string $name): ?string
    {
        // The name comes from a request, so it is resolved inside the backup
        // directory and rejected if it tries to climb out of it.
        if ($name === '' || basename($name) !== $name || ! str_ends_with($name, '.sql.gz')) {
            return null;
        }

        $path = $this->directory().DIRECTORY_SEPARATOR.$name;

        return File::isFile($path) ? $path : null;
    }

    /**
     * Deletes backups older than the retention window.
     *
     * @return list<string>
     */
    public function prune(): array
    {
        $keepDays = (int) config('backup.keep_days');
        $deleted = [];

        foreach ($this->all() as $backup) {
            if (CarbonImmutable::parse($backup['created_at'])->gt(now()->subDays($keepDays))) {
                continue;
            }

            File::delete($backup['path']);
            $deleted[] = $backup['name'];
        }

        return $deleted;
    }

    private function directory(): string
    {
        return rtrim((string) config('backup.path'), '/\\');
    }

    /**
     * @return list<string>
     */
    private function mysqlTables(): array
    {
        return array_map(
            fn (object $row) => (string) $row->name,
            DB::select(
                'SELECT table_name AS name FROM information_schema.tables WHERE table_schema = ? AND table_type = "BASE TABLE" ORDER BY table_name',
                [DB::connection()->getDatabaseName()],
            ),
        );
    }

    /**
     * The primary key columns of a table, in key order.
     *
     * Not every table has a single `id`: Laravel's `cache` table is keyed by
     * `key`, and the keyset walk needs to know what to walk by.
     *
     * @return list<string>
     */
    private function primaryKeyColumns(string $table): array
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return ['rowid'];
        }

        return array_map(
            fn (object $row) => (string) $row->name,
            DB::select(
                'SELECT column_name AS name FROM information_schema.key_column_usage
                 WHERE table_schema = ? AND table_name = ? AND constraint_name = "PRIMARY"
                 ORDER BY ordinal_position',
                [DB::connection()->getDatabaseName(), $table],
            ),
        );
    }

    /**
     * @return list<string>
     */
    private function sqliteTables(): array
    {
        return DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
            ->map(fn (object $row) => $row->name)
            ->all();
    }

    private function writeSchema($handle, string $driver, string $table): void
    {
        if ($driver === 'sqlite') {
            $create = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);

            if ($create?->sql !== null) {
                $this->writeLine($handle, "DROP TABLE IF EXISTS `{$table}`;");
                $this->writeLine($handle, $create->sql.';');
            }

            return;
        }

        $row = DB::selectOne("SHOW CREATE TABLE `{$table}`");
        $create = $row->{'Create Table'} ?? null;

        if (is_string($create)) {
            // Dropping first means the dump restores cleanly over an existing
            // schema, which is the case a shop actually needs when it is
            // recovering from a bad deploy.
            $this->writeLine($handle, "DROP TABLE IF EXISTS `{$table}`;");
            $this->writeLine($handle, $create.';');
        }

        $this->writeLine($handle, '');
    }

    /**
     * Streams one table's rows out as batched INSERT statements.
     *
     * Tables with a single column primary key are read with a keyset walk rather
     * than LIMIT/OFFSET: an offset into a large ledger re-reads every skipped
     * row, and this runs nightly against `stock_movements`. Anything else falls
     * back to offset paging.
     */
    private function writeRows($handle, string $driver, string $table, array $keyColumns): int
    {
        $keyset = count($keyColumns) === 1 ? $keyColumns[0] : null;
        $columns = null;
        $lastKey = null;
        $written = 0;

        do {
            $query = DB::table($table)->limit(self::CHUNK_SIZE);

            if ($keyset !== null) {
                $query->orderBy($keyset);

                if ($lastKey !== null) {
                    $query->where($keyset, '>', $lastKey);
                }
            } else {
                $query->offset($written);
            }

            $rows = $query->get();

            if ($rows->isEmpty()) {
                break;
            }

            $values = [];

            foreach ($rows as $row) {
                $row = (array) $row;

                if ($columns === null) {
                    $columns = array_keys($row);
                    $quoted = $driver === 'sqlite'
                        ? '"'.implode('", "', $columns).'"'
                        : '`'.implode('`, `', $columns).'`';

                    $this->writeLine($handle, "INSERT INTO `{$table}` ({$quoted}) VALUES");
                }

                $lastKey = $keyset === null ? $lastKey : $row[$keyset];
                $values[] = '('.implode(', ', $this->quoteAll(array_values($row))).')';
            }

            $this->writeLine($handle, implode(",\n", $values).';');
            $written += $rows->count();
        } while ($rows->count() === self::CHUNK_SIZE);

        $this->writeLine($handle, '');

        return $written;
    }

    /**
     * @param  list<mixed>  $values
     * @return list<string>
     */
    private function quoteAll(array $values): array
    {
        $connection = DB::connection();

        return array_map(
            fn (mixed $value) => $value === null
                ? 'NULL'
                : $connection->getPdo()->quote((string) $value),
            $values,
        );
    }

    private function writeLine($handle, string $line): void
    {
        gzwrite($handle, $line."\n");
    }
}
