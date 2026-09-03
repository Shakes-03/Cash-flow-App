<?php

namespace App\Filament\Resources\BankImports;

use App\Filament\Resources\BankImports\Pages;
use App\Filament\Resources\BankImports\Schemas\BankImportForm;
use App\Filament\Resources\BankImports\Tables\BankImportsTable;
use App\Models\BankImport;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class BankImportResource extends Resource
{
    protected static ?string $model = BankImport::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string | \UnitEnum | null $navigationGroup = 'Bank';

    public static function form(Schema $schema): Schema
    {
        return BankImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankImportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankImports::route('/'),
            'create' => Pages\CreateBankImport::route('/create'),
            'edit' => Pages\EditBankImport::route('/{record}/edit'),
        ];
    }
}
