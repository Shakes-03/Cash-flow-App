<?php

namespace App\Filament\Resources\CashFlowEntries;

use App\Filament\Resources\CashFlowEntries\Pages;
use App\Filament\Resources\CashFlowEntries\Schemas\CashFlowEntryForm;
use App\Filament\Resources\CashFlowEntries\Tables\CashFlowEntriesTable;
use App\Models\CashFlowEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CashFlowEntryResource extends Resource
{
    protected static ?string $model = CashFlowEntry::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-pencil-square';

    protected static string | \UnitEnum | null $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Manual Entries';

    protected static ?string $modelLabel = 'manual entry';

    public static function form(Schema $schema): Schema
    {
        return CashFlowEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashFlowEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashFlowEntries::route('/'),
            'create' => Pages\CreateCashFlowEntry::route('/create'),
            'edit' => Pages\EditCashFlowEntry::route('/{record}/edit'),
        ];
    }
}
