<?php

namespace App\Filament\Resources\InvoiceImports\Pages;

use App\Filament\Resources\InvoiceImports\InvoiceImportResource;
use App\Services\InvoiceImportService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateInvoiceImport extends CreateRecord
{
    protected static string $resource = InvoiceImportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $relativePath = $data['csv_file'];
        $fullPath = Storage::disk('local')->path($relativePath);
        $originalFilename = basename($relativePath);

        return app(InvoiceImportService::class)->parse($fullPath, $originalFilename, auth()->id());
    }
}
