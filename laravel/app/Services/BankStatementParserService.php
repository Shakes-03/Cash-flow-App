<?php

namespace App\Services;

use App\Models\BankImport;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class BankStatementParserService
{
    /** Revolut Business export column order, used when a file has no header row. */
    private const REVOLUT_COLUMNS = [
        'Date started (UTC)', 'Date completed (UTC)', 'ID', 'Type', 'State', 'Description',
        'Reference', 'Payer', 'Card number', 'Card label', 'Card state', 'Orig currency',
        'Orig amount', 'Payment currency', 'Amount', 'Total amount', 'Exchange rate', 'Fee',
        'Fee currency', 'Balance', 'Account', 'Beneficiary account number',
        'Beneficiary sort code or routing number', 'Beneficiary IBAN', 'Beneficiary BIC', 'MCC',
        'Related transaction id', 'Spend program',
    ];

    /**
     * Parses a Revolut Business statement export.
     *
     * Expected headers (whitespace-trimmed): "Date started (UTC)", "Date completed (UTC)",
     * "Description", "Reference", "Payer", "Amount", "Balance", "State". Extra/reordered
     * columns are ignored.
     *
     * Also accepts a statement hand-assembled or re-saved on a European-locale machine:
     * semicolon-delimited, decimal commas ("207,18") and no header row (columns are then
     * taken to be in Revolut's standard order).
     *
     * @throws \RuntimeException when the file contains no recognisable transactions
     */
    public function parse(string $path, string $originalFilename, int $userId): BankImport
    {
        return DB::transaction(function () use ($path, $originalFilename, $userId) {
            $handle = fopen($path, 'r');
            $firstLine = (string) fgets($handle);
            fclose($handle);
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            $decimalComma = $delimiter === ';';
            $hasHeader = str_contains($firstLine, 'Description') && (str_contains($firstLine, 'Amount') || str_contains($firstLine, 'Balance'));

            $csv = Reader::from($path, 'r');
            $csv->setDelimiter($delimiter);
            if ($hasHeader) {
                $csv->setHeaderOffset(0);
            }
            $records = $hasHeader ? $csv->getRecords() : (function () use ($csv) {
                foreach ($csv->getRecords() as $row) {
                    $row = array_slice(array_pad(array_values($row), count(self::REVOLUT_COLUMNS), ''), 0, count(self::REVOLUT_COLUMNS));
                    yield array_combine(self::REVOLUT_COLUMNS, $row);
                }
            })();

            $bankImport = BankImport::create([
                'filename' => $originalFilename,
                'imported_at' => now(),
                'status' => 'completed',
                'total_rows' => 0,
                'user_id' => $userId,
            ]);

            $rowCount = 0;
            $duplicates = 0;

            // Statements often overlap (e.g. two "last 3 months" exports), so anything already
            // imported is skipped: by the bank's own transaction ID, or - for rows imported
            // before IDs were stored - by date + direction + amount + running balance + description,
            // which can't repeat because the running balance changes with every transaction.
            $seenIds = array_fill_keys(
                Transaction::query()->whereNotNull('external_id')->pluck('external_id')->all(),
                true
            );
            $seenFingerprints = [];
            Transaction::query()
                ->whereNotNull('balance_after')
                ->get(['transaction_date', 'type', 'amount', 'balance_after', 'description'])
                ->each(function (Transaction $t) use (&$seenFingerprints) {
                    $seenFingerprints[$this->fingerprint($t->transaction_date->toDateString(), $t->type, (float) $t->amount, (float) $t->balance_after, $t->description)] = true;
                });

            foreach ($records as $rawRecord) {
                $record = [];
                foreach ($rawRecord as $key => $value) {
                    $record[trim($key)] = is_string($value) ? trim($value) : $value;
                }

                // A repeated ID (within this file or from an earlier import) is the same transaction.
                $revolutId = ($record['ID'] ?? null) ?: null;
                if ($revolutId) {
                    if (isset($seenIds[$revolutId])) {
                        $duplicates++;

                        continue;
                    }
                    $seenIds[$revolutId] = true;
                }

                $state = $record['State'] ?? null;
                if ($state !== null && $state !== '' && strtoupper($state) !== 'COMPLETED') {
                    continue;
                }

                $dateValue = $record['Date completed (UTC)'] ?? null;
                if (! $dateValue) {
                    $dateValue = $record['Date started (UTC)'] ?? null;
                }

                // "Total amount" includes any Revolut fee and is what actually moves the balance;
                // "Amount" excludes it. Fall back to "Amount" for exports without the column.
                $amountValue = ($record['Total amount'] ?? '') !== '' ? $record['Total amount'] : ($record['Amount'] ?? null);

                if (! $dateValue || $amountValue === null || $amountValue === '') {
                    continue;
                }

                $amount = $this->parseNumber($amountValue, $decimalComma);
                $balanceValue = $record['Balance'] ?? null;
                $balance = ($balanceValue !== null && $balanceValue !== '') ? $this->parseNumber($balanceValue, $decimalComma) : null;
                $date = $this->parseDate($dateValue);
                if (! $date) {
                    continue;
                }
                $type = $amount >= 0 ? 'credit' : 'debit';
                $description = $record['Description'] ?? '';

                if ($balance !== null) {
                    $fingerprint = $this->fingerprint($date->toDateString(), $type, abs($amount), $balance, $description);
                    if (isset($seenFingerprints[$fingerprint])) {
                        $duplicates++;

                        continue;
                    }
                    $seenFingerprints[$fingerprint] = true;
                }

                Transaction::create([
                    'bank_import_id' => $bankImport->id,
                    'external_id' => $revolutId,
                    'transaction_date' => $date,
                    'description' => $description,
                    'payer' => $record['Payer'] ?: null,
                    'amount' => abs($amount),
                    'balance_after' => $balance,
                    'type' => $type,
                    'reference' => $record['Reference'] ?: null,
                ]);

                $rowCount++;
            }

            if ($rowCount === 0 && $duplicates === 0) {
                // Rolls back the empty BankImport record too.
                throw new \RuntimeException('No transactions could be read from this file. Check it is a Revolut statement export saved as CSV.');
            }

            $bankImport->update(['total_rows' => $rowCount, 'duplicates_skipped' => $duplicates]);

            return $bankImport;
        });
    }

    /**
     * Revolut exports "Y-m-d H:i:s", but a statement opened and re-saved in Excel on a UK
     * machine comes back as "d/m/Y H:i" - which Carbon::parse() would read as US m/d/Y
     * (silently turning 05/09 into 9 May). Slashed dates are therefore always day-first.
     */
    /**
     * "1,234.56", "-207,18", "1.234,56", "£ 960" -> float. When only a comma is present it is the
     * decimal separator in a semicolon (European) file, otherwise a thousands separator.
     */
    private function parseNumber(string $value, bool $decimalComma): float
    {
        $value = preg_replace('/[^\d,.\-]/', '', $value);

        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');

        if ($lastComma !== false && $lastDot !== false) {
            $value = $lastComma > $lastDot
                ? str_replace(',', '.', str_replace('.', '', $value))
                : str_replace(',', '', $value);
        } elseif ($lastComma !== false) {
            $value = $decimalComma ? str_replace(',', '.', $value) : str_replace(',', '', $value);
        }

        return (float) $value;
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);

        // Day-first dates separated by "/", "-", "." or spaces ("05/09/2026", "14 09 2026").
        if (preg_match('#^(\d{1,2})[/\-. ](\d{1,2})[/\-. ](\d{2,4})(?:\s+(\d{1,2}):(\d{2})(?::(\d{2}))?)?$#', $value, $m)) {
            $year = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];

            return checkdate((int) $m[2], (int) $m[1], $year)
                ? Carbon::create($year, (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), (int) ($m[6] ?? 0))
                : null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function fingerprint(string $date, string $type, float $amount, float $balance, ?string $description): string
    {
        return implode('|', [$date, $type, number_format($amount, 2, '.', ''), number_format($balance, 2, '.', ''), mb_strtolower(trim((string) $description))]);
    }
}
