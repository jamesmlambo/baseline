<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Receipt;
use App\Services\Finance\DocumentPdf;
use Illuminate\Http\Response;

/**
 * Guest download links for invoices, receipts and quotations. Each document has its own unguessable token,
 * so a link shared by WhatsApp reveals only that one document (REC-07, WA-06).
 */
class DocumentController extends Controller
{
    public function __invoke(string $type, string $token, DocumentPdf $pdf): Response
    {
        $document = match ($type) {
            'invoice' => Invoice::query()->where('public_token', $token)->firstOrFail(),
            'receipt' => Receipt::query()->where('public_token', $token)->firstOrFail(),
            'quotation' => Quotation::query()->where('public_token', $token)->firstOrFail(),
        };

        return response($pdf->render($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($document).'"',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
