<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\CategorizationRule;

class CategorizationService
{
    public function categorize(Transaction $transaction): void
    {
        $rules = CategorizationRule::all();

        foreach ($rules as $rule) {
            $isMatch = $rule->match_type === 'exact'
                ? strtolower($transaction->description) === strtolower($rule->keyword)
                : str_contains(strtolower($transaction->description), strtolower($rule->keyword));

            if ($isMatch) {
                $transaction->update([
                    'category_id' => $rule->category_id
                ]);
                break;
            }
        }
    }
}
