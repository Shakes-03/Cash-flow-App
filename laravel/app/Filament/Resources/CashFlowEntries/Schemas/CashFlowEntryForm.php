<?php

namespace App\Filament\Resources\CashFlowEntries\Schemas;

use App\Models\Category;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CashFlowEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    /**
     * Shared with the "Add entry" action on the Cash Flow page.
     */
    public static function components(): array
    {
        return [
            Select::make('category_id')
                ->label('Account')
                ->options(fn () => Category::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->createOptionForm([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('type')
                        ->options([
                            'income' => 'Income (money in)',
                            'expense' => 'Expense (money out)',
                        ])
                        ->live()
                        ->required(),
                    Toggle::make('is_cost_of_sales')
                        ->label('Cost of Sales')
                        ->visible(fn ($get) => $get('type') === 'expense'),
                ])
                ->createOptionUsing(fn (array $data): int => Category::create($data)->getKey()),
            DatePicker::make('entry_date')
                ->label('Date')
                ->default(now())
                ->required()
                ->helperText('The amount is counted in the Monday-Sunday week containing this date.'),
            TextInput::make('amount')
                ->numeric()
                ->prefix('£')
                ->required()
                ->helperText('Money in for an income account, money out for an expense account. Use a negative amount to reverse.'),
            TextInput::make('description')
                ->maxLength(255),
        ];
    }
}
