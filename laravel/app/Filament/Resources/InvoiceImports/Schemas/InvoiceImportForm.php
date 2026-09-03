<?php

namespace App\Filament\Resources\InvoiceImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class InvoiceImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('csv_file')
                    ->label('Invoices CSV')
                    ->helperText('Columns: Customer Name, Customer Number (optional), Invoice Number, Invoice Date, Amount, Description (optional).')
                    ->disk('local')
                    ->directory('invoice-imports')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
