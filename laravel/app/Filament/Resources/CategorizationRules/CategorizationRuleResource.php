<?php

namespace App\Filament\Resources\CategorizationRules;

use App\Filament\Resources\CategorizationRules\CategorizationRuleResource\Pages;
use App\Models\CategorizationRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategorizationRuleResource extends Resource
{
    protected static ?string $model = CategorizationRule::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-funnel';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('keyword')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Keyword to match against transaction descriptions (e.g. "CHECKERS", "UBER")'),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required(),
                Forms\Components\Select::make('match_type')
                    ->options([
                        'contains' => 'Contains',
                        'exact' => 'Exact Match',
                    ])
                    ->default('contains')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('keyword')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('match_type')
                    ->badge(),
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
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
