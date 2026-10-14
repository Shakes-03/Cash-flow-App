<?php

namespace App\Services;

use App\Models\CashFlowEntry;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashFlowService
{
    /** @var array<string, Category|null> */
    private array $clientCategoryCache = [];

    /**
     * Builds a Monday-Sunday weekly cash flow matrix over the given range, laid out as
     * Sales -> Cost of Sales -> Gross Profit -> Expenses -> Net Cashflow, matching the
     * structure of Payshield's own "Income & Cash Flow Statement" workbook.
     *
     * Returns an array with:
     *  - weeks: list of ['label', 'start', 'end']
     *  - rows: list of ['key', 'label', 'section' => 'income'|'cost_of_sales'|'expense'|'funding', 'values' => [weekIndex => amount]]
     *    (funding rows are signed: money in positive, money out negative)
     *  - totals: ['income', 'costOfSales', 'gross', 'grossMargin', 'expenses', 'net', 'opening', 'closing'] => [...]
     */
    public function build(?Carbon $start = null, ?Carbon $end = null, int $defaultWeeks = 9): array
    {
        if (! $start || ! $end) {
            $bounds = Transaction::query()->selectRaw('min(transaction_date) as min_date, max(transaction_date) as max_date')->first();
            $entryBounds = CashFlowEntry::query()->selectRaw('min(entry_date) as min_date, max(entry_date) as max_date')->first();

            // Manual entries can sit outside the bank statement's range (e.g. a forecast for
            // a future month), so the report stretches to cover both.
            $minDate = collect([$bounds?->min_date, $entryBounds?->min_date])->filter()->min();
            $maxDate = collect([$bounds?->max_date, $entryBounds?->max_date])->filter()->max();

            $end = $end ?: ($maxDate ? Carbon::parse($maxDate) : now());
            $start = $start ?: ($minDate
                ? Carbon::parse($minDate)
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
            $section = $this->sectionFor($category);
            $addRow('category-'.$category->id.'-'.$section, $category->name, $section);
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
            'funding' => array_fill(0, $weekCount, 0.0),
        ];

        // Running total of manual entries' effect on cash, so the balance check below can
        // compare like with like (bank balances never include manual entries).
        $manualToDate = CashFlowEntry::query()
            ->with('category')
            ->whereDate('entry_date', '<', $weekStart->toDateString())
            ->get()
            ->sum(fn (CashFlowEntry $entry) => $entry->signedAmount());

        $priorClosing = $this->balanceBefore($weekStart) + $manualToDate;
        $balanceChecks = [];
        $priorDifference = 0.0;

        $entriesByWeek = CashFlowEntry::query()
            ->whereDate('entry_date', '>=', $weekStart->toDateString())
            ->whereDate('entry_date', '<=', $weekEndBound->toDateString())
            ->get()
            ->groupBy(fn (CashFlowEntry $entry) => (int) floor($weekStart->diffInDays($entry->entry_date) / 7));

        foreach ($weeks as $weekIndex => $week) {
            /** @var Collection<int, Transaction> $transactions */
            $transactions = Transaction::query()
                // whereDate, not whereBetween on date strings: transaction_date is stored as
                // "Y-m-d 00:00:00", which sorts after a bare "Y-m-d" end bound and would
                // silently drop every transaction dated on the week's last day (Sunday).
                ->whereDate('transaction_date', '>=', $week['start']->toDateString())
                ->whereDate('transaction_date', '<=', $week['end']->toDateString())
                ->with('pbePayment')
                ->get();

            foreach ($transactions as $transaction) {
                $rawAmount = (float) $transaction->amount;
                $category = $transaction->category_id ? $categories->get($transaction->category_id) : null;

                if ($category?->is_funding) {
                    // Loans / inter-company funding sit on the Funding Capital line, signed by direction.
                    $section = 'funding';
                    $amount = $transaction->type === 'credit' ? $rawAmount : -$rawAmount;
                } elseif ($category && $category->type === 'income' && $transaction->type === 'debit') {
                    // A debit against a client/income category is money paid back out to that
                    // client (a repayment, not a stray expense) - it becomes its own Cost of
                    // Sales line under the same client name rather than negative Sales income.
                    $section = 'cost_of_sales';
                    $amount = $rawAmount;
                } elseif ($category && $category->type === 'income') {
                    // Normal direction for a client/income category: a credit is real sales revenue.
                    $section = 'income';
                    $amount = $rawAmount;
                } elseif ($category) {
                    // Non-client expense/COS category: normal direction is a debit. A credit in
                    // the opposite direction is a refund/reversal, which reduces (nets against)
                    // this same row instead of being counted as unrelated income.
                    $section = $category->is_cost_of_sales ? 'cost_of_sales' : 'expense';
                    $amount = $transaction->type === 'debit' ? $rawAmount : -$rawAmount;
                } else {
                    $section = $transaction->type === 'credit' ? 'income' : 'expense';
                    $amount = $rawAmount;
                }

                match ($section) {
                    'income' => $totals['income'][$weekIndex] += $amount,
                    'cost_of_sales' => $totals['costOfSales'][$weekIndex] += $amount,
                    'expense' => $totals['expenses'][$weekIndex] += $amount,
                    'funding' => $totals['funding'][$weekIndex] += $amount,
                };

                // Cost-of-sales transactions matched to a payroll bureau (PBE) record are broken
                // out per client/agency instead of lumped into one "Employee Payroll" row, since
                // that's the only place the bank statement's worker payment can be tied to a client.
                $pbeClient = $section === 'cost_of_sales' ? $transaction->pbePayment?->client_name : null;
                $pbeCategory = $pbeClient ? $this->incomeCategoryForClient($pbeClient, $categories) : null;

                if ($pbeCategory) {
                    // Same row as that client's own repayment debits, labelled like its Sales line.
                    $key = 'category-'.$pbeCategory->id.'-cost_of_sales';
                    $label = $pbeCategory->name;
                } elseif ($pbeClient) {
                    $key = 'cos-client-'.$pbeClient;
                    $label = $pbeClient;
                } elseif ($category) {
                    $key = 'category-'.$category->id.'-'.$section;
                    $label = $category->name;
                } else {
                    $key = $section === 'income' ? 'uncategorized-income' : 'uncategorized-expense';
                    $label = 'Uncategorized';
                }

                $index = $rowIndex[$key] ?? $addRow($key, $label, $section);
                $rows[$index]['values'][$weekIndex] = ($rows[$index]['values'][$weekIndex] ?? 0.0) + $amount;
            }

            // Manual entries land on their account's own row, in the account's normal direction.
            foreach ($entriesByWeek->get($weekIndex, collect()) as $entry) {
                $category = $categories->get($entry->category_id);
                if (! $category) {
                    continue;
                }

                $section = $this->sectionFor($category);
                $amount = (float) $entry->amount;
                if ($section === 'funding') {
                    $amount = $category->type === 'income' ? $amount : -$amount;
                }

                match ($section) {
                    'income' => $totals['income'][$weekIndex] += $amount,
                    'cost_of_sales' => $totals['costOfSales'][$weekIndex] += $amount,
                    'expense' => $totals['expenses'][$weekIndex] += $amount,
                    'funding' => $totals['funding'][$weekIndex] += $amount,
                };

                $index = $rowIndex['category-'.$category->id.'-'.$section];
                $rows[$index]['values'][$weekIndex] = ($rows[$index]['values'][$weekIndex] ?? 0.0) + $amount;
                $manualToDate += $section === 'funding' ? $amount : ($category->type === 'income' ? $amount : -$amount);
            }

            $totals['gross'][$weekIndex] = $totals['income'][$weekIndex] - $totals['costOfSales'][$weekIndex];
            $totals['grossMargin'][$weekIndex] = $totals['income'][$weekIndex] > 0
                ? $totals['gross'][$weekIndex] / $totals['income'][$weekIndex]
                : 0.0;
            $totals['net'][$weekIndex] = $totals['gross'][$weekIndex] - $totals['expenses'][$weekIndex];
            $totals['opening'][$weekIndex] = $priorClosing;

            // Rolled forward rather than read off the bank's running balance, so a manual entry
            // in any week carries through every later week. Only the very first opening comes
            // from the bank; from there the bank data reconciles to its own balances exactly.
            $totals['closing'][$weekIndex] = $totals['opening'][$weekIndex] + $totals['funding'][$weekIndex] + $totals['net'][$weekIndex];

            $priorClosing = $totals['closing'][$weekIndex];

            // Bank check: the bank's own balance after its last transaction this week should
            // equal our rolled-forward closing (less manual entries). A difference that appears
            // or changes in a week points at that week: a duplicated or missing transaction, or
            // a gap between imported statements. It then carries into every later week.
            $lastBankTransaction = $transactions
                ->filter(fn (Transaction $t) => $t->balance_after !== null)
                ->sort(fn (Transaction $a, Transaction $b) => $this->newestFirst($a, $b))
                ->first();

            if ($lastBankTransaction) {
                $bankBalance = (float) $lastBankTransaction->balance_after;
                $reportBalance = $totals['closing'][$weekIndex] - $manualToDate;
                $difference = round($reportBalance - $bankBalance, 2);

                if (abs($difference - $priorDifference) > 0.005) {
                    $balanceChecks[] = [
                        'week' => $week['label'],
                        'report' => $reportBalance,
                        'bank' => $bankBalance,
                        'difference' => $difference,
                        'change' => round($difference - $priorDifference, 2),
                        'duplicates' => $this->likelyDuplicates($transactions),
                    ];
                }

                $priorDifference = $difference;
            }
        }

        return [
            'weeks' => $weeks,
            'rows' => $rows,
            'totals' => $totals,
            'balanceChecks' => $balanceChecks,
        ];
    }

    private function sectionFor(Category $category): string
    {
        return match (true) {
            $category->is_funding => 'funding',
            $category->type === 'income' => 'income',
            $category->is_cost_of_sales => 'cost_of_sales',
            default => 'expense',
        };
    }

    /**
     * Rows that repeat another row exactly - same date, direction, amount, description and
     * running balance. A real second payment would move the balance, so these are copies;
     * the earliest-imported row is kept and the rest are returned.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return list<Transaction>
     */
    private function likelyDuplicates(Collection $transactions): array
    {
        return $transactions
            ->filter(fn (Transaction $t) => $t->balance_after !== null)
            ->groupBy(fn (Transaction $t) => implode('|', [
                $t->transaction_date->toDateString(), $t->type, $t->amount, $t->balance_after, mb_strtolower(trim($t->description)),
            ]))
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->flatMap(fn (Collection $group) => $group->sortBy('id')->slice(1))
            ->values()
            ->all();
    }

    /**
     * Orders bank transactions newest first. Statements list newest first, so within one
     * import and one date a lower id is more recent; across imports on the same date the
     * later import is assumed to be the more recent statement.
     */
    private function newestFirst(Transaction $a, Transaction $b): int
    {
        return [$b->transaction_date->toDateString(), $b->bank_import_id, $a->id]
            <=> [$a->transaction_date->toDateString(), $a->bank_import_id, $b->id];
    }

    /**
     * The payroll bureau spells client names differently from the bank-side income categories
     * ("ProMedical Personnel Ltd" vs "ProMedical Personnel", "Commercial Services Kent Ltd" vs
     * "Hackney & Kent Commercial Services"). Matches on normalized names first, then on one
     * name's words being a subset of the other's.
     *
     * @param  Collection<int, Category>  $categories
     */
    private function incomeCategoryForClient(string $clientName, Collection $categories): ?Category
    {
        if (array_key_exists($clientName, $this->clientCategoryCache)) {
            return $this->clientCategoryCache[$clientName];
        }

        $tokens = function (string $name): array {
            $words = preg_split('/[^a-z0-9]+/', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);

            return array_values(array_unique(array_diff($words, ['ltd', 'limited', 'uk', 'the', 'and', 'plc'])));
        };

        $clientTokens = $tokens($clientName);
        $incomeCategories = $categories->where('type', 'income');

        $exact = $incomeCategories->first(function (Category $c) use ($tokens, $clientTokens) {
            $categoryTokens = $tokens($c->name);
            sort($categoryTokens);
            $sortedClient = $clientTokens;
            sort($sortedClient);

            return $categoryTokens === $sortedClient;
        });

        $subset = $exact ? null : $incomeCategories->filter(function (Category $c) use ($tokens, $clientTokens) {
            $categoryTokens = $tokens($c->name);

            return $clientTokens && $categoryTokens
                && (! array_diff($clientTokens, $categoryTokens) || ! array_diff($categoryTokens, $clientTokens));
        });

        return $this->clientCategoryCache[$clientName] = $exact ?? ($subset->count() === 1 ? $subset->first() : null);
    }

    private function balanceBefore(Carbon $date): float
    {
        $transaction = Transaction::query()
            ->where('transaction_date', '<', $date->toDateString())
            ->whereNotNull('balance_after')
            ->orderByDesc('transaction_date')
            ->orderByDesc('bank_import_id') // same ordering as newestFirst()
            ->orderBy('id')
            ->first();

        if ($transaction) {
            return (float) $transaction->balance_after;
        }

        // Nothing earlier on file: back the opening balance out of the oldest transaction
        // (latest id on the earliest date) instead of assuming the account started at zero.
        $oldest = Transaction::query()
            ->whereNotNull('balance_after')
            ->orderBy('transaction_date')
            ->orderBy('bank_import_id')
            ->orderByDesc('id')
            ->first();

        if (! $oldest) {
            return 0.0;
        }

        $signed = $oldest->type === 'credit' ? (float) $oldest->amount : -(float) $oldest->amount;

        return (float) $oldest->balance_after - $signed;
    }
}
