<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashFlowService
{
    /**
     * Builds a Monday-Sunday weekly cash flow matrix over the given range, laid out as
     * Sales -> Cost of Sales -> Gross Profit -> Expenses -> Net Cashflow, matching the
     * structure of Payshield's own "Income & Cash Flow Statement" workbook.
     *
     * Returns an array with:
     *  - weeks: list of ['label', 'start', 'end']
     *  - rows: list of ['key', 'label', 'section' => 'income'|'cost_of_sales'|'expense', 'values' => [weekIndex => amount]]
     *  - totals: ['income', 'costOfSales', 'gross', 'grossMargin', 'expenses', 'net', 'opening', 'closing'] => [...]
     */
    public function build(?Carbon $start = null, ?Carbon $end = null, int $defaultWeeks = 9): array
    {
        if (! $start || ! $end) {
            $bounds = Transaction::query()->selectRaw('min(transaction_date) as min_date, max(transaction_date) as max_date')->first();

            $end = $end ?: ($bounds?->max_date ? Carbon::parse($bounds->max_date) : now());
            $start = $start ?: ($bounds?->min_date
                ? Carbon::parse($bounds->min_date)
                : $end->copy()->subWeeks($defaultWeeks - 1));
        }

        $weekStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $weekEndBound = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $weekStart->copy();
        while ($cursor->lte($weekEndBound)) {
            $weeks[] = [
                'label' => $cursor->format('d M').' - '.$cursor->copy()->endOfWeek(Carbon::SUNDAY)->format('d M'),
                'start' => $cursor->copy(),
                'end' => $cursor->copy()->endOfWeek(Carbon::SUNDAY),
            ];
            $cursor->addWeek();
        }

        $categories = Category::all()->keyBy('id');

        $sectionFor = fn (?Category $category, string $transactionType): string => match (true) {
            $transactionType === 'credit' => 'income',
            $category?->is_cost_of_sales => 'cost_of_sales',
            default => 'expense',
        };

        $rows = [];
        $rowIndex = [];
        $addRow = function (string $key, string $label, string $section) use (&$rows, &$rowIndex) {
            if (! isset($rowIndex[$key])) {
                $rowIndex[$key] = count($rows);
                $rows[] = ['key' => $key, 'label' => $label, 'section' => $section, 'values' => []];
            }

            return $rowIndex[$key];
        };

        foreach ($categories as $category) {
            $section = $category->type === 'income' ? 'income' : ($category->is_cost_of_sales ? 'cost_of_sales' : 'expense');
            $addRow('category-'.$category->id, $category->name, $section);
        }
        $addRow('uncategorized-income', 'Uncategorized Income', 'income');
        $addRow('uncategorized-expense', 'Uncategorized Expenses', 'expense');

        $weekCount = count($weeks);
        $totals = [
            'income' => array_fill(0, $weekCount, 0.0),
            'costOfSales' => array_fill(0, $weekCount, 0.0),
            'gross' => array_fill(0, $weekCount, 0.0),
            'grossMargin' => array_fill(0, $weekCount, 0.0),
            'expenses' => array_fill(0, $weekCount, 0.0),
            'net' => array_fill(0, $weekCount, 0.0),
            'opening' => array_fill(0, $weekCount, 0.0),
            'closing' => array_fill(0, $weekCount, 0.0),
        ];

        $priorClosing = $this->balanceBefore($weekStart);

        foreach ($weeks as $weekIndex => $week) {
            /** @var Collection<int, Transaction> $transactions */
            $transactions = Transaction::query()
                ->whereBetween('transaction_date', [$week['start']->toDateString(), $week['end']->toDateString()])
                ->get();

            foreach ($transactions as $transaction) {
                $amount = (float) $transaction->amount;
                $category = $transaction->category_id ? $categories->get($transaction->category_id) : null;

                // A category only applies if its type agrees with the money's actual direction
                // (e.g. a stray credit keyword-matched into an expense category is treated as
                // uncategorized income instead, so row totals always foot to the section totals).
                $categoryMatchesDirection = $category && (($category->type === 'income') === ($transaction->type === 'credit'));
                if (! $categoryMatchesDirection) {
                    $category = null;
                }

                $section = $sectionFor($category, $transaction->type);

                match ($section) {
                    'income' => $totals['income'][$weekIndex] += $amount,
                    'cost_of_sales' => $totals['costOfSales'][$weekIndex] += $amount,
                    'expense' => $totals['expenses'][$weekIndex] += $amount,
                };

                $key = $category ? 'category-'.$category->id : ($section === 'income' ? 'uncategorized-income' : 'uncategorized-expense');
                $index = $rowIndex[$key] ?? $addRow($key, $category?->name ?? 'Uncategorized', $section);
                $rows[$index]['values'][$weekIndex] = ($rows[$index]['values'][$weekIndex] ?? 0.0) + $amount;
            }

            $totals['gross'][$weekIndex] = $totals['income'][$weekIndex] - $totals['costOfSales'][$weekIndex];
            $totals['grossMargin'][$weekIndex] = $totals['income'][$weekIndex] > 0
                ? $totals['gross'][$weekIndex] / $totals['income'][$weekIndex]
                : 0.0;
            $totals['net'][$weekIndex] = $totals['gross'][$weekIndex] - $totals['expenses'][$weekIndex];
            $totals['opening'][$weekIndex] = $priorClosing;

            $lastBalance = $transactions
                ->filter(fn (Transaction $t) => $t->balance_after !== null)
                ->sortBy([['transaction_date', 'asc'], ['id', 'asc']])
                ->last();

            $totals['closing'][$weekIndex] = $lastBalance
                ? (float) $lastBalance->balance_after
                : $totals['opening'][$weekIndex] + $totals['net'][$weekIndex];

            $priorClosing = $totals['closing'][$weekIndex];
        }

        return [
            'weeks' => $weeks,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    private function balanceBefore(Carbon $date): float
    {
        $transaction = Transaction::query()
            ->where('transaction_date', '<', $date->toDateString())
            ->whereNotNull('balance_after')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        return $transaction ? (float) $transaction->balance_after : 0.0;
    }
}
