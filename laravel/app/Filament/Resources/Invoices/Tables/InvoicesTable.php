<?php

namespace App\Filament\Resources\Invoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_number')
                    ->searchable(),
                TextColumn::make('invoice_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('GBP')
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer')
                    ->relationship('customer', 'name')
                    ->searchable(),
                // Same customer, date and amount where at least one of the pair was typed in by hand
                // or has no number - usually one invoice entered twice, e.g. typed manually and then
                // imported from Xero. Two imported, numbered Xero invoices are never flagged: clients
                // billed per worker (TSSI, ProMedical) legitimately get many identical invoices a day.
                Filter::make('possible_duplicates')
                    ->label('Possible duplicates')
                    ->query(fn (Builder $query) => $query->whereExists(fn ($twin) => $twin
                        ->from('invoices as twin')
                        ->whereColumn('twin.customer_id', 'invoices.customer_id')
                        ->whereColumn('twin.invoice_date', 'invoices.invoice_date')
                        ->whereColumn('twin.amount', 'invoices.amount')
                        ->whereColumn('twin.id', '!=', 'invoices.id')
                        ->where(fn ($either) => $either
                            ->whereNull('twin.invoice_import_id')->orWhereNull('twin.invoice_number')
                            ->orWhereNull('invoices.invoice_import_id')->orWhereNull('invoices.invoice_number')))),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
