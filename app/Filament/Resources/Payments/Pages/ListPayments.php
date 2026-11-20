<?php

declare(strict_types=1);

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Payments are recorded from a booking, never on their own.
 */
class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
