<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Actions\Reception\ResolveReceptionCode;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Support\QrCode;
use App\Support\SiteContent;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders financial documents as PDF (PAY-02). Banking details and VAT number appear only when configured.
 */
class DocumentPdf
{
    public function __construct(
        private readonly FinanceSettings $finance,
        private readonly SiteContent $site,
    ) {}

    public function render(Invoice|Quotation|Receipt|CreditNote $document): string
    {
        [$title, $view] = match (true) {
            $document instanceof Invoice => ['Invoice', 'pdf.document'],
            $document instanceof Quotation => ['Quotation', 'pdf.document'],
            $document instanceof CreditNote => ['Credit note', 'pdf.document'],
            $document instanceof Receipt => ['Receipt', 'pdf.receipt'],
        };

        return Pdf::loadView($view, [
            'title' => $title,
            'document' => $document,
            'lines' => method_exists($document, 'lines') ? $document->lines()->get() : collect(),
            'bank' => $document instanceof Invoice || $document instanceof Quotation ? $this->finance->bankDetails() : null,
            'vatNumber' => $this->finance->vatNumber(),
            'contact' => $this->site->contact(),
            'isDemo' => ! app()->isProduction(),
            'receptionQr' => $document->booking !== null
                ? QrCode::pngDataUri(ResolveReceptionCode::urlFor($document->booking))
                : null,
        ])->setPaper('a4')->output();
    }

    public function filename(Invoice|Quotation|Receipt|CreditNote $document): string
    {
        return $document->number.'.pdf';
    }
}
