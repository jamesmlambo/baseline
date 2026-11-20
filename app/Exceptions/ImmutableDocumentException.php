<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class ImmutableDocumentException extends LogicException
{
    /**
     * @param  list<string>  $fields
     */
    public static function forFields(Model $document, array $fields): self
    {
        return new self(sprintf(
            '%s %s has been issued and cannot be changed (%s). Issue a credit note instead.',
            class_basename($document),
            $document->getAttribute('number') ?? $document->getKey(),
            implode(', ', $fields),
        ));
    }

    public static function forDeletion(Model $document): self
    {
        return new self(sprintf('%s %s has been issued and cannot be deleted.', class_basename($document), $document->getAttribute('number') ?? $document->getKey()));
    }
}
