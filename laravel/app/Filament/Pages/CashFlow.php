<?php

namespace App\Filament\Pages;

use App\Services\CashFlowExcelExporter;
use App\Services\CashFlowService;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function (): StreamedResponse {
                    $xlsx = app(CashFlowExcelExporter::class)->toXlsx($this->getCashFlow());

                    return response()->streamDownload(function () use ($xlsx): void {
                        echo $xlsx;
                    }, 'cash-flow-'.now()->format('Y-m-d').'.xlsx', [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),
        ];
    }
}
