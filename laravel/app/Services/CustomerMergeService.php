<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerMatchingRule;
use App\Models\Invoice;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class CustomerMergeService
{
    /**
     * Moves everything from $duplicate onto $keep (invoices, matched payments, matching rules,
     * opening balance) and deletes $duplicate. With $useDuplicateName the kept customer takes
     * the duplicate's name - e.g. the name Xero uses, so the next invoice import finds it.
     */
    public function merge(Customer $duplicate, Customer $keep, bool $useDuplicateName = false): Customer
    {
        if ($duplicate->is($keep)) {
            throw new \InvalidArgumentException('A customer cannot be merged into itself.');
        }

        return DB::transaction(function () use ($duplicate, $keep, $useDuplicateName) {
            Invoice::where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]);
            Transaction::where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]);
            CustomerMatchingRule::where('customer_id', $duplicate->id)->update(['customer_id' => $keep->id]);

            $name = $useDuplicateName ? $duplicate->name : $keep->name;
            $customerNumber = $keep->customer_number ?: $duplicate->customer_number;
            $email = $keep->email ?: $duplicate->email;

            $keep->fill([
                'opening_balance' => (float) $keep->opening_balance + (float) $duplicate->opening_balance,
                'opening_balance_date' => $keep->opening_balance_date ?? $duplicate->opening_balance_date,
            ]);

            // Deleted first: customer_number is unique, so it can only move once the duplicate is gone.
            $duplicate->delete();

            $keep->fill(['name' => $name, 'customer_number' => $customerNumber, 'email' => $email])->save();

            return $keep;
        });
    }
}
