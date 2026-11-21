<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageTemplates\Pages;

use App\Filament\Resources\MessageTemplates\MessageTemplateResource;
use App\Models\MessageTemplate;
use Filament\Resources\Pages\EditRecord;

class EditMessageTemplate extends EditRecord
{
    protected static string $resource = MessageTemplateResource::class;

    /**
     * Placeholders may be moved but not removed or misspelt: every message relies on them.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var MessageTemplate $record */
        $record = $this->getRecord();
        $probe = new MessageTemplate(['body' => (string) $data['body']]);
        $unknown = array_diff($probe->variables(), $record->variables());

        if ($unknown !== []) {
            $this->addError('data.body', 'Unknown placeholder: {{ '.implode(' }}, {{ ', $unknown).' }}.');
            $this->halt();
        }

        return $data;
    }
}
