<?php

namespace App\Filament\Resources\BankImports\Pages;

use App\Filament\Resources\BankImports\BankImportResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBankImport extends EditRecord
{
    protected static string $resource = BankImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
