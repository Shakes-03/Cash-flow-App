<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\CategorizationRule;

class CategorizationService
{
    public function categorize(Transaction $transaction): void
    {
        if ($transaction->category_locked) {
            return; // set by hand - see EditTransaction
        }

        // Longer, more specific keywords are checked first, so a broad catch-all
        // (e.g. "to ") never shadows a narrower rule regardless of creation order.
        $rules = CategorizationRule::all()->sortByDesc(fn ($rule) => strlen($rule->keyword))->values();

        $haystacks = array_filter([
            strtolower($transaction->description ?? ''),
            strtolower($transaction->reference ?? ''),
        ]);

        foreach ($rules as $rule) {
            $keyword = strtolower($rule->keyword);

            foreach ($haystacks as $haystack) {
                $isMatch = $rule->match_type === 'exact'
                    ? $haystack === $keyword
                    : str_contains($haystack, $keyword);

                if ($isMatch) {
                    $transaction->update(['category_id' => $rule->category_id]);
                    return;
                }
            }
        }
    }
}
