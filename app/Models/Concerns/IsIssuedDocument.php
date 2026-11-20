<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Exceptions\ImmutableDocumentException;
use App\Models\DocumentLine;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Issued financial documents can never be edited or deleted (PAY-03). Corrections are made
 * with credit notes. Only the listed workflow columns (e.g. voided_at) may change later.
 */
trait IsIssuedDocument
{
    protected static function bootIsIssuedDocument(): void
    {
        static::updating(function (self $document): void {
            $changed = array_keys($document->getDirty());
            $locked = array_diff($changed, $document->mutableAfterIssue());

            if ($locked !== []) {
                throw ImmutableDocumentException::forFields($document, $locked);
            }
        });

        static::deleting(function (self $document): void {
            throw ImmutableDocumentException::forDeletion($document);
        });
    }

    /**
     * @return list<string>
     */
    protected function mutableAfterIssue(): array
    {
        return ['updated_at'];
    }

    /**
     * @return MorphMany<DocumentLine, $this>
     */
    public function lines(): MorphMany
    {
        return $this->morphMany(DocumentLine::class, 'document')->orderBy('sort_order')->orderBy('id');
    }
}
