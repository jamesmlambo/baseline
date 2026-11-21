<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\StaffRole;
use App\Services\Reports\ReportMetrics;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * The owner's dashboard (REP-01/02/06): live figures for a chosen period, each labelled with
 * its date basis. Owner and managers only.
 */
class OwnerDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Owner dashboard';

    protected static ?string $title = 'Owner dashboard';

    protected static ?string $slug = 'owner-dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.owner-dashboard';

    public const PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last7' => 'Last 7 days',
        'month' => 'This month',
        'last_month' => 'Last month',
        'custom' => 'Custom',
    ];

    #[Url]
    public string $preset = 'today';

    #[Url]
    public ?string $from = null;

    #[Url]
    public ?string $until = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([StaffRole::Owner->value, StaffRole::Manager->value]) ?? false;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function range(): array
    {
        $today = CarbonImmutable::today('Africa/Johannesburg');

        return match ($this->preset) {
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last7' => [$today->subDays(6), $today],
            'month' => [$today->startOfMonth(), $today],
            'last_month' => [$today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()],
            'custom' => $this->customRange($today),
            default => [$today, $today],
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        [$from, $until] = $this->range();

        return ['data' => app(ReportMetrics::class)->forPeriod($from, $until)];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customRange(CarbonImmutable $today): array
    {
        try {
            $from = CarbonImmutable::parse((string) $this->from, 'Africa/Johannesburg');
            $until = CarbonImmutable::parse((string) $this->until, 'Africa/Johannesburg');
        } catch (\Throwable) {
            return [$today, $today];
        }

        if ($until->lessThan($from)) {
            [$from, $until] = [$until, $from];
        }

        // Keep queries reasonable: at most a year at a time.
        return [$from->max($until->subYear()->addDay()), $until];
    }
}
