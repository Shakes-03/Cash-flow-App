<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                    ])
                    ->live()
                    ->required(),
                Toggle::make('is_cost_of_sales')
                    ->label('Cost of Sales')
                    ->helperText('Show this expense under Cost of Sales (above Gross Profit) instead of Expenses on the Cash Flow report.')
                    ->visible(fn ($get) => $get('type') === 'expense'),
                ColorPicker::make('color'),
            ]);
    }
}
