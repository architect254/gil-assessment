<?php

namespace App\Filament\Gate\Resources\VehicleDrivers\Tables;

use App\Filament\Gate\Support\SortRecordsAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehicleDriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('vehicle.number')
                            ->label('Registration No.')
                            ->searchable()
                            ->sortable()
                            ->weight(FontWeight::Bold),
                        TextColumn::make('vehicle.description')
                            ->label('Vehicle Description')
                            ->placeholder('—'),
                    ]),
                    Stack::make([
                        TextColumn::make('driver.name')
                            ->label('Driver')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('driver.phone')
                            ->label('Phone')
                            ->placeholder('—'),
                    ]),
                    IconColumn::make('active')
                        ->boolean(),
                ])->from('md')->grow(false),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                SortRecordsAction::make([
                    'vehicle.number' => 'Registration No.',
                    'driver.name' => 'Driver',
                ]),
            ])
            ->defaultSort('vehicle_id');
    }
}
