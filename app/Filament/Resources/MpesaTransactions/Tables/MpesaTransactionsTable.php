<?php

namespace App\Filament\Resources\MpesaTransactions\Tables;

use App\Models\MpesaTransaction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MpesaTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        MpesaTransaction::STATUS_SUCCESS => 'success',
                        MpesaTransaction::STATUS_FAILED => 'danger',
                        MpesaTransaction::STATUS_CANCELLED => 'warning',
                        MpesaTransaction::STATUS_TIMEOUT => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        MpesaTransaction::STATUS_SUCCESS => 'Success',
                        MpesaTransaction::STATUS_FAILED => 'Failed',
                        MpesaTransaction::STATUS_CANCELLED => 'Cancelled',
                        MpesaTransaction::STATUS_TIMEOUT => 'Timeout',
                        default => 'Pending',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transaction_id')
                    ->label('Trans ID')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—')
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
                TextColumn::make('result_code')
                    ->label('Result Code')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('result_desc')
                    ->label('Result Description')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn ($record): string => $record->result_desc ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Filter::make('receipt_pending')
                    ->label(fn () => 'Receipt pending (' . MpesaTransaction::isReceiptPending()->count() . ')')
                    ->query(fn (Builder $query) => MpesaTransaction::isReceiptPending()),
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
