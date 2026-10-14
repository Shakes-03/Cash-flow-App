<?php

namespace App\Filament\Resources\CashFlowEntries\Pages;

use App\Filament\Resources\CashFlowEntries\CashFlowEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCashFlowEntry extends EditRecord
{
    protected static string $resource = CashFlowEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
