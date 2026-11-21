<?php

declare(strict_types=1);

namespace App\Filament\Resources\ChannelExceptions\Pages;

use App\Filament\Resources\ChannelExceptions\ChannelExceptionResource;
use Filament\Resources\Pages\ListRecords;

class ListChannelExceptions extends ListRecords
{
    protected static string $resource = ChannelExceptionResource::class;
}
