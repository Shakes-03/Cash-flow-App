<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders a CashFlowService::build() result as a formatted workbook matching the layout
 * of Payshield's own "Income & Cash Flow Statement" tab: Sales Revenue -> Total Sales ->
 * Cost of Sales -> Total COS -> Gross Profit -> Expenses -> Total Expenses -> PAYE/Pension/VAT
 * -> Cashflow Statement.
 *
 * Only the per-account weekly amounts and the very first Opening Balance are values; every
 * total, Gross Profit, margin, Net Inflow and each week's Opening/Closing Balance is a live
 * formula. Amounts can be typed over or added in the spare (yellow) rows and the whole
 * sheet - including every later week's balances - recalculates.
 */
class CashFlowExcelExporter
{
    private const MONEY_FORMAT = '£#,##0.00;(£#,##0.00);;';

    private const PERCENT_FORMAT = '0.0%;(0.0%);;';

    private const INPUT_FILL = 'FFF2CC';

    /** Blank rows left inside each section's SUM range for accounts added by hand. */
    private const SPARE_ROWS = 3;

    /**
     * Rows the real workbook lists in this order. Anything in our data that isn't in this
     * list (e.g. a category the user has since added) is appended after, so nothing is lost.
     */
    private const INCOME_ORDER = [
        'TSSI Recruitment (UK) Ltd',
        'TSSI Group Ltd',
        'Teaching Tomorrow Ltd',
        'Enlighten Supply Pool Ltd',
        'Medecho Ltd',
        'First Point Healthcare',
        'CT&R Technical Services Ltd',
        'ProMedical Personnel',
        'Hackney & Kent Commercial Services',
        'Connect 2 Hampshire',
        'Nohow International',
        'TSSI Recruit Ltd',
    ];

    private const EXPENSE_ORDER = [
        'Accountancy Fees',
        'Bank Charges',
        'Consultancy',
        'Insurance',
        'IT Software & Consumables',
        'Licences & Subscriptions',
        'Buffer',
        'Telephone and Internet Costs',
        'Interest - Expenses External',
        'Back Office Salaries - Operations',
        'Salaries Management',
        'Staff Welfare',
        'Entertainment',
        'Postage & Carriage',
        'Legal & Professional Fees',
    ];

    /**
     * Below-the-line categories the reference workbook shows after Total Expenses rather
     * than inside the main expense list (they don't reduce Total Expenses, but still count
     * toward Net Cashflow). Order matches the reference workbook.
     */
    private const BELOW_THE_LINE = ['PAYE Accrual HMRC', 'Pension Payments', 'VAT Effect Payable/Refundable'];

    private Worksheet $sheet;

    private int $weekCount;

    private string $lastWeekCol;

    private string $totalCol;

