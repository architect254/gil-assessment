<?php

namespace App\Filament\Resources\Items\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Item No')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true),
                TextInput::make('description')
                    ->label('Item Description')
                    ->required()
                    ->maxLength(255),
                TextInput::make('unit_price')
                    ->label('Unit Price')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.001)
                    ->default(0)
                    ->required(),
            ]);
    }
}
