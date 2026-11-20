<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settings\Schemas;

use App\Models\Setting;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        $isType = fn (string ...$types): \Closure => fn (?Setting $record): bool => in_array($record?->type, $types, true);

        return $schema
            ->components([
                Section::make(fn (?Setting $record): string => $record?->label ?? 'Setting')
                    ->description(fn (?Setting $record): ?string => $record?->help_text)
                    ->schema([
                        Text::make(fn (?Setting $record): string => 'PENDING CLIENT DECISION ('.$record?->decision_reference.'). This value is not approved policy.')
                            ->color('warning')
                            ->visible(fn (?Setting $record): bool => (bool) $record?->is_pending_client_decision),
                        TextInput::make('value')
                            ->label('Value')
                            ->visible($isType('string')),
                        TextInput::make('value')
                            ->label('Value')
                            ->integer()
                            ->minValue(0)
                            ->visible($isType('integer')),
                        TextInput::make('value')
                            ->label('Value (HH:MM, 24-hour)')
                            ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                            ->visible($isType('time')),
                        Textarea::make('value')
                            ->label('Value')
                            ->rows(3)
                            ->visible($isType('text')),
                        TagsInput::make('value')
                            ->label('Values (in order)')
                            ->visible($isType('list')),
                        Toggle::make('is_pending_client_decision')
                            ->label('Still awaiting a client decision')
                            ->helperText('Switch off only once the client has approved this value.'),
                    ]),
            ]);
    }
}
