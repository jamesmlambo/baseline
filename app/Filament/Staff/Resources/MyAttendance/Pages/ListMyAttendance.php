<?php

declare(strict_types=1);

namespace App\Filament\Staff\Resources\MyAttendance\Pages;

use App\Filament\Staff\Resources\MyAttendance\MyAttendanceResource;
use Filament\Resources\Pages\ListRecords;

class ListMyAttendance extends ListRecords
{
    protected static string $resource = MyAttendanceResource::class;
}
