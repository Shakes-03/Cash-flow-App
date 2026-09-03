<?php

namespace App\Filament\Resources\Transactions\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('transaction_date')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('payer')
                    ->maxLength(255),
                TextInput::make('amount')
                    ->numeric()
                    ->prefix('£')
                    ->required(),
                Select::make('type')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ])
                    ->required(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Only relevant for credit (receipt) transactions.'),
                TextInput::make('reference')
                    ->maxLength(255),
            ]);
    }
}
