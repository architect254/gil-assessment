<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Support\Carbon;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                // ── Bill To & Invoice Details ───────────────────────────
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([

                        Section::make('Bill To')
                            ->description(fn (?Invoice $record) => $record?->customer?->name)
                            ->icon('heroicon-o-building-office-2')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('customer.name')
                                    ->label('Customer')
                                    ->weight('bold')
                                    ->size('lg'),
                                TextEntry::make('customer.address')
                                    ->label('Address')
                                    ->placeholder('—')
                                    ->icon('heroicon-o-map-pin')
                                    ->color('gray'),
                                TextEntry::make('customer.phone')
                                    ->label('Phone')
                                    ->placeholder('—')
                                    ->icon('heroicon-o-phone')
                                    ->color('gray'),
                                TextEntry::make('customer.email')
                                    ->label('Email')
                                    ->placeholder('—')
                                    ->icon('heroicon-o-envelope')
                                    ->color('gray'),
                            ]),

                        Section::make(fn (?Invoice $record) => 'Invoice #' . ($record?->no ?? ''))
                            ->description(fn (?Invoice $record) => $record?->posting_date
                                ? 'Posted ' . Carbon::parse($record->posting_date)->format('M j, Y')
                                : null)
                            ->icon('heroicon-o-document-text')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('needs_approval')
                                    ->label('Status')
                                    ->badge()
                                    ->size('lg')
                                    ->icon(fn (bool $state) => !$state
                                        ? 'heroicon-o-check-circle'
                                        : 'heroicon-o-clock')
                                    ->formatStateUsing(fn (bool $state): string => !$state
                                        ? 'Approved'
                                        : 'Pending Approval')
                                    ->color(fn (bool $state): string => !$state
                                        ? 'success'
                                        : 'warning')
                                    ->columnSpan(1),

                                TextEntry::make('no')
                                    ->label('Document No.')
                                    ->badge()
                                    ->color('gray')
                                    ->columnSpan(1),

                                TextEntry::make('salesEmployee.name')
                                    ->label('Sales Employee')
                                    ->placeholder('—')
                                    ->columnSpan(1),

                                TextEntry::make('createdBy.name')
                                    ->label('Created By')
                                    ->placeholder('—')
                                    ->color('gray')
                                    ->columnSpan(1),
                            ]),
                    ]),

                // ── Line Items + Totals ────────────────────────────────
                        Section::make('Line Items')
                            ->icon('heroicon-o-list-bullet')
                            ->columnSpanFull()
                            ->schema([
                                RepeatableEntry::make('lines')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('line_no')
                                            ->label('#'),
                                        TextEntry::make('item_code')
                                            ->label('Item Code')
                                            ->placeholder('—'),
                                        TextEntry::make('item_description')
                                            ->label('Description'),
                                        TextEntry::make('quantity')
                                            ->label('Qty')
                                            ->numeric(decimalPlaces: 2)
                                            ->alignEnd(),
                                        TextEntry::make('price_before_discount')
                                            ->label('Unit Price')
                                            ->numeric(decimalPlaces: 3)
                                            ->alignEnd(),
                                        TextEntry::make('discount')
                                            ->label('Disc. %')
                                            ->suffix('%')
                                            ->badge()
                                            ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                                            ->alignEnd(),
                                        TextEntry::make('price_after_discount')
                                            ->label('Net Price')
                                            ->numeric(decimalPlaces: 3)
                                            ->alignEnd(),
                                        TextEntry::make('total')
                                            ->label('Total')
                                            ->numeric(decimalPlaces: 3)
                                            ->weight('bold')
                                            ->alignEnd(),
                                    ])
                                    ->table([
                                        TableColumn::make('#')->alignCenter()->width(50),
                                        TableColumn::make('Item Code')->width(120),
                                        TableColumn::make('Description'),
                                        TableColumn::make('Qty')->alignEnd()->width(80),
                                        TableColumn::make('Unit Price')->alignEnd()->width(120),
                                        TableColumn::make('Disc. %')->alignEnd()->width(80),
                                        TableColumn::make('Net Price')->alignEnd()->width(120),
                                        TableColumn::make('Total')->alignEnd()->width(120),
                                    ]),
                            ]),

                // ── Remarks ────────────────────────────────────────────
                Grid::make(4)
                    ->columnSpanFull()
                    ->schema([
                        // Remarks aligned to the bottom using self-end
                        Section::make('Remarks')
                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                            ->columnSpan(3)
                            ->extraAttributes(['class' => 'self-end'])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(fn (?Invoice $record) => empty($record?->remarks))
                            ->schema([
                                TextEntry::make('remarks')
                                    ->hiddenLabel()
                                    ->placeholder('No remarks for this invoice.')
                                    ->extraAttributes(['class' => 'text-gray-600 dark:text-gray-400']),
                            ]),

                        // Summary with tight gaps, inline labels, and larger balance due
                        Section::make('Summary')
                            ->icon('heroicon-o-calculator')
                            ->columnSpan(1)
                            ->extraAttributes(['class' => 'sticky top-4'])
                            ->schema([
                                Grid::make(1)
                                    ->extraAttributes(['class' => 'gap-1']) // Decreases vertical gap between entries
                                    ->schema([
                                        TextEntry::make('total_before_discount')
                                            ->label('Subtotal')
                                            ->numeric(decimalPlaces: 3)
                                            ->color('gray')
                                            ->inlineLabel(),

                                        TextEntry::make('discount')
                                            ->label('Discount')
                                            ->numeric(decimalPlaces: 3)
                                            ->color('danger')
                                            ->inlineLabel()
                                            ->formatStateUsing(fn ($state) => $state > 0
                                                ? '-' . number_format($state, 3)
                                                : number_format($state, 3)),

                                        TextEntry::make('total_after_discount')
                                            ->label('Amount Due')
                                            ->numeric(decimalPlaces: 3)
                                            ->weight('extrabold')
                                            ->size('2xl')
                                            ->color('primary')
                                            ->inlineLabel()
                                            ->extraAttributes(['class' => 'mt-3 pt-3 border-t-2 border-primary-500 dark:border-primary-400']),
                                    ]),
                            ]),
                    ]),


                // ── M-Pesa Payments ───────────────────────────────────
                Section::make('M-Pesa Payments')
                    ->icon('heroicon-o-banknotes')
                    ->columnSpanFull()
                    ->poll('5s')
                    ->description(fn (?Invoice $record) => $record
                        ? app(MpesaService::class)->getTransactionsForInvoice($record)->count() . ' transaction(s) recorded'
                        : null)
                    ->collapsible()
                    ->schema([
                        TextEntry::make('status')
                            ->label('Payment Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                Invoice::STATUS_PAID => 'PAID',
                                Invoice::STATUS_PARTIALLY_PAID => 'PARTIALLY PAID',
                                default => 'UNPAID',
                            })
                            ->color(fn (string $state): string => match ($state) {
                                Invoice::STATUS_PAID => 'success',
                                Invoice::STATUS_PARTIALLY_PAID => 'warning',
                                default => 'gray',
                            }),
                        RepeatableEntry::make('mpesa_transactions')
                            ->hiddenLabel()
                            ->state(fn (?Invoice $record) => $record
                                ? app(MpesaService::class)->getTransactionsForInvoice($record)
                                : [])
                            ->placeholder('No M-Pesa transactions recorded for this invoice.')
                            ->schema([
                                TextEntry::make('transaction_id')
                                    ->label('Trans ID')
                                    ->badge()
                                    ->color('primary'),
                                TextEntry::make('trans_amount')
                                    ->label('Amount')
                                    ->numeric(decimalPlaces: 2)
                                    ->weight('bold')
                                    ->alignEnd(),
                                TextEntry::make('msisdn')
                                    ->label('Phone'),
                                TextEntry::make('first_name')
                                    ->label('Payer')
                                    ->formatStateUsing(fn ($record) => trim("{$record->first_name} {$record->last_name}") ?: '—'),
                                TextEntry::make('bill_ref_number')
                                    ->label('Reference'),
                                TextEntry::make('trans_time')
                                    ->label('Trans Time')
                                    ->formatStateUsing(fn (?string $state) => $state
                                        ? date('M j, Y g:i A', strtotime($state) ?: time())
                                        : '—'),
                                TextEntry::make('mpesa_receipt_number')
                                    ->label('Receipt No.')
                                    ->placeholder(fn ($record) => $record->status === MpesaTransaction::STATUS_SUCCESS
                                        ? 'Receipt pending — awaiting callback'
                                        : '—')
                                    ->badge()
                                    ->color(fn ($record) => $record->status === MpesaTransaction::STATUS_SUCCESS && is_null($record->mpesa_receipt_number)
                                        ? 'warning'
                                        : ($record->mpesa_receipt_number ? 'success' : 'gray')),
                            ])
                            ->table([
                                TableColumn::make('Trans ID'),
                                TableColumn::make('Amount')->alignEnd()->width(120),
                                TableColumn::make('Phone')->width(140),
                                TableColumn::make('Payer'),
                                TableColumn::make('Reference'),
                                TableColumn::make('Trans Time'),
                            ]),
                    ]),
            ]);
    }
}
