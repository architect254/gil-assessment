<?php

namespace App\Filament\Gate\Resources\Drivers\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DriverForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('id_number')
                    ->label('ID / Passport No.')
                    ->maxLength(50),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(30),
            ])
            ->columns(2);
    }
}
