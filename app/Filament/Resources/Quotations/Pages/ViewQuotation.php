<?php

declare(strict_types=1);

namespace App\Filament\Resources\Quotations\Pages;

use App\Filament\Resources\Quotations\QuotationActions;
use App\Filament\Resources\Quotations\QuotationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewQuotation extends ViewRecord
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            QuotationActions::book(),
            QuotationActions::open(),
            QuotationActions::download(),
            QuotationActions::email(),
            QuotationActions::whatsApp(),
        ];
    }
}
