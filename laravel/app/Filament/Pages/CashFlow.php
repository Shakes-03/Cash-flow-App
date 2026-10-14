<?php

namespace App\Filament\Pages;

use App\Filament\Resources\CashFlowEntries\Schemas\CashFlowEntryForm;
use App\Models\CashFlowEntry;
use App\Models\Transaction;
use App\Services\CashFlowExcelExporter;
use App\Services\CashFlowService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

    /**
     * Deletes a bank row the balance check identified as an exact copy of another. Re-checks
     * that its twin still exists first, so it can never remove the only copy.
     */
    public function deleteDuplicateAction(): Action
    {
        return Action::make('deleteDuplicate')
            ->label('Delete duplicate')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->size('sm')
            ->requiresConfirmation()
            ->modalHeading('Delete duplicated bank transaction?')
            ->modalDescription(function (array $arguments): string {
                $t = Transaction::find($arguments['id'] ?? null);

                return $t
                    ? "#{$t->id}: {$t->description}, £".number_format((float) $t->amount, 2)." on {$t->transaction_date->format('d M Y')}. The identical original row is kept."
                    : 'This transaction no longer exists.';
            })
            ->action(function (array $arguments): void {
                $duplicate = Transaction::find($arguments['id'] ?? null);

                $original = $duplicate ? Transaction::query()
                    ->whereKeyNot($duplicate->id)
                    ->where('id', '<', $duplicate->id)
                    ->whereDate('transaction_date', $duplicate->transaction_date->toDateString())
                    ->where('type', $duplicate->type)
                    ->where('amount', $duplicate->amount)
                    ->where('balance_after', $duplicate->balance_after)
                    ->where('description', $duplicate->description)
                    ->exists() : false;

                if (! $original) {
                    Notification::make()->title('Not deleted - no identical original found')->danger()->send();

                    return;
                }

                $duplicate->delete();

                Notification::make()->title('Duplicate deleted - cash flow updated')->success()->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addEntry')
                ->label('Add entry')
                ->icon('heroicon-o-plus')
                ->modalHeading('Add a manual cash flow entry')
                ->schema(CashFlowEntryForm::components())
                ->action(function (array $data): void {
                    CashFlowEntry::create($data + ['user_id' => auth()->id()]);

                    Notification::make()->title('Entry added - cash flow updated')->success()->send();
                }),
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
