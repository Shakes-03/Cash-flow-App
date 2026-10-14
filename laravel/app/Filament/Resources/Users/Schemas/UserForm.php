<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                TextInput::make('password')
                    ->label(fn (string $operation) => $operation === 'create' ? 'Temporary password' : 'New password')
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'At least 12 characters. Send it to the person privately - they should change it under their profile (top-right menu) after first signing in.'
                        : 'Leave blank to keep the current password.')
                    ->password()
                    ->revealable()
                    ->rule(Password::min(12))
                    ->required(fn (string $operation) => $operation === 'create')
                    // Blank on edit = unchanged; the model's 'hashed' cast hashes whatever is saved.
                    ->dehydrated(fn (?string $state) => filled($state)),
            ]);
    }
}
