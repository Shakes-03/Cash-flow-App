<?php

namespace App\Filament\Resources\CashFlowEntries\Pages;

use App\Filament\Resources\CashFlowEntries\CashFlowEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCashFlowEntry extends CreateRecord
{
    protected static string $resource = CashFlowEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        return $data;
    }
}
