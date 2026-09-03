<?php

namespace App\Filament\Resources\BankImports\Pages;

use App\Filament\Resources\BankImports\BankImportResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditBankImport extends EditRecord
{
    protected static string $resource = BankImportResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('filename')->disabled(),
                TextInput::make('status')->disabled(),
                TextInput::make('total_rows')->disabled(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
