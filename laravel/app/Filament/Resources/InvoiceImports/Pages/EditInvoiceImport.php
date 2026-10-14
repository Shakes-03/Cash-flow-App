<?php

namespace App\Filament\Resources\InvoiceImports\Pages;

use App\Filament\Resources\InvoiceImports\InvoiceImportResource;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Schema;

class EditInvoiceImport extends EditRecord
{
    protected static string $resource = InvoiceImportResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('filename')->disabled(),
                TextInput::make('status')->disabled(),
                TextInput::make('total_rows')->label('Imported')->disabled(),
                TextInput::make('duplicates_skipped')->label('Already imported (skipped)')->disabled(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
