<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // A category or customer picked by hand must survive the next re-run of the keyword rules.
        $changedByHand = (int) ($data['category_id'] ?? 0) !== (int) $this->record->category_id
            || (int) ($data['customer_id'] ?? 0) !== (int) $this->record->customer_id;

        if ($changedByHand) {
            $data['category_locked'] = true;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
