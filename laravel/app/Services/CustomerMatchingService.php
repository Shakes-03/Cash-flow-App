<?php

namespace App\Services;

use App\Models\CustomerMatchingRule;
use App\Models\Transaction;

class CustomerMatchingService
{
    /**
     * Links an incoming payment to a customer, based on payer/description keyword rules.
     */
    public function match(Transaction $transaction): void
    {
        if ($transaction->type !== 'credit' || $transaction->customer_id !== null) {
            return;
        }

        $haystacks = array_filter([
            strtolower($transaction->payer ?? ''),
            strtolower($transaction->description ?? ''),
        ]);

        // Longer, more specific keywords are checked first (consistent with CategorizationService).
        $rules = CustomerMatchingRule::all()->sortByDesc(fn ($rule) => strlen($rule->keyword))->values();

        foreach ($rules as $rule) {
            $keyword = strtolower($rule->keyword);

            foreach ($haystacks as $haystack) {
                $isMatch = $rule->match_type === 'exact'
                    ? $haystack === $keyword
                    : str_contains($haystack, $keyword);

                if ($isMatch) {
                    $transaction->update(['customer_id' => $rule->customer_id]);
                    return;
                }
            }
        }
    }
}
