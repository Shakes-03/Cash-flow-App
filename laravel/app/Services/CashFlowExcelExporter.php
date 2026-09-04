<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders a CashFlowService::build() result as a formatted workbook matching the layout
 * of Payshield's own "Income & Cash Flow Statement" tab: Sales Revenue -> Total Sales ->
 * Cost of Sales -> Total COS -> Gross Profit -> Expenses -> Total Expenses -> PAYE/Pension/VAT
 * -> Cashflow Statement.
 */
class CashFlowExcelExporter
{
    private const MONEY_FORMAT = '£#,##0.00;(£#,##0.00);;';

    private const PERCENT_FORMAT = '0.0%;(0.0%);;';

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

    public function toXlsx(array $cashFlow): string
    {
        $weeks = $cashFlow['weeks'];
        $rows = $cashFlow['rows'];
        $totals = $cashFlow['totals'];
        $weekCount = count($weeks);
        $weekIndexes = array_keys($weeks);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cash Flow');

        $lastCol = Coordinate::stringFromColumnIndex(2 + $weekCount); // A=Description, B..=weeks, last=TOTAL
        $firstWeekCol = Coordinate::stringFromColumnIndex(2);
        $lastWeekCol = Coordinate::stringFromColumnIndex(1 + $weekCount);

        $row = 1;
        $sheet->setCellValue("A{$row}", 'INCOME STATEMENT & CASHFLOW PROJECTIONS');
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $row += 2;

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

        $writeSectionHeader = function (string $label) use ($sheet, &$row, $lastCol) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->setUnderline(true);
            $row++;
        };

        $writeDetailRow = function (string $label, array $values) use ($sheet, &$row, $weekIndexes, $lastCol) {
            $sheet->setCellValue("A{$row}", $label);
            foreach ($weekIndexes as $i) {
                $sheet->setCellValue("{$this->col($i)}{$row}", $values[$i] ?? 0);
            }
            $sheet->setCellValue("{$lastCol}{$row}", array_sum($values));
            $sheet->getStyle($this->rangeForValues($row, $lastCol))->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
            $row++;
        };

