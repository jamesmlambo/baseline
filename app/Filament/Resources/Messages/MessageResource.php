<?php

declare(strict_types=1);

namespace App\Filament\Resources\Messages;

use App\Enums\MessageStatus;
use App\Filament\Resources\Messages\Pages\ListMessages;
use App\Filament\Tables\MessageLog;
use App\Models\Message;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every SMS and WhatsApp the system has sent or deliberately not sent (MSG-05).
 */
class MessageResource extends Resource
{
    protected static ?string $model = Message::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Front desk';

    protected static ?string $navigationLabel = 'Messages';

    protected static ?int $navigationSort = 40;

    public static function getNavigationBadge(): ?string
    {
        $failed = Message::query()->where('status', MessageStatus::Failed)->where('created_at', '>=', now()->subDays(7))->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Messages that failed in the last 7 days';
    }

    public static function table(Table $table): Table
    {
        return MessageLog::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMessages::route('/'),
        ];
    }
}
