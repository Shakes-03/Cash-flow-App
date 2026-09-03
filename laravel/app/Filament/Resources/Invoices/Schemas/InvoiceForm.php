<?php

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

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
                    ->maxLength(255),
                DatePicker::make('invoice_date')
                    ->required()
                    ->default(now()),
                TextInput::make('amount')
                    ->numeric()
                    ->prefix('£')
                    ->required(),
                TextInput::make('description')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }
}
