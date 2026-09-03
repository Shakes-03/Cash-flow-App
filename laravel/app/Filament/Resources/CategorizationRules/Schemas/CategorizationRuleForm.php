<?php

namespace App\Filament\Resources\CategorizationRules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategorizationRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('keyword')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Keyword to match against transaction descriptions (e.g. "CHECKERS", "UBER")'),
                Select::make('category_id')
                    ->relationship('category', 'name')
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
