<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Website\CloudContentCopy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copies room content made on the laptop (room types, rooms, prices, descriptions, amenities and
 * room photos) to the Laravel Cloud site. Pushing to GitHub only moves code, never this content.
 * Dry run by default; --apply makes the changes. Bookings, guests, payments and users are never copied.
 */
class CopyContentToCloud extends Command
{
    protected $signature = 'app:copy-content-to-cloud
        {--apply : Make the changes (without it, only shows what would change)}';

    protected $description = 'Copy room types, rooms, amenities and room photos from this laptop to the Laravel Cloud site';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Run this on the laptop (local environment) only.');

            return self::FAILURE;
        }

        $settings = config('cloud_copy');
        foreach (['database_cluster', 'public_bucket', 'public_bucket_key'] as $key) {
            if (blank($settings[$key] ?? null)) {
                $this->error('Set CLOUD_COPY_DATABASE_CLUSTER, CLOUD_COPY_PUBLIC_BUCKET and CLOUD_COPY_PUBLIC_BUCKET_KEY in .env (see .env.example).');

                return self::FAILURE;
            }
        }

        try {
            $this->connectToCloud($settings);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Copying to Laravel Cloud…' : 'Dry run: nothing is changed. Add --apply to copy.');

        $result = (new CloudContentCopy(
            DB::connection(),
            DB::connection('cloud_copy'),
            Storage::disk('public'),
            Storage::disk('cloud_copy_public'),
        ))->run($apply);

        foreach ($result['report'] as $line) {
            $this->line($line);
        }

        $counts = $result['counts'];
        $summary = "{$counts['types']} room type(s), {$counts['rooms']} room(s), {$counts['amenities']} amenity(ies), {$counts['photos']} photo(s)";
        $result['report'] === []
            ? $this->info('Laravel Cloud already matches this laptop.')
            : $this->info(($apply ? 'Copied: ' : 'Would copy: ').$summary.'.');

        return self::SUCCESS;
    }

    /**
     * Registers the Cloud database and public bucket for this run, with credentials read from the
     * Cloud CLI. They are kept in memory only and never printed.
     *
     * @param  array<string, string|null>  $settings
     */
    private function connectToCloud(array $settings): void
    {
        $database = $this->cloud(['database-cluster:get', $settings['database_cluster'], '--json', '--show-sensitive', '-n'])['connection'] ?? null;
        $bucket = $this->cloud(['bucket:get', $settings['public_bucket'], '--json', '-n']);
        $key = $this->cloud(['bucket-key:get', $settings['public_bucket'], $settings['public_bucket_key'], '--json', '--show-sensitive', '-n']);

        if (! is_array($database) || blank($bucket['endpoint'] ?? null) || blank($key['secretAccessKey'] ?? null)) {
            throw new RuntimeException('Could not read the Cloud database or bucket details. Is the Cloud CLI signed in (php vendor/bin/cloud auth)?');
        }

        config([
            'database.connections.cloud_copy' => [
                'driver' => 'pgsql', 'host' => $database['hostname'], 'port' => $database['port'],
                'database' => $settings['database'], 'username' => $database['username'], 'password' => $database['password'],
                'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'require',
            ],
            'filesystems.disks.cloud_copy_public' => [
                'driver' => 's3', 'key' => $key['accessKeyId'], 'secret' => $key['secretAccessKey'], 'region' => 'auto',
                'bucket' => $settings['public_bucket'], 'endpoint' => $bucket['endpoint'],
                'use_path_style_endpoint' => false, 'throw' => true,
            ],
        ]);
    }

    /**
     * @param  list<string>  $arguments
     * @return array<string, mixed>
     */
    private function cloud(array $arguments): array
    {
        $result = Process::path(base_path())->timeout(120)->run([PHP_BINARY, 'vendor/bin/cloud', ...$arguments]);
        $data = json_decode($result->output(), true);

        if (! $result->successful() || ! is_array($data)) {
            throw new RuntimeException("The Laravel Cloud CLI could not run '{$arguments[0]}'. Is it signed in (php vendor/bin/cloud auth)?");
        }

        return $data;
    }
}
