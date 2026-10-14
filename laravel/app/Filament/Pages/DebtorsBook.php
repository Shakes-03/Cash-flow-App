<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\CustomerMatchingService;
use App\Services\DebtorsBookService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DebtorsBook extends Page
{
    protected string $view = 'filament.pages.debtors-book';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static string | \UnitEnum | null $navigationGroup = 'Debtors';

    protected static ?string $title = 'Debtors Book';

    public ?int $customerId = null;

    /** Blank = today. A past month-end lets the book be checked against Xero once that month is booked. */
    public ?string $asAt = null;

    /** The "As at" dropdown: '' = today, a month-end date, or 'custom' to type any date into $asAt. */
    public string $asAtChoice = '';

    public function updatedAsAtChoice(string $choice): void
    {
        if ($choice !== 'custom') {
            $this->asAt = $choice ?: null;
        }
    }

    /**
     * Month-ends from the last completed month back to the earliest bank data - the dates Xero's
     * Aged Receivables is usually compared at.
     *
     * @return array<string, string>
     */
    public function getAsAtOptions(): array
    {
        $earliest = Transaction::min('transaction_date');
        $monthEnd = now()->subMonthNoOverflow()->endOfMonth()->startOfDay();
        $stop = $earliest ? Carbon::parse($earliest)->endOfMonth()->startOfDay() : $monthEnd->copy()->subMonths(11);

        $options = [];
        while ($monthEnd->gte($stop)) {
            $options[$monthEnd->toDateString()] = $monthEnd->format('j M Y');
            $monthEnd = $monthEnd->subMonthNoOverflow()->endOfMonth()->startOfDay();
        }

        return $options;
    }

    public function getAsAtDate(): ?Carbon
    {
        try {
            return $this->asAt ? Carbon::parse($this->asAt)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getSummary(): Collection
    {
        return app(DebtorsBookService::class)->summary($this->getAsAtDate());
    }

    public function getCustomerOptions(): array
    {
        return Customer::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getSelectedCustomer(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    public function getLedger(): ?Collection
    {
        $customer = $this->getSelectedCustomer();

        return $customer ? app(DebtorsBookService::class)->ledger($customer, $this->getAsAtDate()) : null;
    }

    public function getAllocation(): ?object
    {
        $customer = $this->getSelectedCustomer();

        return $customer ? app(DebtorsBookService::class)->allocation($customer, $this->getAsAtDate()) : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            // Matching rules only run when a statement is imported, so a rule added later never
            // reaches payments already in the app - this applies the current rules to those.
            Action::make('rerunMatching')
                ->label('Re-run payment matching')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalDescription('Applies the current Customer Matching Rules to every incoming payment not yet linked to a customer. Payments already linked, or set by hand on the Transactions page, are left alone.')
                ->action(function (): void {
                    $matcher = app(CustomerMatchingService::class);
                    $unmatched = Transaction::query()->where('type', 'credit')->whereNull('customer_id')->get();

                    $unmatched->each(fn (Transaction $transaction) => $matcher->match($transaction));

                    $nowMatched = $unmatched->filter(fn (Transaction $transaction) => $transaction->customer_id !== null)->count();
                    $stillUnmatched = $unmatched->count() - $nowMatched;

                    Notification::make()
                        ->title($nowMatched ? "{$nowMatched} payment(s) linked to a customer" : 'No new matches')
                        ->body($stillUnmatched ? "{$stillUnmatched} incoming payment(s) still have no customer - add a Customer Matching Rule, or set the customer on the transaction." : null)
                        ->success()
                        ->send();
                }),
        ];
    }
}
