<?php

declare(strict_types=1);

namespace App\Filament\Resources\Refunds\Pages;

use App\Filament\Resources\Refunds\RefundResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Refunds are requested from a payment, never created here.
 */
class ListRefunds extends ListRecords
{
    protected static string $resource = RefundResource::class;
}
