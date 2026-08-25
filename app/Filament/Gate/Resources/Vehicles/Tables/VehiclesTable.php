<?php

namespace App\Filament\Gate\Resources\Vehicles\Tables;

use App\Models\Vehicle;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VehiclesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Registration No.')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('currentAssignment.driver.name')
                    ->label('Assigned Driver')
                    ->badge()
                    ->color('info')
                    ->placeholder('Unassigned'),
                TextColumn::make('status')
                    ->label('Location')
                    ->state(fn (Vehicle $record): string => $record->gateLogs()->where('status', 'in')->exists() ? 'On Premises' : 'Outside')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'On Premises' ? 'warning' : 'gray'),
                TextColumn::make('visits_count')
                    ->counts('gateLogs')
                    ->label('Total Visits')
                    ->alignEnd()
                    ->sortable(),
            ])
            ->stackedOnMobile()
            ->filters([
                TernaryFilter::make('on_premises')
                    ->label('On Premises')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('gateLogs', fn (Builder $q): Builder => $q->where('status', 'in')),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('gateLogs', fn (Builder $q): Builder => $q->where('status', 'in')),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ])
            ->defaultSort('number');
    }
}
