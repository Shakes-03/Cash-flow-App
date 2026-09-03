<?php

namespace App\Filament\Resources\BankImports\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;

class BankImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('csv_file')
                    ->label('Bank Statement CSV')
                    ->disk('local')
                    ->directory('bank-imports')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
