<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PunchDirection;
use App\Enums\PunchSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * A raw clock event (ATT-02). Kept exactly as received and never edited; attendance is derived
 * from punches separately. Only the employee link can be filled in later, when an unknown device
 * person ID is mapped to an employee.
 */
#[Fillable(['source', 'device_serial', 'device_person_id', 'employee_id', 'punched_at', 'direction', 'verify_mode', 'dedup_key', 'attendance_import_id', 'entered_by', 'reason', 'raw'])]
class AttendancePunch extends Model
{
    protected static function booted(): void
    {
        static::updating(function (AttendancePunch $punch): void {
            if (array_diff(array_keys($punch->getDirty()), ['employee_id', 'updated_at']) !== []) {
                throw new LogicException('Raw punches cannot be changed. Correct the attendance day instead.');
            }
        });
        static::deleting(fn (): never => throw new LogicException('Raw punches cannot be deleted.'));
    }

    protected function casts(): array
    {
        return [
            'source' => PunchSource::class,
            'direction' => PunchDirection::class,
            'punched_at' => 'immutable_datetime',
            'raw' => 'array',
        ];
    }

    /**
     * ATT-02: a composite key — never a device sequence number on its own.
     */
    public static function dedupKey(PunchSource $source, ?string $deviceSerial, ?string $devicePersonId, \DateTimeInterface $punchedAt, PunchDirection $direction): string
    {
        return hash('sha256', implode('|', [
            $source->value,
            mb_strtolower(trim((string) $deviceSerial)),
            trim((string) $devicePersonId),
            $punchedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
            $direction->value,
        ]));
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
