<?php

namespace App\Filament\Resources\CategorizationRules\Pages;

use App\Filament\Resources\CategorizationRules\CategorizationRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategorizationRules extends ListRecords
{
    protected static string $resource = CategorizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
