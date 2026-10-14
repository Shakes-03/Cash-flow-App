<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\CategorizationService;
use App\Services\CustomerMatchingService;
use Illuminate\Console\Command;

class RecategorizeTransactions extends Command
{
    protected $signature = 'cashflow:recategorize';

    protected $description = 'Re-runs categorization and customer matching for every imported transaction, using the current rules';

    public function handle(CategorizationService $categorizer, CustomerMatchingService $customerMatcher): int
    {
        $transactions = Transaction::all();

        $this->withProgressBar($transactions, function (Transaction $transaction) use ($categorizer, $customerMatcher) {
            $categorizer->categorize($transaction);
            $customerMatcher->match($transaction);
        });

        $this->newLine(2);
        $this->info("Recategorized {$transactions->count()} transactions.");

        return self::SUCCESS;
    }
}
