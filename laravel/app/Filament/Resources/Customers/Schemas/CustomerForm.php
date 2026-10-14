<?php

namespace App\Filament\Resources\Customers\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('customer_number')
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                TextInput::make('opening_balance')
                    ->label('Opening balance owed')
                    ->numeric()
                    ->prefix('£')
                    ->default(0)
                    ->helperText('What this customer owed you at the opening date (negative if they were in credit).'),
                DatePicker::make('opening_balance_date')
                    ->label('Opening balance as at')
                    ->helperText('The Debtors Book only counts invoices and payments after this date. Leave blank to count everything.'),
            ]);
    }
}
