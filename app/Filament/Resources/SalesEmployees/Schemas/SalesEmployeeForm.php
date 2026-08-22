<?php

namespace App\Filament\Resources\SalesEmployees\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SalesEmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('phone')
                    ->tel()
                    ->maxLength(30),
            ]);
    }
}
