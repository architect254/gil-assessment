<?php

namespace App\Filament\Gate\Resources\Drivers\Tables;

use App\Filament\Gate\Support\SortRecordsAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriversTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    TextColumn::make('name')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold),
                    Stack::make([
                        TextColumn::make('id_number')
                            ->label('ID / Passport No.')
                            ->searchable()
                            ->placeholder('—'),
                        TextColumn::make('phone')
                            ->label('Phone')
                            ->placeholder('—'),
                    ]),
                    TextColumn::make('vehicles_count')
                        ->counts('vehicles')
                        ->label('Vehicles'),
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
                    'name' => 'Name',
                ]),
            ])
            ->defaultSort('name');
    }
}
