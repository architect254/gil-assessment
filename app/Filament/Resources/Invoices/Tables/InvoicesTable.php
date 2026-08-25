<?php

namespace App\Filament\Resources\Invoices\Tables;

use App\Filament\Resources\Invoices\Actions\PayMpesaAction;
use App\Filament\Resources\Invoices\Actions\ApproveInvoiceAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('no')
                    ->label('No.')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('salesEmployee.name')
                    ->label('Sales Employee')
                    ->placeholder('—'),
                TextColumn::make('posting_date')
                    ->label('Posting Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('needs_approval')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'APPROVED' : 'PENDING APPROVAL')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-clock')
                    ->sortable(),
                TextColumn::make('total_after_discount')
                    ->label('Total')
                    ->numeric(decimalPlaces: 3)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
//                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                PayMpesaAction::make(),
                ApproveInvoiceAction::make(),
            ])
            ->toolbarActions([
                //
            ])
            ->defaultSort('no', 'desc');
    }
}
