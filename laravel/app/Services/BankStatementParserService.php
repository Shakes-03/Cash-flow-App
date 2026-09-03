<?php

namespace App\Services;

use App\Models\BankImport;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class BankStatementParserService
{
    /**
     * Parses a Revolut Business statement export.
     *
     * Expected headers (whitespace-trimmed): "Date started (UTC)", "Date completed (UTC)",
     * "Description", "Reference", "Payer", "Amount", "Balance", "State". Extra/reordered
     * columns are ignored.
     */
    public function parse(string $path, string $originalFilename, int $userId): BankImport
    {
        return DB::transaction(function () use ($path, $originalFilename, $userId) {
            $csv = Reader::from($path, 'r');
            $csv->setHeaderOffset(0);

            $bankImport = BankImport::create([
                'filename' => $originalFilename,
                'imported_at' => now(),
                'status' => 'completed',
                'total_rows' => 0,
                'user_id' => $userId,
            ]);

            $rowCount = 0;

            foreach ($csv->getRecords() as $rawRecord) {
                $record = [];
                foreach ($rawRecord as $key => $value) {
                    $record[trim($key)] = is_string($value) ? trim($value) : $value;
                }

                $state = $record['State'] ?? null;
                if ($state !== null && $state !== '' && strtoupper($state) !== 'COMPLETED') {
                    continue;
                }

                $dateValue = $record['Date completed (UTC)'] ?? null;
                if (! $dateValue) {
                    $dateValue = $record['Date started (UTC)'] ?? null;
                }

                $amountValue = $record['Amount'] ?? null;

                if (! $dateValue || $amountValue === null || $amountValue === '') {
                    continue;
                }

                $amount = (float) str_replace(',', '', $amountValue);
                $balanceValue = $record['Balance'] ?? null;

                Transaction::create([
                    'bank_import_id' => $bankImport->id,
                    'transaction_date' => Carbon::parse($dateValue),
                    'description' => $record['Description'] ?? '',
                    'payer' => $record['Payer'] ?: null,
                    'amount' => abs($amount),
                    'balance_after' => ($balanceValue !== null && $balanceValue !== '') ? (float) str_replace(',', '', $balanceValue) : null,
                    'type' => $amount >= 0 ? 'credit' : 'debit',
                    'reference' => $record['Reference'] ?: null,
                ]);

                $rowCount++;
            }

            $bankImport->update(['total_rows' => $rowCount]);

            return $bankImport;
        });
    }
}
