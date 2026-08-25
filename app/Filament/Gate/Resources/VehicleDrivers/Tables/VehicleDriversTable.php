<?php

namespace App\Filament\Gate\Resources\VehicleDrivers\Tables;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VehicleDriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle.number')
                    ->label('Registration No.')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle.description')
                    ->label('Vehicle Description')
                    ->placeholder('—'),
                TextColumn::make('driver.name')
                    ->label('Driver Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('driver.phone')
                    ->label('Driver Phone')
                    ->placeholder('—'),
                IconColumn::make('active')
                    ->label('Active Assignment')
                    ->boolean(),
            ])
            ->stackedOnMobile()
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active Status'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ])
            ->defaultSort('vehicle_id');
    }
}
