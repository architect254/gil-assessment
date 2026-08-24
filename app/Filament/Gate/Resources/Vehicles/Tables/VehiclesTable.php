<?php

namespace App\Filament\Gate\Resources\Vehicles\Tables;

use App\Filament\Gate\Support\SortRecordsAction;
use Filament\Actions\CreateAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('number')
                            ->label('Registration No.')
                            ->searchable()
                            ->sortable()
                            ->weight(FontWeight::Bold),
                        TextColumn::make('currentAssignment.driver.name')
                            ->label('Assigned Driver')
                            ->badge()
                            ->color('info')
                            ->placeholder('Unassigned'),
                        TextColumn::make('on_premises')
                            ->state(fn ($record) => $record->gateLogs()->where('status', 'in')->exists())
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'On Premises' : '')
                            ->color('warning'),
                    ]),
                    TextColumn::make('description')
                        ->label('Description')
                        ->searchable()
                        ->sortable()
                        ->placeholder('—'),
                    TextColumn::make('visits_count')
                        ->counts('gateLogs')
                        ->label('Total Visits')
                        ->alignEnd(),
                ])
                    ->from('md'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                SortRecordsAction::make([
                    'number' => 'Registration No.',
                    'description' => 'Description',
                ]),
                CreateAction::make(),
            ])
            ->defaultSort('number');
    }
}
