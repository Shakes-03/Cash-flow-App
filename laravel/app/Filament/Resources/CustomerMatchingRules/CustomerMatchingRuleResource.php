<?php

namespace App\Filament\Resources\CustomerMatchingRules;

use App\Filament\Resources\CustomerMatchingRules\Pages;
use App\Filament\Resources\CustomerMatchingRules\Schemas\CustomerMatchingRuleForm;
use App\Filament\Resources\CustomerMatchingRules\Tables\CustomerMatchingRulesTable;
use App\Models\CustomerMatchingRule;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CustomerMatchingRuleResource extends Resource
{
    protected static ?string $model = CustomerMatchingRule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-funnel';

    protected static string | \UnitEnum | null $navigationGroup = 'Debtors';

    public static function form(Schema $schema): Schema
    {
        return CustomerMatchingRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerMatchingRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomerMatchingRules::route('/'),
            'create' => Pages\CreateCustomerMatchingRule::route('/create'),
            'edit' => Pages\EditCustomerMatchingRule::route('/{record}/edit'),
        ];
    }
}
