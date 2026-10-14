<?php

namespace App\Filament\Resources\InvoiceImports\Pages;

use App\Filament\Resources\InvoiceImports\InvoiceImportResource;
use App\Services\InvoiceImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateInvoiceImport extends CreateRecord
{
    protected static string $resource = InvoiceImportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $files = collect($data['files'])
            ->mapWithKeys(fn (string $relativePath) => [basename($relativePath) => Storage::disk('local')->path($relativePath)])
            ->all();

        try {
            $invoiceImport = app(InvoiceImportService::class)->import($files, auth()->id());
        } catch (\RuntimeException $e) {
            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->persistent()->send();

            throw new Halt;
        }

        Notification::make()
            ->title("Imported {$invoiceImport->total_rows} invoices")
            ->body($invoiceImport->duplicates_skipped ? "{$invoiceImport->duplicates_skipped} already imported - skipped." : null)
            ->success()
            ->persistent()
            ->send();

        return $invoiceImport;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // replaced by the import summary sent from handleRecordCreation()
    }
}
