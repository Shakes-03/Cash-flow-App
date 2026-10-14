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
    /** Invoice numbers / fingerprints already in the database, so re-imports don't double the debtors book. */
    private array $seenNumbers = [];

    private array $seenFingerprints = [];

    public function __construct(private XeroInvoicePdfParser $pdfParser) {}

    /**
     * Imports one or more uploaded files as a single InvoiceImport: Xero invoice PDFs (one
     * invoice each), Xero's own invoice CSV export, and/or invoice CSVs with columns Customer Name,
     * Customer Number (optional), Invoice Number, Invoice Date, Amount, Description (optional). A customer is created
     * automatically the first time its name/number is seen.
     *
     * Any unreadable file or row rolls back the whole import, so a fixed batch can simply be
     * uploaded again.
     *
     * @param  array<string, string>  $files  original filename => full path
     */
    public function import(array $files, int $userId): InvoiceImport
    {
        return DB::transaction(function () use ($files, $userId) {
            $invoiceImport = InvoiceImport::create([
                'filename' => count($files) === 1 ? array_key_first($files) : count($files).' files: '.mb_strimwidth(implode(', ', array_keys($files)), 0, 200, '...'),
                'imported_at' => now(),
                'status' => 'completed',
                'total_rows' => 0,
                'user_id' => $userId,
            ]);

            $this->loadSeen();
            $rowCount = 0;
            $duplicates = 0;

            foreach ($files as $filename => $path) {
                foreach ($this->rows($filename, $path) as $source => $record) {
                    match ($this->importRow($record, $source, $invoiceImport)) {
                        'imported' => $rowCount++,
                        'duplicate' => $duplicates++,
                        'skipped' => null,
                    };
                }
            }

            if ($rowCount === 0 && $duplicates === 0) {
                // Rolls back the empty InvoiceImport record too.
                throw new \RuntimeException('No invoices could be read. Upload the invoice export CSV from Xero, Xero invoice PDFs, or a CSV with the columns listed above.');
            }

            $invoiceImport->update(['total_rows' => $rowCount, 'duplicates_skipped' => $duplicates]);

            return $invoiceImport;
        });
    }

    /**
     * Yields "where it came from" => invoice row, for error messages.
     */
    private function rows(string $filename, string $path): \Generator
    {
        if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'pdf') {
            try {
                yield $filename => $this->pdfParser->parse($path);
            } catch (\RuntimeException $e) {
                throw new \RuntimeException("{$filename}: ".$e->getMessage());
            }

            return;
        }

        $csv = Reader::from($path, 'r');
        $csv->setHeaderOffset(0);

        $headerKeys = array_map(fn ($key) => $this->xeroKey($key), $csv->getHeader());
        if (in_array('contactname', $headerKeys, true) && in_array('invoicenumber', $headerKeys, true)) {
            yield from $this->xeroExportRows($filename, $csv);

            return;
        }

        // Header is row 1, so the first record is row 2 - matches what Excel shows.
        foreach ($csv->getRecords() as $offset => $rawRecord) {
            $record = [];
            foreach ($rawRecord as $key => $value) {
                $record[trim($key)] = is_string($value) ? trim($value) : $value;
            }

            yield "{$filename} row ".($offset + 1) => $record;
        }
    }

    /**
     * Xero's own invoice export (Sales -> Invoices -> Export): one row per invoice *line*, the
     * invoice's details repeated on each, with headers like "ContactName"/"*ContactName",
     * "InvoiceNumber", "InvoiceDate", "Total". Collapsed here to one row per invoice, at its
     * VAT-inclusive Total. Drafts, invoices awaiting approval and voided/deleted ones aren't
     * real debts and are left out; credit notes become negative amounts.
     */
    private function xeroExportRows(string $filename, Reader $csv): \Generator
    {
        $invoices = [];

        foreach ($csv->getRecords() as $offset => $rawRecord) {
            $row = [];
            foreach ($rawRecord as $key => $value) {
                $row[$this->xeroKey($key)] = is_string($value) ? trim($value) : $value;
            }

            $number = $row['invoicenumber'] ?? '';
            if ($number === '' || isset($invoices[$number])) {
                continue; // a further line of an invoice already taken
            }

            $status = strtolower($row['status'] ?? '');
            if (in_array($status, ['draft', 'submitted', 'awaiting approval', 'voided', 'void', 'deleted'], true)) {
                $invoices[$number] = null;

                continue;
            }

            if (($row['total'] ?? '') === '') {
                throw new \RuntimeException("{$filename}: no \"Total\" column found. Export the invoices from Xero again with all columns included.");
            }

            $amount = (float) preg_replace('/[^\d.\-]/', '', $row['total']);
            if ($amount > 0 && str_contains(strtolower(($row['type'] ?? '').' '.$number), 'credit')) {
                $amount = -$amount;
            }

            $invoices[$number] = ["{$filename} row ".($offset + 1) => [
                'Customer Name' => $row['contactname'] ?? '',
                'Customer Number' => $row['accountnumber'] ?? '',
                'Invoice Number' => $number,
                'Invoice Date' => $row['invoicedate'] ?? '',
                'Amount' => (string) $amount,
                'Description' => $row['reference'] ?? '',
            ]];
        }

        foreach (array_filter($invoices) as $invoice) {
            yield from $invoice;
        }
    }

    /** "*ContactName", "Contact Name" and a UTF-8 BOM-prefixed "ContactName" all give "contactname". */
    private function xeroKey(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($header));
    }

    /**
     * @return 'imported'|'duplicate'|'skipped'
     */
    private function importRow(array $record, string $source, InvoiceImport $invoiceImport): string
    {
        $customerName = $record['Customer Name'] ?? null;
        $amountValue = $record['Amount'] ?? null;
        $dateValue = $record['Invoice Date'] ?? null;

        if (! $customerName || $amountValue === null || $amountValue === '' || ! $dateValue) {
            return 'skipped';
        }

        $date = $this->parseDate($dateValue);
        if (! $date) {
            throw new \RuntimeException("{$source}: invoice date \"{$dateValue}\" could not be read. Use a format like 02/11/2026 or 2026-11-02.");
        }

        // A "number" with no letters or digits (e.g. "-") is treated as no number.
        $invoiceNumber = Invoice::normaliseNumber($record['Invoice Number'] ?? null) !== null ? $record['Invoice Number'] : null;
        $amount = (float) preg_replace('/[^\d.\-]/', '', $amountValue);
        $description = ($record['Description'] ?? '') !== '' ? $record['Description'] : null;

        if ($invoiceNumber !== null) {
            $key = $this->normaliseNumber($invoiceNumber);
            if (isset($this->seenNumbers[$key])) {
                return 'duplicate';
            }
            $this->seenNumbers[$key] = true;
        }

        $customer = $this->findOrCreateCustomer($customerName, ($record['Customer Number'] ?? '') ?: null);

        if ($invoiceNumber === null) {
            $fingerprint = $this->fingerprint($customer->id, $date->toDateString(), $amount, $description);
            if (isset($this->seenFingerprints[$fingerprint])) {
                return 'duplicate';
            }
            $this->seenFingerprints[$fingerprint] = true;
        }

        Invoice::create([
            'customer_id' => $customer->id,
            'invoice_import_id' => $invoiceImport->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => $date,
            'amount' => $amount,
            'description' => $description,
        ]);

        return 'imported';
    }

    private function loadSeen(): void
    {
        $this->seenNumbers = array_fill_keys(
            Invoice::query()->whereNotNull('invoice_number')->pluck('invoice_number')
                ->map(fn ($number) => $this->normaliseNumber($number))->all(),
            true
        );

        $this->seenFingerprints = [];
        Invoice::query()
            ->whereNull('invoice_number')
            ->get(['customer_id', 'invoice_date', 'amount', 'description'])
            ->each(function (Invoice $invoice) {
                $this->seenFingerprints[$this->fingerprint($invoice->customer_id, $invoice->invoice_date->toDateString(), (float) $invoice->amount, $invoice->description)] = true;
            });
    }

    /**
     * By customer number when given, otherwise by name - ignoring case, spacing, punctuation
     * and a trailing Ltd/Limited, so Xero's "ProMedical Personnel Ltd" finds the existing
     * "ProMedical Personnel" (and PDF text glitches like "L td" don't create a new customer).
     */
    private function findOrCreateCustomer(string $name, ?string $customerNumber): Customer
    {
        if ($customerNumber) {
            return Customer::firstOrCreate(['customer_number' => $customerNumber], ['name' => $name]);
        }

        $key = $this->normaliseName($name);

        return Customer::all()->first(fn (Customer $customer) => $this->normaliseName($customer->name) === $key)
            ?? Customer::create(['name' => $name]);
    }

    private function normaliseName(string $name): string
    {
        $name = preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));

        return preg_replace('/(ltd|limited)$/', '', $name);
    }

    /**
     * Slashed/dotted dates are always day-first ("02/11/2026" is 2 November): Carbon::parse()
     * would read them as US m/d/Y and silently swap day and month. ISO "2026-11-02" and Xero's
     * "2 Oct 2026" still work.
     */
    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        if (preg_match('#^(\d{1,2})[/\-. ](\d{1,2})[/\-. ](\d{2,4})$#', $value, $m)) {
            $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

            return checkdate((int) $m[2], (int) $m[1], $year)
                ? Carbon::create($year, (int) $m[2], (int) $m[1])
                : null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normaliseNumber(string $invoiceNumber): string
    {
        return Invoice::normaliseNumber($invoiceNumber) ?? '';
    }

    private function fingerprint(int $customerId, string $date, float $amount, ?string $description): string
    {
        return implode('|', [$customerId, $date, number_format($amount, 2, '.', ''), mb_strtolower(trim((string) $description))]);
    }
}
