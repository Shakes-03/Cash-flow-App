<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Collection;

class DebtorsBookService
{
    /**
     * Invoiced/received/balance totals for every customer, for the Debtors Book overview.
     */
    public function summary(): Collection
    {
        return Customer::query()
            ->withSum('invoices as invoiced', 'amount')
            ->withSum(['transactions as received' => fn ($query) => $query->where('type', 'credit')], 'amount')
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) {
                $invoiced = (float) ($customer->invoiced ?? 0);
                $received = (float) ($customer->received ?? 0);

                return (object) [
                    'customer' => $customer,
                    'invoiced' => $invoiced,
                    'received' => $received,
                    'balance' => $invoiced - $received,
                ];
            });
    }

    /**
     * A single customer's dated ledger (invoices as debits, matched payments as credits)
     * with a running balance.
     */
    public function ledger(Customer $customer): Collection
    {
        $debits = $customer->invoices->map(fn ($invoice) => (object) [
            'date' => $invoice->invoice_date,
            'description' => 'Invoice '.($invoice->invoice_number ?: '#'.$invoice->id).($invoice->description ? ' — '.$invoice->description : ''),
            'debit' => (float) $invoice->amount,
            'credit' => 0.0,
        ]);

        $credits = $customer->transactions()
            ->where('type', 'credit')
            ->get()
            ->map(fn ($transaction) => (object) [
                'date' => $transaction->transaction_date,
                'description' => $transaction->description,
                'debit' => 0.0,
                'credit' => (float) $transaction->amount,
            ]);

        $running = 0.0;

        return $debits->concat($credits)
            ->sortBy('date')
            ->values()
            ->map(function ($entry) use (&$running) {
                $running += $entry->debit - $entry->credit;
                $entry->balance = $running;

                return $entry;
            });
    }
}
