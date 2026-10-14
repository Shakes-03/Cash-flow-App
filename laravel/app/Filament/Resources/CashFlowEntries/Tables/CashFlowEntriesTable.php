<?php

namespace App\Filament\Resources\CashFlowEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CashFlowEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('entry_date', 'desc')
            ->columns([
                TextColumn::make('entry_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Account')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'income' ? 'success' : 'danger'),
                TextColumn::make('amount')
                    ->money('GBP')
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Added by')
                    ->toggleable(isToggledHiddenByDefault: true),
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
