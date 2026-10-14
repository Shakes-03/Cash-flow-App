<?php

namespace App\Filament\Resources\Customers\Tables;

use App\Models\Customer;
use App\Services\CustomerMergeService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('customer_number')
                    ->label('Customer #')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('invoices_count')
                    ->counts('invoices')
                    ->label('Invoices'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                // For a customer created twice under different names (e.g. an invoice import
                // where Xero's contact name didn't match the existing customer).
                Action::make('merge')
                    ->label('Merge')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('warning')
                    ->modalHeading(fn (Customer $record) => "Merge \"{$record->name}\" into another customer")
                    ->modalDescription('Its invoices, matched payments, matching rules and opening balance move to the customer you choose, and this one is deleted.')
                    ->schema(fn (Customer $record) => [
                        Select::make('keep_id')
                            ->label('Merge into')
                            ->options(Customer::query()->whereKeyNot($record->id)->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        Toggle::make('use_this_name')
                            ->label("Keep the name \"{$record->name}\"")
                            ->helperText('Switch on when this is the name Xero uses, so future invoice imports find the merged customer.'),
                    ])
                    ->action(function (Customer $record, array $data): void {
                        $kept = app(CustomerMergeService::class)->merge($record, Customer::findOrFail($data['keep_id']), (bool) ($data['use_this_name'] ?? false));

                        Notification::make()->title("Merged into {$kept->name}")->success()->send();
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
