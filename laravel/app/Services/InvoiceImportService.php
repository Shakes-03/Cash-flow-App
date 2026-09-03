<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceImport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class InvoiceImportService
{
    /**
     * Parses an invoice CSV with columns: Customer Name, Customer Number (optional),
     * Invoice Number, Invoice Date, Amount, Description (optional). A customer is
     * created automatically the first time its name/number is seen.
     */
    public function parse(string $path, string $originalFilename, int $userId): InvoiceImport
    {
        return DB::transaction(function () use ($path, $originalFilename, $userId) {
            $csv = Reader::from($path, 'r');
            $csv->setHeaderOffset(0);

            $invoiceImport = InvoiceImport::create([
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

                $customerName = $record['Customer Name'] ?? null;
                $amountValue = $record['Amount'] ?? null;
                $dateValue = $record['Invoice Date'] ?? null;

                if (! $customerName || $amountValue === null || $amountValue === '' || ! $dateValue) {
                    continue;
                }

                $customerNumber = $record['Customer Number'] ?? null;

                $customer = $customerNumber
                    ? Customer::firstOrCreate(
                        ['customer_number' => $customerNumber],
                        ['name' => $customerName]
                    )
                    : Customer::firstOrCreate(['name' => $customerName]);

                Invoice::create([
                    'customer_id' => $customer->id,
                    'invoice_import_id' => $invoiceImport->id,
                    'invoice_number' => $record['Invoice Number'] ?? null,
                    'invoice_date' => Carbon::parse($dateValue),
                    'amount' => (float) str_replace(',', '', $amountValue),
                    'description' => $record['Description'] ?? null,
                ]);

                $rowCount++;
            }

            $invoiceImport->update(['total_rows' => $rowCount]);

            return $invoiceImport;
        });
    }
}
