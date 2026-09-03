<?php

namespace App\Filament\Resources\CustomerMatchingRules\Pages;

use App\Filament\Resources\CustomerMatchingRules\CustomerMatchingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerMatchingRules extends ListRecords
{
    protected static string $resource = CustomerMatchingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
