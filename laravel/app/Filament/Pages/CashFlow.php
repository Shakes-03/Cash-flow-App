<?php

namespace App\Filament\Pages;

use App\Services\CashFlowService;
use Filament\Pages\Page;

class CashFlow extends Page
{
    protected string $view = 'filament.pages.cash-flow';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | \UnitEnum | null $navigationGroup = 'Reports';

    protected static ?string $title = 'Cash Flow';

    /**
     * @return array{weeks: array, rows: array, totals: array}
     */
    public function getCashFlow(): array
    {
        return app(CashFlowService::class)->build();
    }
}
