<?php

namespace App\Filament\Resources\BankImports\Pages;

use App\Filament\Resources\BankImports\BankImportResource;
use App\Models\BankImport;
use App\Services\BankStatementParserService;
use App\Services\CategorizationService;
use App\Services\CustomerMatchingService;
use App\Services\PbePaymentMatchingService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateBankImport extends CreateRecord
{
    protected static string $resource = BankImportResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $relativePath = $data['csv_file'];
        $fullPath = Storage::disk('local')->path($relativePath);
        $originalFilename = basename($relativePath);

        try {
            $bankImport = app(BankStatementParserService::class)->parse($fullPath, $originalFilename, auth()->id());
        } catch (\RuntimeException $e) {
            Notification::make()->title('Import failed')->body($e->getMessage())->danger()->persistent()->send();

            throw new Halt;
        }

        $categorizer = app(CategorizationService::class);
        $customerMatcher = app(CustomerMatchingService::class);

        foreach ($bankImport->transactions as $transaction) {
            $categorizer->categorize($transaction);
            $customerMatcher->match($transaction);
        }

        // Payroll records already imported may now find their bank payment, which splits
        // the new weeks' payroll per client on the Cash Flow report.
        $pbe = app(PbePaymentMatchingService::class)->matchAll();

        Notification::make()
            ->title("Imported {$bankImport->total_rows} transactions")
            ->body(collect([
                $bankImport->duplicates_skipped ? "{$bankImport->duplicates_skipped} already imported - skipped." : null,
                $pbe['matched'] ? "{$pbe['matched']} payroll (PBE) payments matched." : null,
            ])->filter()->implode(' ') ?: null)
            ->success()
            ->persistent()
            ->send();

        return $bankImport;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // replaced by the import summary sent from handleRecordCreation()
    }
}
