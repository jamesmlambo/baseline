<?php

declare(strict_types=1);

namespace App\Filament\Resources\MessageTemplates;

use App\Enums\MessageChannel;
use App\Enums\MessageClassification;
use App\Filament\Forms\MoneyInput;
use App\Filament\Resources\MessageTemplates\Pages\EditMessageTemplate;
use App\Filament\Resources\MessageTemplates\Pages\ListMessageTemplates;
use App\Models\MessageTemplate;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Message wording per automation and channel (MSG-02). Editing the text bumps the version.
 */
class MessageTemplateResource extends Resource
{
    protected static ?string $model = MessageTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Message wording';

    protected static ?string $modelLabel = 'message';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(fn (?MessageTemplate $record): string => ($record?->name ?? 'Message').' · '.($record?->channel->label() ?? ''))
                ->schema([
                    Text::make(fn (?MessageTemplate $record): string => 'Placeholders available: '.collect($record?->variables() ?? [])->map(fn (string $name): string => '{{ '.$name.' }}')->implode(', ').'. Keep them exactly as written; a message with a missing value is not sent.')
                        ->color('gray'),
                    Textarea::make('body')
                        ->label('Text')
                        ->rows(5)
                        ->required()
                        ->maxLength(1000)
                        ->helperText('SMS: about 160 characters per message part. WhatsApp business messages may also need approval by the provider.'),
                    MoneyInput::make('cost_estimate_cents')->label('Estimated cost per message')->required()
                        ->helperText('Used for the monthly spend cap. Set once a provider is chosen.'),
                    TextInput::make('provider_template_id')->label('Provider template ID')->maxLength(191)
                        ->helperText('Only needed for WhatsApp, once the provider has approved the template.'),
                    Toggle::make('is_active')->label('Send this message'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (MessageTemplate $record): string => $record->key),
                TextColumn::make('channel')->formatStateUsing(fn (MessageChannel $state): string => $state->label())->badge()->color('gray'),
                TextColumn::make('classification')->label('Type')->formatStateUsing(fn (MessageClassification $state): string => $state->label()),
                IconColumn::make('is_critical')->label('Critical')->boolean()
                    ->tooltip('Critical messages still go out when the monthly spend cap is reached.'),
                TextColumn::make('version'),
                TextColumn::make('cost_estimate_cents')->label('Cost')->formatStateUsing(fn (Money $state): string => $state->format()),
                IconColumn::make('is_active')->label('On')->boolean(),
            ])
            ->filters([SelectFilter::make('channel')->options(MessageChannel::options())])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessageTemplates::route('/'),
            'edit' => EditMessageTemplate::route('/{record}/edit'),
        ];
    }
}
