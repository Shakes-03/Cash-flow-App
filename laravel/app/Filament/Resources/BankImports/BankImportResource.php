<?php

namespace App\Filament\Resources\BankImports;

use App\Filament\Resources\BankImports\BankImportResource\Pages;
use App\Models\BankImport;
use App\Services\BankStatementParserService;
use App\Services\CategorizationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BankImportResource extends Resource
{
    protected static ?string $model = BankImport::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-up-tray';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('csv_file')
                    ->label('Bank Statement CSV')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                    ->required()
                    ->columnSpanFull()
                    ->saveUploadedFileUsing(function ($file) {
                        // 1. Parse the uploaded CSV
                        $parser = new BankStatementParserService();
                        $bankImport = $parser->parse($file, auth()->id() ?? 1);

                        // 2. Auto-categorize each parsed transaction
                        $categorizer = new CategorizationService();
                        foreach ($bankImport->transactions as $transaction) {
                            $categorizer->categorize($transaction);
                        }

                        return $file->store('bank-imports', 'public');
                    }),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('filename')
                    ->searchable(),
                Tables\Columns\TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total_rows')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
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
