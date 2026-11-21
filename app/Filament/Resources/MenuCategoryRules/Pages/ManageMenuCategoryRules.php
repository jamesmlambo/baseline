<?php

declare(strict_types=1);

namespace App\Filament\Resources\MenuCategoryRules\Pages;

use App\Filament\Resources\MenuCategoryRules\MenuCategoryRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMenuCategoryRules extends ManageRecords
{
    protected static string $resource = MenuCategoryRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
