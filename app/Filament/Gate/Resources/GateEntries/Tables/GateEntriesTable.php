<?php

namespace App\Filament\Gate\Resources\GateEntries\Tables;

use App\Filament\Gate\Support\SortRecordsAction;
use App\Models\GateLog;
use Filament\Actions\Action;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class GateEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('vehicle_number')
                            ->label('Vehicle No.')
                            ->searchable()
                            ->sortable()
                            ->weight(FontWeight::Bold),
                        TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === GateLog::STATUS_IN ? 'On Premises' : 'Exited')
                            ->color(fn (string $state): string => $state === GateLog::STATUS_IN ? 'warning' : 'success'),
                    ]),
                    Stack::make([
                        TextColumn::make('driver_name')
                            ->label('Driver')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('driver_phone')
                            ->label('Phone')
                            ->placeholder('—'),
                    ]),
                    Stack::make([
                        TextColumn::make('gated_in_at')
                            ->label('Gate In')
                            ->dateTime('d/m/Y H:i')
                            ->sortable(),
                        TextColumn::make('gated_out_at')
                            ->label('Gate Out')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('On premises')
                            ->sortable(),
                    ])->grow(false),
                ])
                    ->from('md'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('registerExit')
                    ->label('Register Exit')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->visible(fn (GateLog $record): bool => $record->isOpen())
                    ->requiresConfirmation()
                    ->modalDescription(fn (GateLog $record): string => "Register exit for vehicle {$record->vehicle_number} ({$record->driver_name})?")
                    ->action(function (GateLog $record): void {
                        $record->update([
                            'gated_out_at' => now(),
                            'gated_out_by' => Auth::id(),
                            'status' => GateLog::STATUS_OUT,
                        ]);
                    })
                    ->successNotificationTitle('Exit registered'),
            ])
            ->toolbarActions([
                SortRecordsAction::make([
                    'vehicle_number' => 'Vehicle No.',
                    'driver_name' => 'Driver',
                    'gated_in_at' => 'Gate In',
                    'gated_out_at' => 'Gate Out',
                    'status' => 'Status',
                ]),
            ])
            ->defaultSort('gated_in_at', 'desc');
    }
}
