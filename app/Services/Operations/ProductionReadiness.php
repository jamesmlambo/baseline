<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Acceptance criterion 10 and the production checklist: no demo content, demo accounts or fake
 * drivers live in production, and every PENDING item is either decided or still visibly flagged.
 * "fail" blocks go-live; "warn" needs a conscious decision; "ok" is fine.
 */
class ProductionReadiness
{
    /**
     * @return list<array{area: string, status: 'ok'|'warn'|'fail', message: string}>
     */
    public function check(): array
    {
        $results = [];
        $add = function (string $area, string $status, string $message) use (&$results): void {
            $results[] = ['area' => $area, 'status' => $status, 'message' => $message];
        };

        // Application
        $add('Environment', app()->environment('production') ? 'ok' : 'warn', 'APP_ENV is "'.app()->environment().'".');
        $add('Debug mode', config('app.debug') ? 'fail' : 'ok', config('app.debug') ? 'APP_DEBUG is on — error pages would show internals.' : 'APP_DEBUG is off.');
        $add('Site address', Str::startsWith((string) config('app.url'), 'https://') ? 'ok' : 'fail', 'APP_URL is '.config('app.url').' (must be https).');
        $add('Secure session cookie', config('session.secure') ? 'ok' : 'fail', config('session.secure') ? 'Session cookies are HTTPS-only.' : 'Set SESSION_SECURE_COOKIE=true.');
        $add('Queue', config('queue.default') === 'sync' ? 'warn' : 'ok', 'Queue connection: '.config('queue.default').'. A worker must be running (php artisan queue:work).');

        // Demo content and accounts
        foreach ($this->tablesWithDemoFlag() as $table) {
            $count = DB::table($table)->where('is_demo', true)->count();
            $add("Demo data: {$table}", $count > 0 ? 'fail' : 'ok', $count > 0 ? "{$count} DEMO record(s) still present." : 'None.');
        }

        $demoUsers = User::query()->where('email', 'like', '%@baseline-lodge.test')->where('is_active', true)->count();
        $add('Demo accounts', $demoUsers > 0 ? 'fail' : 'ok', $demoUsers > 0 ? "{$demoUsers} active @baseline-lodge.test account(s). Deactivate them." : 'None active.');

        // Drivers
        $add('Payments', config('payments.gateway') === 'fake' ? 'fail' : 'ok', 'Payment gateway driver: '.config('payments.gateway').' (PEND-07).');
        $add('SMS', config('messaging.sms') === 'log' ? 'fail' : 'ok', 'SMS driver: '.config('messaging.sms').'. With "log" no guest receives codes or messages.');
        $add('WhatsApp', config('messaging.whatsapp') === 'log' ? 'warn' : 'ok', 'WhatsApp driver: '.config('messaging.whatsapp').'.');
        $add('Email', in_array(config('mail.default'), ['log', 'array'], true) ? 'fail' : 'ok', 'Mailer: '.config('mail.default').'.');
        $add('Web push', 'warn', 'Log driver only until a push provider is approved; daily reports still go by email.');
        $add('Channel manager', 'warn', 'Not connected (PEND-07/08). Channel bookings must be entered by hand or blocked via iCal.');
        $add('Virus scanning', 'warn', 'No scanner connected; uploads are marked "not scanned" (SEC-08).');

        // Pending decisions
        $pending = Setting::query()->where('is_pending_client_decision', true)->count();
        $add('Pending decisions', $pending > 0 ? 'warn' : 'ok', $pending > 0 ? "{$pending} setting(s) still flagged PENDING (visible in Settings)." : 'All decided.');

        // Backups
        $add('Backups', ...$this->backupStatus());

        return $results;
    }

    /**
     * @return list<string>
     */
    private function tablesWithDemoFlag(): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'is_demo')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();
    }

    /**
     * @return array{0: 'ok'|'warn'|'fail', 1: string}
     */
    private function backupStatus(): array
    {
        try {
            $disks = (array) config('backup.backup.destination.disks', []);

            if ($disks === []) {
                return ['fail', 'No backup destination configured.'];
            }

            $name = (string) config('backup.backup.name');
            $newest = null;

            foreach ($disks as $disk) {
                foreach (Storage::disk($disk)->files($name) as $file) {
                    $modified = Storage::disk($disk)->lastModified($file);
                    $newest = max($newest ?? 0, $modified);
                }
            }

            if ($newest === null) {
                return ['fail', 'No backup has been made yet.'];
            }

            $hours = (int) floor((time() - $newest) / 3600);

            if ($hours > 26) {
                return ['fail', "Newest backup is {$hours} hours old."];
            }

            $onServerOnly = array_diff($disks, ['backups', 'local']) === [];

            return $onServerOnly
                ? ['fail', "Newest backup is {$hours} hour(s) old, but only on this server. Add an off-site disk to BACKUP_DISKS (PRIV-05)."]
                : ['ok', "Newest backup is {$hours} hour(s) old on: ".implode(', ', $disks).'.'];
        } catch (Throwable $exception) {
            return ['fail', 'Could not read backups: '.$exception->getMessage()];
        }
    }
}
