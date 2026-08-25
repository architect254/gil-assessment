<?php

namespace App\Filament\Resources\MpesaTransactions\Pages;

use App\Filament\Resources\MpesaTransactions\MpesaTransactionResource;
use App\Services\MpesaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
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
                ->label('Test Payment')
                ->icon('heroicon-o-banknotes')
                ->color('primary')
                ->modalHeading('M-Pesa Sandbox Payment')
                ->modalDescription('Execute a simulated callback or initiate a live STK push test.')
                ->modalSubmitActionLabel('Execute')
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
                    TextInput::make('first_name')
                        ->label('First Name')
                        ->default('Demo'),
                    TextInput::make('last_name')
                        ->label('Last Name')
                        ->default('User'),
                ])
                ->action(function (array $data, MpesaService $mpesaService): void {
                    $phone = (string) $data['phone_number'];
                    $amount = (float) $data['amount'];
                    $ref = (string) $data['bill_ref_number'];
                    $mode = $data['mode'] ?? 'simulate';

                    if ($mode === 'simulate') {
                        $transaction = $mpesaService->simulatePayment(
                            phone: $phone,
                            amount: $amount,
                            billRef: $ref,
                            firstName: (string) ($data['first_name'] ?? 'Demo'),
                            lastName: (string) ($data['last_name'] ?? 'User')
                        );

                        Notification::make()
                            ->title('Test Payment Simulated')
                            ->body("Transaction {$transaction->transaction_id} for KES " . number_format($amount, 2) . " created successfully.")
                            ->success()
                            ->send();
                    } else {
                        try {
                            $response = $mpesaService->sendStkPush(
                                phone: $phone,
                                amount: $amount,
                                reference: $ref,
                                description: 'Test Payment'
                            );

                            Notification::make()
                                ->title('STK Push Request Dispatched')
                                ->body("STK prompt sent to {$phone}. CheckoutRequestID: " . ($response['CheckoutRequestID'] ?? 'OK'))
                                ->info()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('STK Push Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }
                }),
        ];
    }
}
