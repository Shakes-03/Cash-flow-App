<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        TextInput::make('name')->required(),
                        TextInput::make('customer_number'),
                        TextInput::make('email')->email(),
                    ])
                    ->required(),
                TextInput::make('invoice_number')
                    ->maxLength(255)
                    ->helperText('Must be unique - "INV-0012", "inv 0012" and "INV0012" count as the same invoice.')
                    // Same normalised number as the database's unique index, so the clash is a
                    // friendly form error rather than a crash on save.
                    ->rule(fn (?Invoice $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                        $existing = Invoice::findByNumber($value, $record?->id);
                        if ($existing) {
                            $fail("Invoice {$existing->invoice_number} already exists ({$existing->customer?->name}, {$existing->invoice_date->format('d M Y')}, £".number_format((float) $existing->amount, 2).').');
                        }
                    }),
                DatePicker::make('invoice_date')
                    ->required()
                    ->default(now()),
                TextInput::make('amount')
                    ->numeric()
                    ->prefix('£')
                    ->required()
                    // Without an invoice number, the same customer, date and amount is taken to be
                    // the same invoice entered twice (matches what the invoice import skips).
                    ->rule(fn (Get $get, ?Invoice $record) => function (string $attribute, mixed $value, Closure $fail) use ($get, $record) {
                        if (Invoice::normaliseNumber($get('invoice_number')) !== null || ! $get('customer_id') || ! $get('invoice_date')) {
                            return;
                        }

                        $duplicate = Invoice::query()
                            ->where('customer_id', $get('customer_id'))
                            ->whereDate('invoice_date', Carbon::parse($get('invoice_date'))->toDateString())
                            ->where('amount', round((float) $value, 2))
                            ->when($record, fn ($query) => $query->whereKeyNot($record->id))
                            ->first();

                        if ($duplicate) {
                            $fail('This customer already has an invoice for £'.number_format((float) $value, 2).' on '.$duplicate->invoice_date->format('d M Y').'. Give this one an invoice number if it really is a separate invoice.');
                        }
                    }),
                TextInput::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
