<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployeeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * An employee profile (EMP-01). Linked to a login when the person uses the system. Staff can
 * propose changes to their own details; a manager approves them. No biometric data (DEC-07).
 */
#[Fillable([
    'user_id', 'department_id', 'employee_number', 'full_name', 'preferred_name', 'job_title', 'phone',
    'emergency_contact_name', 'emergency_contact_phone', 'starts_on', 'ends_on', 'device_person_id', 'status', 'notes', 'is_demo',
])]
class Employee extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    /**
     * Fields staff may change themselves (subject to approval).
     */
    public const SELF_SERVICE_FIELDS = ['preferred_name', 'phone', 'emergency_contact_name', 'emergency_contact_phone'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending', 'is_demo' => false];

    protected function casts(): array
    {
        return [
            'status' => EmployeeStatus::class,
            'phone' => 'encrypted',
            'emergency_contact_name' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'pending_changes' => 'array',
            'changes_submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Encrypted contact fields are deliberately left out of the audit log.
        return LogOptions::defaults()
            ->useLogName('people')
            ->logOnly(['user_id', 'department_id', 'employee_number', 'full_name', 'job_title', 'starts_on', 'ends_on', 'device_person_id', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->useDisk('local')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        $this->addMediaCollection('pending_photo')->useDisk('local')->singleFile()->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function displayName(): string
    {
        return $this->preferred_name ?: $this->full_name;
    }

    /**
     * @param  Builder<Employee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', EmployeeStatus::Active);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<RosterPattern, $this>
     */
    public function rosterPatterns(): HasMany
    {
        return $this->hasMany(RosterPattern::class);
    }

    /**
     * @return HasMany<RosterChange, $this>
     */
    public function rosterChanges(): HasMany
    {
        return $this->hasMany(RosterChange::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<AttendanceDay, $this>
     */
    public function attendanceDays(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }
}
