<?php

namespace App\Filament\Resources\BankImports\Pages;

use App\Filament\Resources\BankImports\BankImportResource;
use App\Models\BankImport;
use App\Services\BankStatementParserService;
use App\Services\CategorizationService;
use App\Services\CustomerMatchingService;
use Filament\Resources\Pages\CreateRecord;
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

        $bankImport = app(BankStatementParserService::class)->parse($fullPath, $originalFilename, auth()->id());

        $categorizer = app(CategorizationService::class);
        $customerMatcher = app(CustomerMatchingService::class);

        foreach ($bankImport->transactions as $transaction) {
            $categorizer->categorize($transaction);
            $customerMatcher->match($transaction);
        }

        return $bankImport;
    }
}
