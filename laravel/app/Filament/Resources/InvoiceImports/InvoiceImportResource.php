<?php

namespace App\Filament\Resources\InvoiceImports;

use App\Filament\Resources\InvoiceImports\Pages;
use App\Filament\Resources\InvoiceImports\Schemas\InvoiceImportForm;
use App\Filament\Resources\InvoiceImports\Tables\InvoiceImportsTable;
use App\Models\InvoiceImport;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class InvoiceImportResource extends Resource
{
    protected static ?string $model = InvoiceImport::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static string | \UnitEnum | null $navigationGroup = 'Debtors';

    public static function form(Schema $schema): Schema
    {
        return InvoiceImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoiceImportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoiceImports::route('/'),
            'create' => Pages\CreateInvoiceImport::route('/create'),
            'edit' => Pages\EditInvoiceImport::route('/{record}/edit'),
        ];
    }
}