        $writeTotalRow = function (string $label, array $values) use ($sheet, &$row, $weekIndexes, $lastCol, $writeDetailRow) {
            $writeDetailRow($label, $values);
            $totalRow = $row - 1;
            $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getFont()->setBold(true)->setUnderline(true);
            $sheet->getStyle("A{$totalRow}:{$lastCol}{$totalRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        };

        $rowsByLabel = collect($rows)->keyBy('label');
        $orderedRows = function (array $preferredOrder, string $section) use ($rowsByLabel, $rows): array {
            $ordered = [];
            foreach ($preferredOrder as $label) {
                if ($rowsByLabel->has($label)) {
                    $ordered[] = $rowsByLabel->get($label);
                }
            }
            $seen = collect($ordered)->pluck('label')->all();
            foreach ($rows as $candidate) {
                if ($candidate['section'] === $section
                    && ! in_array($candidate['label'], $seen, true)
                    && ! in_array($candidate['label'], self::BELOW_THE_LINE, true)
                    && $candidate['label'] !== 'Uncategorized Income'
                    && $candidate['label'] !== 'Uncategorized Expenses'
                ) {
                    $ordered[] = $candidate;
                }
            }

            return $ordered;
        };

        // --- Sales Revenue ---
        $writeSectionHeader('Sales Revenue:');
        foreach ($orderedRows(self::INCOME_ORDER, 'income') as $incomeRow) {
            $writeDetailRow($incomeRow['label'], $incomeRow['values']);
        }
        if ($rowsByLabel->has('Uncategorized Income')) {
            $writeDetailRow('UNKNOWN', $rowsByLabel->get('Uncategorized Income')['values']);
        }
        $writeTotalRow('Total Sales', $totals['income']);
        $row++;

        // --- Cost of Sales ---
        $writeSectionHeader('Cost of Sales');
        $costOfSalesRows = array_filter($rows, fn ($r) => $r['section'] === 'cost_of_sales');
        foreach ($costOfSalesRows as $cosRow) {
            $writeDetailRow($cosRow['label'], $cosRow['values']);
        }
        $writeTotalRow('Total Cost of Sales', $totals['costOfSales']);
        $row++;

        // --- Gross Profit ---
        $sheet->setCellValue("A{$row}", 'Gross Profit');
        foreach ($weekIndexes as $i) {
            $sheet->setCellValue("{$this->col($i)}{$row}", $totals['gross'][$i]);
        }
        $sheet->setCellValue("{$lastCol}{$row}", array_sum($totals['gross']));
        $sheet->getStyle($this->rangeForValues($row, $lastCol))->getNumberFormat()->setFormatCode(self::MONEY_FORMAT);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->setUnderline(true);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
        $row++;

        $sheet->setCellValue("A{$row}", null);
        foreach ($weekIndexes as $i) {
            $sheet->setCellValue("{$this->col($i)}{$row}", $totals['income'][$i] > 0 ? $totals['grossMargin'][$i] : null);
        }
        $sheet->getStyle($this->rangeForValues($row, $lastCol))->getNumberFormat()->setFormatCode(self::PERCENT_FORMAT);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFEFEF');
        $row += 2;

        // --- Expenses (excluding below-the-line categories like Pension Payments) ---
        $writeSectionHeader('Expenses');
        foreach ($orderedRows(self::EXPENSE_ORDER, 'expense') as $expenseRow) {
            $writeDetailRow($expenseRow['label'], $expenseRow['values']);
        }
        if ($rowsByLabel->has('Uncategorized Expenses')) {
            $writeDetailRow('UNKNOWN', $rowsByLabel->get('Uncategorized Expenses')['values']);
        }

        $belowTheLineValues = array_fill(0, $weekCount, 0.0);
        foreach (self::BELOW_THE_LINE as $label) {
            if ($rowsByLabel->has($label)) {
                foreach ($weekIndexes as $i) {
                    $belowTheLineValues[$i] += $rowsByLabel->get($label)['values'][$i] ?? 0;
                }
            }
        }
        $expensesExcludingBelowTheLine = array_map(
            fn ($i) => $totals['expenses'][$i] - $belowTheLineValues[$i],
            $weekIndexes
        );
        $writeTotalRow('Total Expenses', $expensesExcludingBelowTheLine);
        $row++;

        // --- PAYE / Pension / VAT (below the line, per the reference workbook) ---
        foreach (self::BELOW_THE_LINE as $label) {
            $values = $rowsByLabel->has($label) ? $rowsByLabel->get($label)['values'] : array_fill(0, $weekCount, 0.0);
            $thisRow = $row;
            $writeDetailRow($label, $values);
            if ($label === 'VAT Effect Payable/Refundable') {
                $sheet->getStyle("A{$thisRow}:{$lastCol}{$thisRow}")->getFont()->getColor()->setRGB('FF0000');
            }
        }
        $row++;

        // --- Cashflow Statement ---
        $writeSectionHeader('CASHFLOW STATEMENT:');
        $writeDetailRow('Opening Balance', $totals['opening']);
        $writeDetailRow('Funding Capital', array_fill(0, $weekCount, 0.0));
        $writeDetailRow('Net Inflow (Outflow)', $totals['net']);
        $closingRow = $row;
        $writeDetailRow('Closing Balance', $totals['closing']);
        $sheet->getStyle("A{$closingRow}:{$lastCol}{$closingRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$closingRow}:{$lastCol}{$closingRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C6EFCE');

        $sheet->getColumnDimension('A')->setWidth(32);
        foreach (range(0, $weekCount) as $i) {
            $sheet->getColumnDimension($this->col($i))->setWidth(13);
        }
        $sheet->getColumnDimension($lastCol)->setWidth(15);
        $sheet->freezePane('B'.($headerRow));
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$dateRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $writer = new Xlsx($spreadsheet);
        $tempPath = storage_path('app/private/cashflow-export-'.uniqid().'.xlsx');
        $writer->save($tempPath);
        $contents = file_get_contents($tempPath);
        unlink($tempPath);

        return $contents;
    }

    private function col(int $weekIndex): string
    {
        return Coordinate::stringFromColumnIndex(2 + $weekIndex);
    }

    private function rangeForValues(int $row, string $lastCol): string
    {
        return "B{$row}:{$lastCol}{$row}";
    }
}
