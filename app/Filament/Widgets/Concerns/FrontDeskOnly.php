<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Enums\StaffRole;

trait FrontDeskOnly
{
    public static function canView(): bool
    {
        return auth()->user()?->hasAnyRole([
            StaffRole::Administrator->value, StaffRole::Owner->value, StaffRole::Manager->value, StaffRole::Receptionist->value,
        ]) ?? false;
    }
}
