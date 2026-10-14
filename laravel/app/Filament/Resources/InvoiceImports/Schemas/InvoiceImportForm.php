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
                FileUpload::make('files')
                    ->label('Invoices')
                    ->helperText('Easiest: the invoice export CSV from Xero (Sales > Invoices > Export) - one file for every invoice. Xero invoice PDFs also work - select as many as you like. Or a CSV with columns: Customer Name, Customer Number (optional), Invoice Number, Invoice Date, Amount, Description (optional). Invoices already imported are skipped.')
                    ->multiple()
                    ->disk('local')
                    ->directory('invoice-imports')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf', 'text/csv', 'application/vnd.ms-excel', 'text/plain'])
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
