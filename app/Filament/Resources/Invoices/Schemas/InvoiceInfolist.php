<?php

namespace App\Filament\Resources\Invoices\Schemas;

use App\Models\Invoice;
use App\Services\MpesaService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Invoice '.($schema->getRecord()?->no ?? ''))
                    ->columns(4)
                    ->schema([
                        TextEntry::make('no')
                            ->label('Document No.')
                            ->badge(),
                        TextEntry::make('posting_date')
                            ->label('Posting Date')
                            ->date(),
                        TextEntry::make('customer.name')
                            ->label('Customer'),
                        TextEntry::make('salesEmployee.name')
                            ->label('Sales Employee')
                            ->placeholder('—'),
                        TextEntry::make('needs_approval')
                            ->label('Approval Status')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Pending Approval' : 'Approved')
                            ->color(fn (bool $state): string => $state ? 'warning' : 'success'),
                        TextEntry::make('createdBy.name')
                            ->label('Created By')
                            ->placeholder('—'),
                    ]),
                Fieldset::make('Lines')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('lines')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('line_no')->label('#'),
                                TextEntry::make('item_code')->label('Item Code')->placeholder('—'),
                                TextEntry::make('item_description')->label('Description'),
                                TextEntry::make('quantity')->numeric(decimalPlaces: 3),
                                TextEntry::make('price_before_discount')->label('Unit Price')->numeric(decimalPlaces: 3),
                                TextEntry::make('discount')->label('Disc. %')->suffix('%'),
                                TextEntry::make('price_after_discount')->label('Price After Disc.')->numeric(decimalPlaces: 3),
                                TextEntry::make('total')->numeric(decimalPlaces: 3)->weight('bold'),
                            ])
                            ->table([
                                TableColumn::make('#'),
                                TableColumn::make('Item Code'),
                                TableColumn::make('Description'),
                                TableColumn::make('Quantity')->alignEnd(),
                                TableColumn::make('Unit Price')->alignEnd(),
                                TableColumn::make('Disc. %')->alignEnd(),
                                TableColumn::make('Price After Disc.')->alignEnd(),
                                TableColumn::make('Total')->alignEnd(),
                            ]),
                    ]),
                Grid::make(3)
                    ->columnSpanFull()
                    ->schema([
                        Grid::make()->schema([]),
                        Grid::make()->schema([]),
                        Section::make('Totals')
                            ->schema([
                                TextEntry::make('total_before_discount')
                                    ->label('Total Before Discount')
                                    ->numeric(decimalPlaces: 3)
                                    ->alignEnd(),
                                TextEntry::make('discount')
                                    ->label('Discount Total')
                                    ->numeric(decimalPlaces: 3)
                                    ->alignEnd(),
                                TextEntry::make('total_after_discount')
                                    ->label('Total After Discount')
                                    ->numeric(decimalPlaces: 3)
                                    ->weight('bold')
                                    ->alignEnd(),
                            ]),
                    ]),
                Section::make('M-Pesa Payments')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('mpesa_transactions')
                            ->hiddenLabel()
                            ->state(fn (?Invoice $record) => $record ? app(MpesaService::class)->getTransactionsForInvoice($record) : [])
                            ->placeholder('No M-Pesa transactions recorded for this invoice.')
                            ->schema([
                                TextEntry::make('transaction_id')->label('Trans ID')->badge()->color('primary'),
                                TextEntry::make('trans_amount')->label('Amount')->numeric(decimalPlaces: 3),
                                TextEntry::make('msisdn')->label('Phone'),
                                TextEntry::make('first_name')->label('Payer')
                                    ->formatStateUsing(fn ($record) => trim("{$record->first_name} {$record->last_name}") ?: '—'),
                                TextEntry::make('bill_ref_number')->label('Reference'),
                                TextEntry::make('trans_time')->label('Trans Time')
                                    ->formatStateUsing(fn (?string $state) => $state ? date('Y-m-d H:i:s', strtotime($state) ?: time()) : '—'),
                            ])
                            ->table([
                                TableColumn::make('Trans ID'),
                                TableColumn::make('Amount')->alignEnd(),
                                TableColumn::make('Phone'),
                                TableColumn::make('Payer'),
                                TableColumn::make('Reference'),
                                TableColumn::make('Trans Time'),
                            ]),
                    ]),
            ]);
    }
}
