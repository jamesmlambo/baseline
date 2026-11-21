<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * PRIV-05 / acceptance 9: proves the newest backup can be restored. Unzips it (with the archive
 * password), loads the database dump into a separate scratch database, compares every table
 * with the live database, then drops the scratch database. It never touches any other database:
 * the scratch name must end in "_restore_test".
 */
class BackupRestoreTest extends Command
{
    protected $signature = 'backup:restore-test
        {--disk=backups : Disk holding the backups}
        {--database= : Scratch database name (default: <DB_DATABASE>_restore_test)}
        {--keep : Leave the scratch database in place for inspection}';

    protected $description = 'Restore the newest backup into a scratch database and compare it with the live one (PRIV-05)';

    /**
     * Tables that legitimately change between the backup and the test.
     *
     * @var list<string>
     */
    private const VOLATILE = ['activity_log', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'report_deliveries', 'channel_sync_runs'];

    public function handle(): int
    {
        $live = (string) config('database.connections.pgsql.database');
        $scratch = (string) ($this->option('database') ?: $live.'_restore_test');

        if (! str_ends_with($scratch, '_restore_test') || strcasecmp($scratch, $live) === 0 || str_starts_with(strtolower($scratch), 'baseline_lodge')) {
            $this->error("Refusing to use \"{$scratch}\": the scratch database name must end in _restore_test and must not be a real database.");

            return self::FAILURE;
        }

        $disk = Storage::disk((string) $this->option('disk'));
        $zips = collect($disk->allFiles())->filter(fn (string $file): bool => str_ends_with($file, '.zip'))->sortByDesc(fn (string $file): int => $disk->lastModified($file))->values();

        if ($zips->isEmpty()) {
            $this->error('No backup found. Run: php artisan backup:run');

            return self::FAILURE;
        }

        $zip = $zips->first();
        $this->info("Backup: {$zip} (".round($disk->size($zip) / 1024).' KB, made '.date('Y-m-d H:i', $disk->lastModified($zip)).')');

        $work = storage_path('app/backup-temp/restore-'.uniqid());
        File::ensureDirectoryExists($work);

        try {
            $dump = $this->extractDump($disk->path($zip), $work);
            $this->recreate($scratch);
            $this->load($dump, $scratch);
            $result = $this->compare($scratch);
        } catch (\Throwable $exception) {
            $this->error('Restore test FAILED: '.$exception->getMessage());

            return self::FAILURE;
        } finally {
            File::deleteDirectory($work);

            if (! $this->option('keep')) {
                $this->drop($scratch);
            }
        }

        $this->table(['Table', 'Live rows', 'Restored rows', 'Result'], $result['rows']);

        if ($result['missing'] !== [] || $result['different'] !== []) {
            $this->error('Restore test FAILED. Missing tables: '.implode(', ', $result['missing'] ?: ['none']).'. Different counts: '.implode(', ', $result['different'] ?: ['none']).'.');

            return self::FAILURE;
        }

        $this->info('Restore test PASSED: '.count($result['rows']).' tables restored'.($this->option('keep') ? ", kept as {$scratch}." : ' and the scratch database was removed.'));

        return self::SUCCESS;
    }

    private function extractDump(string $zipPath, string $work): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The backup zip could not be opened.');
        }

        if (filled($password = config('backup.backup.password'))) {
            $zip->setPassword((string) $password);
        }

        if (! $zip->extractTo($work)) {
            throw new RuntimeException('The backup could not be unpacked (wrong BACKUP_ARCHIVE_PASSWORD?).');
        }

        $zip->close();

        $dump = collect(File::allFiles($work))->first(fn ($file): bool => str_contains($file->getPathname(), 'db-dumps') && str_ends_with($file->getFilename(), '.sql'));

        if ($dump === null) {
            throw new RuntimeException('No database dump inside the backup.');
        }

        return $dump->getPathname();
    }

    private function recreate(string $scratch): void
    {
        $this->drop($scratch);
        DB::connection('pgsql')->statement('CREATE DATABASE "'.str_replace('"', '', $scratch).'"');
        $this->line("Created scratch database {$scratch}.");
    }

    private function drop(string $scratch): void
    {
        if (! str_ends_with($scratch, '_restore_test')) {
            return;
        }

        DB::purge('restore_test');
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS "'.str_replace('"', '', $scratch).'" WITH (FORCE)');
    }

    private function load(string $dump, string $scratch): void
    {
        $config = config('database.connections.pgsql');
        $psql = rtrim((string) ($config['dump']['dump_binary_path'] ?? ''), '/\\');
        $psql = $psql === '' ? 'psql' : $psql.DIRECTORY_SEPARATOR.'psql';

        $result = Process::timeout(1800)
            ->env(['PGPASSWORD' => (string) $config['password']])
            ->run([$psql, '-v', 'ON_ERROR_STOP=1', '-q', '-h', (string) $config['host'], '-p', (string) $config['port'], '-U', (string) $config['username'], '-d', $scratch, '-f', $dump]);

        if (! $result->successful()) {
            throw new RuntimeException('psql could not load the dump: '.mb_substr($result->errorOutput(), 0, 500));
        }

        $this->line('Dump loaded.');
    }

    /**
     * @return array{rows: list<list<string|int>>, missing: list<string>, different: list<string>}
     */
    private function compare(string $scratch): array
    {
        config(['database.connections.restore_test' => array_merge(config('database.connections.pgsql'), ['database' => $scratch])]);

        $tables = DB::connection('pgsql')->table('information_schema.tables')->where('table_schema', 'public')->where('table_type', 'BASE TABLE')->orderBy('table_name')->pluck('table_name');
        $restoredTables = DB::connection('restore_test')->table('information_schema.tables')->where('table_schema', 'public')->pluck('table_name')->all();

        $rows = [];
        $missing = [];
        $different = [];

        foreach ($tables as $table) {
            if (! in_array($table, $restoredTables, true)) {
                $missing[] = $table;
                $rows[] = [$table, DB::connection('pgsql')->table($table)->count(), '—', 'MISSING'];

                continue;
            }

            $liveCount = DB::connection('pgsql')->table($table)->count();
            $restoredCount = DB::connection('restore_test')->table($table)->count();
            $volatile = in_array($table, self::VOLATILE, true);
            $ok = $liveCount === $restoredCount || $volatile;

            if (! $ok) {
                $different[] = $table;
            }

            $rows[] = [$table, $liveCount, $restoredCount, $ok ? ($liveCount === $restoredCount ? 'same' : 'changed since backup (expected)') : 'DIFFERENT'];
        }

        DB::purge('restore_test');

        return ['rows' => $rows, 'missing' => $missing, 'different' => $different];
    }
}
