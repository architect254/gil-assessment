<?php

namespace App\Filament\Resources\Invoices\Actions;

use App\Jobs\QueryStkPushStatus;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Filament\Actions\Action;
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
            ->visible(fn (Invoice $record): bool => !$record->needs_approval)
            ->modalHeading(fn (Invoice $record): string => "M-Pesa Payment — Invoice #{$record->no}")
            ->modalDescription('Initiate a live Lipa Na M-Pesa STK push to the customer\'s phone.')
            ->modalSubmitActionLabel('Send STK Push')
            ->fillForm(fn (Invoice $record): array => [
                'phone_number' => $record->customer?->phone ?? '',
                'amount' => $record->total_after_discount,
                'bill_ref_number' => 'INV-' . $record->no,
            ])
            ->form([
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

                try {
                    $normalizedPhone = $mpesaService->normalizePhoneNumber($phone);

                    $transaction = MpesaTransaction::create([
                        'status' => MpesaTransaction::STATUS_PENDING,
                        'msisdn' => $normalizedPhone,
                        'trans_amount' => number_format($amount, 2, '.', ''),
                        'bill_ref_number' => $ref,
                        'invoice_number' => $ref,
                        'raised_at' => now(),
                        'invoice_id' => $record->id,
                    ]);

                    $response = $mpesaService->sendStkPush(
                        phone: $phone,
                        amount: $amount,
                        reference: $ref,
                        description: "Invoice #{$record->no}"
                    );

                    $transaction->update([
                        'checkout_request_id' => $response['CheckoutRequestID'] ?? null,
                        'merchant_request_id' => $response['MerchantRequestID'] ?? null,
                        'raw_payload' => json_encode($response),
                    ]);

                    QueryStkPushStatus::dispatch($transaction->id)
                        ->delay(now()->addSeconds(30));

                    Notification::make()
                        ->title('STK Push Sent')
                        ->body("Payment prompt sent to {$phone}. CheckoutRequestID: " . ($response['CheckoutRequestID'] ?? 'OK'))
                        ->info()
                        ->send();
                } catch (\Throwable $e) {
                    if (isset($transaction) && $transaction->exists) {
                        $transaction->update([
                            'status' => MpesaTransaction::STATUS_FAILED,
                            'result_desc' => $e->getMessage(),
                        ]);
                    }

                    Notification::make()
                        ->title('STK Push Request Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