    public function toXlsx(array $cashFlow): string
    {
        $weeks = $cashFlow['weeks'];
        $rows = $cashFlow['rows'];
        $totals = $cashFlow['totals'];
        $this->weekCount = count($weeks);

        $spreadsheet = new Spreadsheet;
        $this->sheet = $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cash Flow');

        $this->lastWeekCol = $this->col($this->weekCount - 1);
        $this->totalCol = $lastCol = $this->col($this->weekCount);

        $row = 1;
        $sheet->setCellValue("A{$row}", 'INCOME STATEMENT & CASHFLOW PROJECTIONS');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $row++;
        $sheet->setCellValue("A{$row}", 'Type over any weekly amount or use the yellow spare rows for new accounts - totals, Gross Profit, Net and every later week\'s balances recalculate automatically.');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setRGB('595959');
        $row++;

        $headerRow = $row;
        $sheet->setCellValue("A{$headerRow}", 'DESCRIPTION');
        foreach ($weeks as $i => $week) {
            $sheet->setCellValue("{$this->col($i)}{$headerRow}", 'WEEK'.($i + 1));
        }
        $sheet->setCellValue("{$lastCol}{$headerRow}", 'TOTAL');

        $dateRow = $headerRow + 1;
        foreach ($weeks as $i => $week) {
            $sheet->setCellValue("{$this->col($i)}{$dateRow}", $week['start']->format('d M').' - '.$week['end']->format('d M'));
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$dateRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$dateRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9D9D9');
        $row = $dateRow + 2;

        // Keyed per section: a client's Sales row and its Cost of Sales row share a label.
        $orderedRows = function (array $preferredOrder, string $section) use ($rows): array {
            $rowsByLabel = collect($rows)->where('section', $section)->keyBy('label');
            $ordered = [];
            foreach ($preferredOrder as $label) {
                if ($rowsByLabel->has($label)) {
                    $ordered[] = $rowsByLabel->get($label);
                }
            }
            $seen = collect($ordered)->pluck('key')->all();
            foreach ($rows as $candidate) {
                if ($candidate['section'] === $section
                    && ! in_array($candidate['key'], $seen, true)
                    && ! in_array($candidate['label'], self::BELOW_THE_LINE, true)
                    && ! str_starts_with($candidate['key'], 'uncategorized-')
                ) {
                    $ordered[] = $candidate;
                }
            }

            return $ordered;
        };
        $uncategorized = fn (string $key) => collect($rows)->firstWhere('key', $key);

        // --- Sales Revenue ---
        $this->sectionHeader($row, 'Sales Revenue:');
        $salesStart = $row;
        foreach ($orderedRows(self::INCOME_ORDER, 'income') as $incomeRow) {
            $this->inputRow($row, $incomeRow['label'], $incomeRow['values']);
        }
        $this->inputRow($row, 'UNKNOWN', $uncategorized('uncategorized-income')['values'] ?? []);
        $this->spareRows($row);
        $salesTotalRow = $this->sumRow($row, 'Total Sales', $salesStart, $row - 1);
        $row++;

        // --- Cost of Sales ---
        $this->sectionHeader($row, 'Cost of Sales');
        $cosStart = $row;
        foreach (array_filter($rows, fn ($r) => $r['section'] === 'cost_of_sales') as $cosRow) {
            $this->inputRow($row, $cosRow['label'], $cosRow['values']);
        }
        $this->spareRows($row);
        $cosTotalRow = $this->sumRow($row, 'Total Cost of Sales', $cosStart, $row - 1);
        $row++;

        // --- Gross Profit & margin ---
        $grossRow = $this->formulaRow($row, 'Gross Profit', fn (string $c) => "={$c}{$salesTotalRow}-{$c}{$cosTotalRow}", true);
        $this->styleTotal($grossRow);

        $marginRow = $row;
        $sheet->setCellValue("A{$marginRow}", 'Gross Margin %');
        foreach ($this->allValueCols() as $c) {
            $sheet->setCellValue("{$c}{$marginRow}", "=IF({$c}{$salesTotalRow}=0,\"\",{$c}{$grossRow}/{$c}{$salesTotalRow})");
        }
        $sheet->getStyle("B{$marginRow}:{$lastCol}{$marginRow}")->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
        $sheet->getStyle("A{$marginRow}:{$lastCol}{$marginRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFEFEF');
        $row += 2;

        // --- Expenses (excluding below-the-line categories like Pension Payments) ---
        $this->sectionHeader($row, 'Expenses');
        $expenseStart = $row;
        foreach ($orderedRows(self::EXPENSE_ORDER, 'expense') as $expenseRow) {
            $this->inputRow($row, $expenseRow['label'], $expenseRow['values']);
        }
        $this->inputRow($row, 'UNKNOWN', $uncategorized('uncategorized-expense')['values'] ?? []);
        $this->spareRows($row);
        $expenseTotalRow = $this->sumRow($row, 'Total Expenses', $expenseStart, $row - 1);
        $row++;

        // --- PAYE / Pension / VAT (below the line, per the reference workbook) ---
        $btlStart = $row;
        foreach (self::BELOW_THE_LINE as $label) {
            $btlRow = collect($rows)->first(fn ($r) => $r['label'] === $label && $r['section'] === 'expense');
            $thisRow = $this->inputRow($row, $label, $btlRow['values'] ?? []);
            if ($label === 'VAT Effect Payable/Refundable') {
                $sheet->getStyle("A{$thisRow}:{$lastCol}{$thisRow}")->getFont()->getColor()->setRGB('FF0000');
            }
        }
        $this->spareRows($row, 1);
        $btlEnd = $row - 1;
        $row++;

        // --- Cashflow Statement ---
        $this->sectionHeader($row, 'CASHFLOW STATEMENT:');
        $openingRow = $row;
        $fundingRow = $row + 1;
        $netRow = $row + 2;
        $closingRow = $row + 3;

        // Week 1 opens on the real bank balance; every later week opens on the prior week's
        // closing, so a change anywhere flows through to every following week.
        $sheet->setCellValue("A{$openingRow}", 'Opening Balance');
        $sheet->setCellValue("B{$openingRow}", round($totals['opening'][0] ?? 0, 2));
        for ($i = 1; $i < $this->weekCount; $i++) {
            $sheet->setCellValue("{$this->col($i)}{$openingRow}", "={$this->col($i - 1)}{$closingRow}");
        }
        $sheet->setCellValue("{$lastCol}{$openingRow}", "=B{$openingRow}");
        $this->money($openingRow);
        $row++;

        $sheet->setCellValue("A{$fundingRow}", 'Funding Capital');
        foreach ($this->weekCols() as $i => $c) {
            $funding = round((float) ($totals['funding'][$i] ?? 0), 2);
            if ($funding != 0) {
                $sheet->setCellValue("{$c}{$fundingRow}", $funding);
            }
        }
        $sheet->setCellValue("{$lastCol}{$fundingRow}", "=SUM(B{$fundingRow}:{$this->lastWeekCol}{$fundingRow})");
        $this->inputFill($fundingRow);
        $this->money($fundingRow);
        $row++;

        $this->formulaRow($row, 'Net Inflow (Outflow)', fn (string $c) => "={$c}{$grossRow}-{$c}{$expenseTotalRow}-SUM({$c}{$btlStart}:{$c}{$btlEnd})", true);

        $sheet->setCellValue("A{$closingRow}", 'Closing Balance');
        foreach ($this->weekCols() as $c) {
            $sheet->setCellValue("{$c}{$closingRow}", "={$c}{$openingRow}+{$c}{$fundingRow}+{$c}{$netRow}");
        }
        $sheet->setCellValue("{$lastCol}{$closingRow}", "={$this->lastWeekCol}{$closingRow}");
        $this->money($closingRow);
        $sheet->getStyle("A{$closingRow}:{$lastCol}{$closingRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$closingRow}:{$lastCol}{$closingRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');

        $sheet->getColumnDimension('A')->setWidth(36);
        foreach ($this->weekCols() as $c) {
            $sheet->getColumnDimension($c)->setWidth(13);
        }
        $sheet->getColumnDimension($lastCol)->setWidth(15);
        $sheet->freezePane('B'.($dateRow + 1));
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$dateRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $writer = new Xlsx($spreadsheet);
        $tempPath = storage_path('app/private/cashflow-export-'.uniqid().'.xlsx');
        $writer->save($tempPath);
        $contents = file_get_contents($tempPath);
        unlink($tempPath);

        return $contents;
    }

    private function sectionHeader(int &$row, string $label): void
    {
        $this->sheet->setCellValue("A{$row}", $label);
        $this->sheet->getStyle("A{$row}:{$this->totalCol}{$row}")->getFont()->setBold(true)->setUnderline(true);
        $row++;
    }

    /** An account row: weekly amounts are plain (editable) values, the TOTAL column is a SUM. */
    private function inputRow(int &$row, string $label, array $values): int
    {
        $this->sheet->setCellValue("A{$row}", $label);
        foreach ($this->weekCols() as $i => $c) {
            $value = round((float) ($values[$i] ?? 0), 2);
            if ($value != 0) {
                $this->sheet->setCellValue("{$c}{$row}", $value);
            }
        }
        $this->rowTotal($row);
        $this->money($row);

        return $row++;
    }

    /** Blank yellow rows inside a section's SUM range, ready for a new account. */
    private function spareRows(int &$row, int $count = self::SPARE_ROWS): void
    {
        for ($n = 0; $n < $count; $n++) {
            $this->rowTotal($row);
            $this->inputFill($row);
            $this->money($row);
            $row++;
        }
    }

    private function sumRow(int &$row, string $label, int $from, int $to): int
    {
        $thisRow = $this->formulaRow($row, $label, fn (string $c) => "=SUM({$c}{$from}:{$c}{$to})");
        $this->styleTotal($thisRow);

        return $thisRow;
    }

    /**
     * @param  callable(string $col): string  $formula
     * @param  bool  $totalAcrossWeeks  TOTAL column sums the row across weeks instead of applying $formula to it
     */
    private function formulaRow(int &$row, string $label, callable $formula, bool $totalAcrossWeeks = false): int
    {
        $this->sheet->setCellValue("A{$row}", $label);
        foreach ($this->weekCols() as $c) {
            $this->sheet->setCellValue("{$c}{$row}", $formula($c));
        }
        if ($totalAcrossWeeks) {
            $this->rowTotal($row);
        } else {
            $this->sheet->setCellValue("{$this->totalCol}{$row}", $formula($this->totalCol));
        }
        $this->money($row);

        return $row++;
    }

    private function rowTotal(int $row): void
    {
        $this->sheet->setCellValue("{$this->totalCol}{$row}", "=SUM(B{$row}:{$this->lastWeekCol}{$row})");
    }

    private function styleTotal(int $row): void
    {
        $range = "A{$row}:{$this->totalCol}{$row}";
        $this->sheet->getStyle($range)->getFont()->setBold(true)->setUnderline(true);
        $this->sheet->getStyle($range)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    }

    private function inputFill(int $row): void
    {
        $this->sheet->getStyle("A{$row}:{$this->lastWeekCol}{$row}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::INPUT_FILL);
    }

    private function money(int $row): void
    {
        $this->sheet->getStyle("B{$row}:{$this->totalCol}{$row}")->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
    }

    /** @return array<int, string> week index => column letter */
    private function weekCols(): array
    {
        return array_map(fn (int $i) => $this->col($i), range(0, $this->weekCount - 1));
    }

    /** @return array<int, string> week columns plus the TOTAL column */
    private function allValueCols(): array
    {
        return [...$this->weekCols(), $this->totalCol];
    }

    private function col(int $weekIndex): string
    {
        return Coordinate::stringFromColumnIndex(2 + $weekIndex);
    }
}
