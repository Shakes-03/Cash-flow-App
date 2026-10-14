<?php

namespace App\Services;

use App\Models\PbePayment;
use App\Models\Transaction;

/**
 * Links each PbePayment (a payroll bureau record of what an employee should be paid)
 * to the bank Transaction that actually paid them, so Cost of Sales can be attributed
 * to the client/agency the worker was placed with instead of one lump sum.
 *
 * The bank statement only has a worker's name and a debit amount ("To Emmanuel Mugweni",
 * -497.43) with no client reference, so matching is done by: same net amount (Total Pay),
 * surname appearing in the transaction description, and the transaction falling within a
 * few days of the payroll bureau's Bank Date.
 */
class PbePaymentMatchingService
{
    private const DATE_WINDOW_DAYS = 4;

    private const AMOUNT_TOLERANCE = 0.01;

    public function matchAll(): array
    {
        $matched = 0;
        $unmatched = 0;

        // Loaded up front rather than via chunk(): chunk() paginates by re-running the
        // whereNull('transaction_id') query each page, so rows matched (and thus updated)
        // in an earlier page shift the offset and cause later rows to be silently skipped.
        $payments = PbePayment::query()
            ->whereNull('transaction_id')
            ->whereNotNull('last_name')
            ->orderBy('pay_date')
            ->get();

        foreach ($payments as $payment) {
            if ($this->matchOne($payment)) {
                $matched++;
            } else {
                $unmatched++;
            }
        }

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    private function matchOne(PbePayment $payment): bool
    {
        $anchorDate = $payment->bank_date ?? $payment->pay_date;
        $windowStart = $anchorDate->copy()->subDays(self::DATE_WINDOW_DAYS);
        $windowEnd = $anchorDate->copy()->addDays(self::DATE_WINDOW_DAYS);

        $candidates = Transaction::query()
            ->where('type', 'debit')
            ->whereDate('transaction_date', '>=', $windowStart->toDateString())
            ->whereDate('transaction_date', '<=', $windowEnd->toDateString())
            ->whereBetween('amount', [$payment->total_pay - self::AMOUNT_TOLERANCE, $payment->total_pay + self::AMOUNT_TOLERANCE])
            ->whereDoesntHave('pbePayment')
            ->get();

        if ($candidates->isEmpty()) {
            return false;
        }

        $surname = mb_strtolower($payment->last_name);
        $firstName = mb_strtolower((string) $payment->first_name);

        $byName = $candidates->filter(
            fn (Transaction $t) => str_contains(mb_strtolower($t->description ?? ''), $surname)
        );

        $chosen = match (true) {
            $byName->count() === 1 => $byName->first(),
            $byName->count() > 1 => $byName->first(fn (Transaction $t) => str_contains(mb_strtolower($t->description ?? ''), $firstName)) ?? $byName->first(),
            $candidates->count() === 1 => $candidates->first(),
            default => null,
        };

        if (! $chosen) {
            return false;
        }

        $payment->update(['transaction_id' => $chosen->id]);

        return true;
    }
}
