<?php

namespace App\Services;

use App\Models\PbeImport;
use App\Models\PbePayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses a Payroll Bureau Engine (PBE) "Master Report" / "Verify Payments By Employee"
 * export. Each worksheet is a sequence of pay-date blocks:
 *
 *   Pay Date | 01/09/2026 | Week No | 22 | Month No | 5 | Bank Date | 01/09/2026
 *   Employee ID | Firstname | Surname | Net Sales Invoice | ... | Account Name   <- column headers
 *   ProMedical Personnel Ltd                                                     <- client/agency name
 *   UMB000656 | Emmanuel | Mugweni | 780 | 156 | 936 | ...                       <- employee payment row
 *   Agency Total | ... totals ...
 *   Nohow International Ltd                                                     <- next client, same pay date
 *   ...
 *   Grand Total | ... totals ...
 *   Pay Date | 02/09/2026 | ...                                                 <- next block
 */
class PbeReportParserService
{
    private const REQUIRED_COLUMNS = [
        'Employee ID', 'Firstname', 'Surname', 'Net Sales Invoice', 'VAT Sales Invoice',
        'Gross Sales Invoice', 'Total Pay', 'ERS NIC', 'EES NIC', 'ERS Pension',
        'EES Pension', 'Apprenticeship Levy', 'Margin',
    ];

    public function parse(string $path, string $originalFilename, int $userId): PbeImport
    {
        ini_set('memory_limit', '2048M');

        return DB::transaction(function () use ($path, $originalFilename, $userId) {
            $pbeImport = PbeImport::create([
                'filename' => $originalFilename,
                'imported_at' => now(),
                'total_rows' => 0,
                'user_id' => $userId,
            ]);

            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);

            $rowCount = 0;
            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $rowCount += $this->parseSheet($sheet, $pbeImport);
            }

            $pbeImport->update(['total_rows' => $rowCount]);

            return $pbeImport;
        });
    }

    private function parseSheet(Worksheet $sheet, PbeImport $pbeImport): int
    {
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();

        $columnMap = null;
        $payDate = null;
        $bankDate = null;
        $weekNo = null;
        $monthNo = null;
        $clientName = null;
        $rowCount = 0;

        for ($row = 1; $row <= $highestRow; $row++) {
            $values = $sheet->rangeToArray("A{$row}:{$highestCol}{$row}", null, true, false)[0];
            $values = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $values);
            $colA = $values[0] ?? null;
            $colB = $values[1] ?? null;
            $colC = $values[2] ?? null;

            if ($colA === 'Pay Date') {
                $payDate = $this->parseDate($values[1] ?? null);
                $weekNo = is_numeric($values[3] ?? null) ? (int) $values[3] : null;
                $monthNo = is_numeric($values[5] ?? null) ? (int) $values[5] : null;
                $bankDate = $this->parseDate($values[7] ?? null);
                $clientName = null;

                continue;
            }

            if ($colA === 'Employee ID') {
                $columnMap = array_flip(array_filter($values, fn ($v) => $v !== null && $v !== ''));

                continue;
            }

            if ($colA === 'Agency Total' || $colA === 'Grand Total' || $colA === null || $colA === '') {
                continue;
            }

            if (($colB === null || $colB === '') && ($colC === null || $colC === '')) {
                // A bare label row with nothing in Firstname/Surname is the client/agency name.
                $clientName = $colA;

                continue;
            }

            if (! $columnMap || ! $payDate || ! $clientName) {
                continue;
            }

            $get = fn (string $name) => isset($columnMap[$name]) ? ($values[$columnMap[$name]] ?? null) : null;
            $number = fn ($v) => is_numeric($v) ? (float) $v : 0.0;

            PbePayment::create([
                'pbe_import_id' => $pbeImport->id,
                'employee_ref' => $colA,
                'first_name' => $colB,
                'last_name' => $colC,
                'client_name' => $clientName,
                'pay_date' => $payDate,
                'bank_date' => $bankDate,
                'week_no' => $weekNo,
                'month_no' => $monthNo,
                'gross_sales_invoice' => $number($get('Gross Sales Invoice')),
                'net_sales_invoice' => $number($get('Net Sales Invoice')),
                'vat_sales_invoice' => $number($get('VAT Sales Invoice')),
                'total_pay' => $number($get('Total Pay')),
                'ers_nic' => $number($get('ERS NIC')),
                'ees_nic' => $number($get('EES NIC')),
                'ers_pension' => $number($get('ERS Pension')),
                'ees_pension' => $number($get('EES Pension')),
                'apprenticeship_levy' => $number($get('Apprenticeship Levy')),
                'margin' => $number($get('Margin')),
            ]);

            $rowCount++;
        }

        return $rowCount;
    }

    private function parseDate(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
        } catch (\Throwable) {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
