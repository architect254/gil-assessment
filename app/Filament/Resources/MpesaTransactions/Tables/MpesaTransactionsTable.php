<?php

namespace App\Filament\Resources\MpesaTransactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MpesaTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_id')
                    ->label('Trans ID')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('trans_amount')
                    ->label('Amount (KES)')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('msisdn')
                    ->label('Phone Number')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('bill_ref_number')
                    ->label('Bill Ref')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('Payer Name')
                    ->formatStateUsing(fn ($record): string => trim("{$record->first_name} {$record->last_name}") ?: '—')
                    ->searchable(),
                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->placeholder('Pay Bill')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('trans_time')
                    ->label('Trans Time')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                //
            ])
            ->defaultSort('id', 'desc');
    }
}
