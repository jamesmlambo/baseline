<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Entered by the lodge (not seeded): staff whose roster says they don't work public holidays are off duty.
 */
#[Fillable(['date', 'name'])]
class PublicHoliday extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
