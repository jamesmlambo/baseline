<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SEC-07 / PRIV-01 (final verification): the guest profile encrypted contact details, but bookings
 * and website enquiries kept their own plain-text copies, and the booking audit log copied them
 * too. Encrypt the copies (same format as Laravel's "encrypted" cast) and strip the plain values
 * from existing booking audit entries. down() decrypts again, so nothing is lost.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const COLUMNS = [
        'bookings' => ['lead_guest_email', 'lead_guest_phone'],
        'enquiries' => ['email', 'phone'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                foreach ($columns as $column) {
                    $blueprint->text($column)->nullable()->change();
                }
            });

            DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $values = [];

                    foreach ($columns as $column) {
                        if ($row->{$column} !== null && $row->{$column} !== '' && ! $this->isEncrypted($row->{$column})) {
                            $values[$column] = Crypt::encryptString($row->{$column});
                        }
                    }

                    if ($values !== []) {
                        DB::table($table)->where('id', $row->id)->update($values);
                    }
                }
            });
        }

        // Remove the plain contact details from booking audit entries (names and statuses stay).
        DB::table('activity_log')->where('subject_type', 'App\\Models\\Booking')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $properties = json_decode((string) $row->properties, true);

                if (! is_array($properties)) {
                    continue;
                }

                $changed = false;

                foreach (['attributes', 'old'] as $section) {
                    foreach (['lead_guest_email', 'lead_guest_phone'] as $field) {
                        if (isset($properties[$section]) && is_array($properties[$section]) && array_key_exists($field, $properties[$section])) {
                            $properties[$section][$field] = '[removed]';
                            $changed = true;
                        }
                    }
                }

                if ($changed) {
                    DB::table('activity_log')->where('id', $row->id)->update(['properties' => json_encode($properties)]);
                }
            }
        });
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            DB::table($table)->select(['id', ...$columns])->orderBy('id')->chunkById(500, function ($rows) use ($table, $columns): void {
                foreach ($rows as $row) {
                    $values = [];

                    foreach ($columns as $column) {
                        if ($row->{$column} !== null && $this->isEncrypted($row->{$column})) {
                            $values[$column] = Crypt::decryptString($row->{$column});
                        }
                    }

                    if ($values !== []) {
                        DB::table($table)->where('id', $row->id)->update($values);
                    }
                }
            });
        }
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
};
