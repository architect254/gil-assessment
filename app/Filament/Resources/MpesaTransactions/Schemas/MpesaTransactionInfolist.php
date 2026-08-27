<?php

namespace App\Filament\Resources\MpesaTransactions\Schemas;

use App\Models\MpesaTransaction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MpesaTransactionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Transaction Overview')
                    ->columnSpanFull()
                    ->columns(8)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                MpesaTransaction::STATUS_SUCCESS => 'success',
                                MpesaTransaction::STATUS_FAILED => 'danger',
                                MpesaTransaction::STATUS_CANCELLED => 'warning',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                MpesaTransaction::STATUS_SUCCESS => 'Success',
                                MpesaTransaction::STATUS_FAILED => 'Failed',
                                MpesaTransaction::STATUS_CANCELLED => 'Cancelled',
                                default => 'Pending',
                            }),
                        TextEntry::make('payment_method')
                            ->label('Channel')
                            ->badge()
                            ->color('success')
                            ->formatStateUsing(fn (string $state): string => strtoupper($state)),
                        TextEntry::make('transaction_id')
                            ->label('Transaction ID')
                            ->badge()
                            ->color('primary')
                            ->placeholder('Awaiting callback…'),
                        TextEntry::make('trans_amount')
                            ->label('Amount (KES)')
                            ->numeric(decimalPlaces: 2)
                            ->weight('bold'),
                        TextEntry::make('msisdn')
                            ->label('Phone Number (MSISDN)'),
                        TextEntry::make('bill_ref_number')
                            ->label('Bill Reference')
                            ->placeholder('—'),
                        TextEntry::make('first_name')
                            ->label('Payer Name')
                            ->formatStateUsing(fn ($record): string => trim("{$record->first_name} {$record->middle_name} {$record->last_name}") ?: '—'),
                        TextEntry::make('transaction_type')
                            ->label('Transaction Type')
                            ->placeholder('Pay Bill'),
                        TextEntry::make('business_short_code')
                            ->label('Shortcode')
                            ->placeholder('—'),
                        TextEntry::make('org_account_balance')
                            ->label('Account Balance')
                            ->placeholder('—'),
                        TextEntry::make('third_party_trans_id')
                            ->label('Third Party Trans ID')
                            ->placeholder('—'),
                        TextEntry::make('trans_time')
                            ->label('Trans Time')
                            ->placeholder('—')
                            ->dateTime(),
                        TextEntry::make('created_at')
                            ->label('Received At')
                            ->dateTime(),
                    ]),
                Section::make('Raw Daraja Webhook Payload')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('raw_payload')
                            ->hiddenLabel()
                            ->formatStateUsing(function (?string $state): string {
                                if (empty($state)) {
                                    return 'No payload recorded.';
                                }

                                $decoded = json_decode($state, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                                }

                                return $state;
                            })
                            ->extraAttributes(['class' => 'font-mono text-sm whitespace-pre overflow-x-auto bg-gray-50 dark:bg-gray-900 p-4 rounded-lg border border-gray-200 dark:border-gray-700']),
                    ]),
            ]);
    }
}
