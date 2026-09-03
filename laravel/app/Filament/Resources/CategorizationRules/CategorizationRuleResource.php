<?php

namespace App\Filament\Resources\CategorizationRules;

use App\Filament\Resources\CategorizationRules\Pages;
use App\Filament\Resources\CategorizationRules\Schemas\CategorizationRuleForm;
use App\Filament\Resources\CategorizationRules\Tables\CategorizationRulesTable;
use App\Models\CategorizationRule;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CategorizationRuleResource extends Resource
{
    protected static ?string $model = CategorizationRule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-funnel';

    protected static string | \UnitEnum | null $navigationGroup = 'Bank';

    public static function form(Schema $schema): Schema
    {
        return CategorizationRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategorizationRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategorizationRules::route('/'),
            'create' => Pages\CreateCategorizationRule::route('/create'),
            'edit' => Pages\EditCategorizationRule::route('/{record}/edit'),
        ];
    }
}
