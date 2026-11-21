<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\ServiceCategory;
use App\Enums\ServiceResourceType;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A bookable service (LSR-01/02): day pass, braai, VIP area, spa treatment, meal…
 *
 * - opening_hours: {"1": ["08:00", "18:00"], …} keyed by ISO weekday; missing = closed.
 * - capacity_unit "people": at most `capacity` guests at once; "bookings": at most `capacity`
 *   bookings at once (e.g. a VIP area); null capacity = limited only by resources.
 * - resource_types: one free resource of each listed type is needed per booking (spa: therapist + room).
 */
#[Fillable([
    'name', 'slug', 'category', 'description', 'duration_minutes', 'slot_interval_minutes', 'price_cents', 'pricing',
    'capacity_unit', 'capacity', 'max_party_size', 'min_notice_minutes', 'cancellation_cutoff_hours', 'opening_hours',
    'resource_types', 'restrictions', 'charge_to_room_allowed', 'bookable_by_guests', 'requires_indemnity', 'is_active', 'is_demo', 'sort_order',
])]
class Service extends Model
{
    use LogsActivity;

    public const PER_PERSON = 'per_person';

    public const PER_BOOKING = 'per_booking';

    public const CAPACITY_PEOPLE = 'people';

    public const CAPACITY_BOOKINGS = 'bookings';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'pricing' => self::PER_PERSON,
        'capacity_unit' => self::CAPACITY_PEOPLE,
        'slot_interval_minutes' => 60,
        'max_party_size' => 10,
        'min_notice_minutes' => 0,
        'cancellation_cutoff_hours' => 24,
        'charge_to_room_allowed' => true,
        'bookable_by_guests' => true,
        'requires_indemnity' => false,
        'is_active' => true,
        'is_demo' => false,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'price_cents' => MoneyCast::class,
            'duration_minutes' => 'integer',
            'slot_interval_minutes' => 'integer',
            'capacity' => 'integer',
            'max_party_size' => 'integer',
            'min_notice_minutes' => 'integer',
            'cancellation_cutoff_hours' => 'integer',
            'opening_hours' => 'array',
            'resource_types' => 'array',
            'charge_to_room_allowed' => 'boolean',
            'bookable_by_guests' => 'boolean',
            'requires_indemnity' => 'boolean',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('leisure')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return list<ServiceResourceType>
     */
    public function requiredResourceTypes(): array
    {
        return array_values(array_map(fn (string $type): ServiceResourceType => ServiceResourceType::from($type), $this->resource_types ?? []));
    }

    /**
     * Opening and closing time on a date, or null when closed.
     *
     * @return array{open: CarbonImmutable, close: CarbonImmutable}|null
     */
    public function hoursOn(CarbonImmutable $date): ?array
    {
        $day = CarbonImmutable::parse($date->toDateString(), 'Africa/Johannesburg');
        $hours = ($this->opening_hours ?? [])[(string) $day->isoWeekday()] ?? null;

        if (! is_array($hours) || count($hours) !== 2 || blank($hours[0]) || blank($hours[1])) {
            return null;
        }

        $open = CarbonImmutable::parse($day->toDateString().' '.$hours[0], 'Africa/Johannesburg');
        $close = CarbonImmutable::parse($day->toDateString().' '.$hours[1], 'Africa/Johannesburg');

        return $close->greaterThan($open) ? ['open' => $open, 'close' => $close] : null;
    }

    public function priceFor(int $partySize): Money
    {
        return $this->pricing === self::PER_BOOKING ? $this->price_cents : $this->price_cents->multiply($partySize);
    }

    public function priceLabel(): string
    {
        return $this->price_cents->format().($this->pricing === self::PER_BOOKING ? ' per booking' : ' per person');
    }

    /**
     * @param  Builder<Service>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    /**
     * @return HasMany<ServiceBooking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }
}
