<?php

namespace App\Filament\Resources\MpesaTransactions\Pages;

use App\Filament\Resources\MpesaTransactions\MpesaTransactionResource;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListMpesaTransactions extends ListRecords
{
    protected static string $resource = MpesaTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testPayment')
                ->label('Test STK Push')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->modalHeading('Test M-Pesa STK Push')
                ->modalDescription('Initiate a live STK push to test the Daraja API integration.')
                ->modalSubmitActionLabel('Send STK Push')
                ->form([
                    TextInput::make('phone_number')
                        ->label('Phone Number')
                        ->default('0712345678')
                        ->required()
                        ->regex('/^(?:\+?254|0)?[17]\d{8}$/')
                        ->validationMessages([
                            'regex' => 'Please enter a valid Kenyan phone number (e.g. 0712345678 or 254712345678).',
                        ]),
                    TextInput::make('amount')
                        ->label('Amount (KES)')
                        ->numeric()
                        ->minValue(1)
                        ->default(1000)
                        ->required()
                        ->prefix('KES'),
                    TextInput::make('bill_ref_number')
                        ->label('Bill Reference')
                        ->default('DEMO-001')
                        ->placeholder('e.g. INV-1 or DEMO-001')
                        ->required(),
                ])
                ->action(function (array $data, MpesaService $mpesaService): void {
                    $phone = (string) $data['phone_number'];
                    $amount = (float) $data['amount'];
                    $ref = (string) $data['bill_ref_number'];

                    try {
                        $normalizedPhone = $mpesaService->normalizePhoneNumber($phone);

                        $transaction = MpesaTransaction::create([
                            'status' => MpesaTransaction::STATUS_PENDING,
                            'msisdn' => $normalizedPhone,
                            'trans_amount' => number_format($amount, 2, '.', ''),
                            'bill_ref_number' => $ref,
                        ]);

                        $response = $mpesaService->sendStkPush(
                            phone: $phone,
                            amount: $amount,
                            reference: $ref,
                            description: 'Test Payment'
                        );

                        $transaction->update([
                            'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                            'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                            'raw_payload' => json_encode($response),
                        ]);

                        Notification::make()
                            ->title('STK Push Sent')
                            ->body("Payment prompt sent to {$phone}. CheckoutRequestID: " . ($response['CheckoutRequestID'] ?? 'OK'))
                            ->info()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('STK Push Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
