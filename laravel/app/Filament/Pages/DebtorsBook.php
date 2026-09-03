<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Services\DebtorsBookService;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class DebtorsBook extends Page
{
    protected string $view = 'filament.pages.debtors-book';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static string | \UnitEnum | null $navigationGroup = 'Debtors';

    protected static ?string $title = 'Debtors Book';

    public ?int $customerId = null;

    public function getSummary(): Collection
    {
        return app(DebtorsBookService::class)->summary();
    }

    public function getCustomerOptions(): array
    {
        return Customer::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function getLedger(): ?Collection
    {
        if (! $this->customerId) {
            return null;
        }

        $customer = Customer::find($this->customerId);

        return $customer ? app(DebtorsBookService::class)->ledger($customer) : null;
    }
}
