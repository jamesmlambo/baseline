<?php

declare(strict_types=1);

namespace App\Services\Finance;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Gap-free sequential document numbers per type (PAY-02), e.g. INV-000123.
 * The sequence row is locked, so concurrent issues can never get the same number.
 * Must be called inside the transaction that creates the document, so a rollback
 * also returns the number.
 */
class DocumentNumberer
{
    public function next(string $type): string
    {
        $sequence = DB::table('document_sequences')->where('type', $type)->lockForUpdate()->first();

        if ($sequence === null) {
            throw new InvalidArgumentException("Unknown document type [{$type}].");
        }

        DB::table('document_sequences')->where('type', $type)->update([
            'next_number' => $sequence->next_number + 1,
            'updated_at' => now(),
        ]);

        return sprintf('%s-%06d', $sequence->prefix, $sequence->next_number);
    }
}
