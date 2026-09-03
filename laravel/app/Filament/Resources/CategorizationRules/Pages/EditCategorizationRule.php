<?php

namespace App\Filament\Resources\CategorizationRules\Pages;

use App\Filament\Resources\CategorizationRules\CategorizationRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategorizationRule extends EditRecord
{
    protected static string $resource = CategorizationRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
