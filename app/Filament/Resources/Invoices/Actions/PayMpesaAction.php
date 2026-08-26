<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Models\Invoice;
use App\Services\MpesaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class PayMpesaAction
{
    public static function make(): Action
    {
        return Action::make('payMpesa')
            ->label('Pay with M-Pesa')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (Invoice $record): bool => $record->needs_approval)
            ->modalHeading(fn (Invoice $record): string => "M-Pesa Payment — Invoice #{$record->no}")
            ->modalDescription('Initiate a live Lipa Na M-Pesa STK push or simulate a local callback transaction.')
            ->modalSubmitActionLabel('Process Payment')
            ->fillForm(fn (Invoice $record): array => [
                'phone_number' => $record->customer?->phone ?? '',
                'amount' => $record->total_after_discount,
                'bill_ref_number' => 'INV-' . $record->no,
                'mode' => 'simulate',
            ])
            ->form([
                Radio::make('mode')
                    ->label('Execution Mode')
                    ->options([
                        'simulate' => 'Local Simulation (Instant demo callback)',
                        'stk_push' => 'Live STK Push (Daraja API mobile prompt)',
                    ])
                    ->default('simulate')
                    ->required(),
                TextInput::make('phone_number')
                    ->label('M-Pesa Phone Number')
                    ->placeholder('e.g. 0712345678 or 254712345678')
                    ->helperText('Kenyan MSISDN format (07XXXXXXXX, 01XXXXXXXX, or 2547XXXXXXXX)')
                    ->required()
                    ->regex('/^(?:\+?254|0)?[17]\d{8}$/')
                    ->validationMessages([
                        'regex' => 'Please enter a valid Kenyan phone number (e.g. 0712345678 or 254712345678).',
                    ]),
                TextInput::make('amount')
                    ->label('Payment Amount')
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->prefix('KES'),
                TextInput::make('bill_ref_number')
                    ->label('Bill Reference')
                    ->required()
                    ->helperText('Matched to Invoice number for automated reconciliation.'),
            ])
            ->action(function (array $data, Invoice $record, MpesaService $mpesaService): void {
                $phone = (string) $data['phone_number'];
                $amount = (float) $data['amount'];
                $ref = (string) $data['bill_ref_number'];
                $mode = $data['mode'] ?? 'simulate';

                if ($mode === 'simulate') {
                    $customerName = $record->customer?->name ?? 'Customer';
                    $nameParts = explode(' ', $customerName, 2);
                    $firstName = $nameParts[0] ?? 'Customer';
                    $lastName = $nameParts[1] ?? 'Demo';

                    $transaction = $mpesaService->simulatePayment(
                        phone: $phone,
                        amount: $amount,
                        billRef: $ref,
                        firstName: $firstName,
                        lastName: $lastName
                    );

                    Notification::make()
                        ->title('M-Pesa Payment Simulated')
                        ->body("Transaction {$transaction->transaction_id} for KES " . number_format($amount, 2) . " successfully recorded for Invoice #{$record->no}.")
                        ->success()
                        ->send();
                } else {
                    try {
                        $response = $mpesaService->sendStkPush(
                            phone: $phone,
                            amount: $amount,
                            reference: $ref,
                            description: "Invoice #{$record->no}"
                        );

                        Notification::make()
                            ->title('STK Push Sent')
                            ->body("Payment prompt sent to {$phone}. CheckoutRequestID: " . ($response['CheckoutRequestID'] ?? 'OK'))
                            ->info()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('STK Push Request Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }
            });
    }
}
