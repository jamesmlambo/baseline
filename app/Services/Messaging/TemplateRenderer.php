<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Models\MessageTemplate;
use InvalidArgumentException;

/**
 * Fills {{ placeholders }} in a template. A missing value is an error, so a guest never
 * receives a message with a blank or a raw placeholder in it.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, string|int|null>  $variables
     */
    public function render(MessageTemplate $template, array $variables): string
    {
        $missing = array_filter($template->variables(), fn (string $name): bool => blank($variables[$name] ?? null));

        if ($missing !== []) {
            throw new InvalidArgumentException("Template [{$template->key}] is missing: ".implode(', ', $missing).'.');
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/',
            fn (array $match): string => (string) $variables[$match[1]],
            $template->body,
        );
    }
}
