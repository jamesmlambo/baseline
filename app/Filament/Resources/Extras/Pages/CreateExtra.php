<?php

declare(strict_types=1);

namespace App\Filament\Resources\Extras\Pages;

use App\Filament\Resources\Extras\ExtraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateExtra extends CreateRecord
{
    protected static string $resource = ExtraResource::class;
}
