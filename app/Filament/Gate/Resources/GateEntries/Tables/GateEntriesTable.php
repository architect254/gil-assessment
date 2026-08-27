<?php

namespace App\Filament\Gate\Resources\GateEntries\Tables;

use App\Models\GateLog;
use App\Services\RegisterGateExit;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GateEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle_number')
                    ->label('Vehicle No.')
                    ->weight(FontWeight::Bold)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === GateLog::STATUS_IN ? 'On Premises' : 'Exited')
                    ->color(fn (string $state): string => $state === GateLog::STATUS_IN ? 'warning' : 'success'),
                TextColumn::make('driver_name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('driver_phone')
                    ->label('Phone')
                    ->icon('heroicon-m-phone')
                    ->placeholder('—'),
                TextColumn::make('gated_in_at')
                    ->label('Gate In')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('gatedInUser.name')
                    ->label('Gated In By')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gated_out_at')
                    ->label('Gate Out')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('On premises')
                    ->sortable(),
                TextColumn::make('gatedOutUser.name')
                    ->label('Gated Out By')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        GateLog::STATUS_IN => 'On Premises',
                        GateLog::STATUS_OUT => 'Exited',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('registerExit')
                    ->label('Register Exit')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->color('danger')
                    ->visible(fn (GateLog $record): bool => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalDescription(fn (GateLog $record): string => "Register exit for vehicle {$record->vehicle_number} ({$record->driver_name})?")
                    ->action(function (GateLog $record): void {
                        RegisterGateExit::forLog($record, Auth::id());
                    })
                    ->successNotificationTitle('Exit registered'),
            ])
            ->toolbarActions([
                //
            ])
            ->defaultSort('gated_in_at', 'desc');
    }
}
