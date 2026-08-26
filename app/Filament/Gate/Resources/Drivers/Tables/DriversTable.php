<?php

namespace App\Filament\Gate\Resources\Drivers\Tables;

use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Driver Name')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),
                TextColumn::make('id_number')
                    ->label('ID / Passport No.')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Phone')
                    ->icon('heroicon-m-phone')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('vehicles_count')
                    ->counts('vehicles')
                    ->label('Assigned Vehicles')
                    ->alignEnd(),
            ])
            ->stackedOnMobile()
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                //
            ])
            ->defaultSort('name');
    }
}
