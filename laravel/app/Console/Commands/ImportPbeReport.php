<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PbePaymentMatchingService;
use App\Services\PbeReportParserService;
use Illuminate\Console\Command;

class ImportPbeReport extends Command
{
    protected $signature = 'cashflow:import-pbe {path : Path to the PBE Master Report / Verify Payments By Employee .xlsx file}';

    protected $description = 'Imports a payroll bureau (PBE) report and matches its payments to bank transactions';

    public function handle(PbeReportParserService $parser, PbePaymentMatchingService $matcher): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $userId = User::query()->value('id');

        $this->info('Parsing '.basename($path).'...');
        $pbeImport = $parser->parse($path, basename($path), $userId);
        $this->info("Imported {$pbeImport->total_rows} employee payment rows.");

        $this->info('Matching to bank transactions...');
        $result = $matcher->matchAll();
        $this->info("Matched: {$result['matched']}, unmatched: {$result['unmatched']}.");

        return self::SUCCESS;
    }
}
