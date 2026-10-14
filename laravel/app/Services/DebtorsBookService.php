<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A customer's balance is their opening balance (as at opening_balance_date) plus every invoice
 * dated after it, less every matched payment received after it. Without an opening date,
 * all invoices and payments count from an opening balance of zero.
 *
 * Payments are allocated to invoices oldest first (the opening balance being the oldest), the
 * usual convention when a payment doesn't say which invoice it settles. That gives each invoice
 * its paid/outstanding amount and age, without anyone having to allocate payments by hand.
 */
class DebtorsBookService
{
    /**
     * Opening/invoiced/received/balance totals for every customer, plus what is outstanding
     * by age (from invoice date). With $asAt, only invoices and payments up to that date count -
     * so the book can be compared with Xero's Aged Receivables for a month already booked.
     */
    public function summary(?Carbon $asAt = null): Collection
    {
        return Customer::query()
            ->with(['invoices', 'transactions' => fn ($query) => $query->where('type', 'credit')])
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) use ($asAt) {
                $allocation = $this->allocation($customer, $asAt);
                $ageing = ['current' => 0.0, '31_60' => 0.0, '61_90' => 0.0, 'over_90' => 0.0];
                foreach ($allocation->items as $item) {
                    if ($item->outstanding <= 0) {
                        continue;
                    }
                    $bucket = match (true) {
                        $item->age_days <= 30 => 'current',
                        $item->age_days <= 60 => '31_60',
                        $item->age_days <= 90 => '61_90',
                        default => 'over_90',
                    };
                    $ageing[$bucket] += $item->outstanding;
                }
                // Money received beyond everything invoiced (customer in credit) is shown as a
                // negative in the newest bucket, as Xero does, so the ageing adds up to the balance.
                $ageing['current'] -= $allocation->unallocated;

                return (object) [
                    'customer' => $customer,
                    'opening' => (float) $customer->opening_balance,
                    'invoiced' => $allocation->invoiced,
                    'received' => $allocation->received,
                    'balance' => $allocation->balance,
                    'ageing' => $ageing,
                ];
            });
    }

    /**
     * A single customer's dated ledger (opening balance, invoices as debits, matched payments
     * as credits) with a running balance.
     */
    public function ledger(Customer $customer, ?Carbon $asAt = null): Collection
    {
        $opening = collect();
        if ($customer->opening_balance_date || (float) $customer->opening_balance != 0) {
            $opening->push((object) [
                'date' => $customer->opening_balance_date,
                'description' => 'Opening balance',
                'debit' => max((float) $customer->opening_balance, 0.0),
                'credit' => max(-(float) $customer->opening_balance, 0.0),
            ]);
        }

        $debits = $this->invoices($customer, $asAt)->map(fn ($invoice) => (object) [
            'date' => $invoice->invoice_date,
            'description' => 'Invoice '.($invoice->invoice_number ?: '#'.$invoice->id).($invoice->description ? ' — '.$invoice->description : ''),
            'debit' => max((float) $invoice->amount, 0.0),
            'credit' => max(-(float) $invoice->amount, 0.0), // a negative invoice is a credit note
        ]);

        $credits = $this->payments($customer, $asAt)->map(fn (Transaction $transaction) => (object) [
            'date' => $transaction->transaction_date,
            'description' => $transaction->description,
            'debit' => 0.0,
            'credit' => (float) $transaction->amount,
        ]);

        $running = 0.0;

        return $opening->concat($debits->concat($credits)->sortBy('date')->values())
            ->values()
            ->map(function ($entry) use (&$running) {
                $running += $entry->debit - $entry->credit;
                $entry->balance = $running;

                return $entry;
            });
    }

    /**
     * Oldest-first allocation of the customer's payments (and credit notes) to their opening
     * balance and invoices.
     *
     * @return object{items: Collection, invoiced: float, received: float, balance: float, unallocated: float}
     */
    public function allocation(Customer $customer, ?Carbon $asAt = null): object
    {
        $today = ($asAt ?? now())->copy()->startOfDay();
        $invoices = $this->invoices($customer, $asAt);
        $payments = $this->payments($customer, $asAt);

        $items = collect();
        $opening = (float) $customer->opening_balance;
        if ($opening > 0) {
            $items->push((object) [
                'date' => $customer->opening_balance_date,
                'label' => 'Opening balance',
                'description' => null,
                'amount' => $opening,
            ]);
        }
        foreach ($invoices->where('amount', '>', 0) as $invoice) {
            $items->push((object) [
                'date' => $invoice->invoice_date,
                'label' => $invoice->invoice_number ?: 'Invoice #'.$invoice->id,
                'description' => $invoice->description,
                'amount' => (float) $invoice->amount,
            ]);
        }

        $received = (float) $payments->sum('amount');
        // Money available to settle invoices: payments, credit notes and any opening credit.
        $available = $received
            + (float) -$invoices->where('amount', '<', 0)->sum('amount')
            + max(-$opening, 0.0);

        foreach ($items as $item) {
            $item->paid = round(min($item->amount, $available), 2);
            $available = max($available - $item->paid, 0.0);
            $item->outstanding = round($item->amount - $item->paid, 2);
            $item->status = match (true) {
                $item->outstanding <= 0 => 'paid',
                $item->paid > 0 => 'part paid',
                default => 'unpaid',
            };
            $item->age_days = $item->date ? (int) Carbon::parse($item->date)->startOfDay()->diffInDays($today) : 0;
        }

        $invoiced = (float) $invoices->sum('amount');

        return (object) [
            'items' => $items,
            'invoiced' => $invoiced,
            'received' => $received,
            'balance' => round($opening + $invoiced - $received, 2),
            'unallocated' => round($available, 2), // paid more than invoiced - customer is in credit
        ];
    }

    /** Invoices after the opening balance date (and up to $asAt), oldest first. */
    private function invoices(Customer $customer, ?Carbon $asAt = null): Collection
    {
        return $customer->invoices
            ->when($customer->opening_balance_date, fn ($invoices, $from) => $invoices->filter(fn ($invoice) => $invoice->invoice_date->gt($from)))
            ->when($asAt, fn ($invoices, $to) => $invoices->filter(fn ($invoice) => $invoice->invoice_date->lte($to->copy()->endOfDay())))
            ->sortBy([['invoice_date', 'asc'], ['id', 'asc']])
            ->values();
    }

    /** Matched incoming payments after the opening balance date (and up to $asAt), oldest first. */
    private function payments(Customer $customer, ?Carbon $asAt = null): Collection
    {
        return ($customer->relationLoaded('transactions') ? $customer->transactions : $customer->transactions()->get())
            ->where('type', 'credit')
            ->when($customer->opening_balance_date, fn ($payments, $from) => $payments->filter(fn ($payment) => $payment->transaction_date->gt($from)))
            ->when($asAt, fn ($payments, $to) => $payments->filter(fn ($payment) => $payment->transaction_date->lte($to->copy()->endOfDay())))
            ->sortBy([['transaction_date', 'asc'], ['id', 'asc']])
            ->values();
    }
}
