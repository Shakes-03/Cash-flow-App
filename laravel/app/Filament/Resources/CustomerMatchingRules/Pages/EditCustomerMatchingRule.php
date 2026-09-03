<?php

namespace App\Filament\Resources\CustomerMatchingRules\Pages;

use App\Filament\Resources\CustomerMatchingRules\CustomerMatchingRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerMatchingRule extends EditRecord
{
    protected static string $resource = CustomerMatchingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
