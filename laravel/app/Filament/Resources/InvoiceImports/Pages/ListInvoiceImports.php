<?php

namespace App\Filament\Resources\InvoiceImports\Pages;

use App\Filament\Resources\InvoiceImports\InvoiceImportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInvoiceImports extends ListRecords
{
    protected static string $resource = InvoiceImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
