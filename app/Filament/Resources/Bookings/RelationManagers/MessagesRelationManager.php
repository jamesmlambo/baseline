<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Filament\Tables\MessageLog;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

/**
 * Every SMS/WhatsApp sent for this booking, with resend (MSG-05).
 */
class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return MessageLog::configure($table, showBooking: false);
    }
}
