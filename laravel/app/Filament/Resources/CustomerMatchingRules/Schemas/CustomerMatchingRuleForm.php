<?php

namespace App\Filament\Resources\CustomerMatchingRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CustomerMatchingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Keyword to match against the bank payer name or description (e.g. "TSSI RECRUITMENT")'),
                Select::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('match_type')
                    ->options([
                        'contains' => 'Contains',
                        'exact' => 'Exact Match',
                    ])
                    ->default('contains')
                    ->required(),
            ]);
    }
}
