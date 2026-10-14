<?php

namespace App\Filament\Resources\CashFlowEntries\Pages;

use App\Filament\Resources\CashFlowEntries\CashFlowEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCashFlowEntries extends ListRecords
{
    protected static string $resource = CashFlowEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
