<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashFlowService
{
    /**
     * Builds a Monday-Sunday weekly cash flow matrix over the given range.
     *
     * Returns an array with:
     *  - weeks: list of ['label', 'start', 'end']
     *  - rows: list of ['key', 'label', 'section' => 'income'|'expense', 'values' => [weekIndex => amount]]
     *  - totals: ['income' => [...], 'expenses' => [...], 'net' => [...], 'opening' => [...], 'closing' => [...]]
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
            $addRow('category-'.$category->id, $category->name, $category->type === 'income' ? 'income' : 'expense');
        }
        $addRow('uncategorized-income', 'Uncategorized Income', 'income');
        $addRow('uncategorized-expense', 'Uncategorized Expenses', 'expense');

        $totals = [
            'income' => array_fill(0, count($weeks), 0.0),
            'expenses' => array_fill(0, count($weeks), 0.0),
            'net' => array_fill(0, count($weeks), 0.0),
            'opening' => array_fill(0, count($weeks), 0.0),
            'closing' => array_fill(0, count($weeks), 0.0),
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

                if ($transaction->type === 'credit') {
                    $key = $category ? 'category-'.$category->id : 'uncategorized-income';
                    $totals['income'][$weekIndex] += $amount;
                } else {
                    $key = $category ? 'category-'.$category->id : 'uncategorized-expense';
                    $totals['expenses'][$weekIndex] += $amount;
                }

                $index = $rowIndex[$key] ?? $addRow($key, $category?->name ?? 'Uncategorized', $transaction->type === 'credit' ? 'income' : 'expense');
                $rows[$index]['values'][$weekIndex] = ($rows[$index]['values'][$weekIndex] ?? 0.0) + $amount;
            }

            $totals['net'][$weekIndex] = $totals['income'][$weekIndex] - $totals['expenses'][$weekIndex];
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
