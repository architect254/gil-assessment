<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MpesaInvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->customer = Customer::create([
            'code' => 'CUST001',
            'name' => 'John Kamau',
            'phone' => '0712345678',
        ]);

        $this->invoice = Invoice::createWithNextNumber([
            'customer_id' => $this->customer->id,
            'posting_date' => now()->toDateString(),
            'total_before_discount' => '5000.000',
            'discount' => '0.000',
            'total_after_discount' => '5000.000',
            'remarks' => 'Test order payment',
        ]);
    }

    public function test_can_trigger_live_stk_push_from_invoice_modal(): void
    {
        config([
            'mpesa.consumer_key' => 'test_key',
            'mpesa.consumer_secret' => 'test_secret',
            'mpesa.shortcode' => '174379',
            'mpesa.passkey' => 'test_passkey',
            'mpesa.environment' => 'sandbox',
        ]);

        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
                'access_token' => 'mocked_live_token',
            ], 200),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'MerchantRequestID' => 'REQ-123',
                'CheckoutRequestID' => 'ws_CO_TEST_999',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ], 200),
        ]);

        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
            ->callAction('payMpesa', data: [
                'phone_number' => '0712345678',
                'amount' => 5000.00,
                'bill_ref_number' => 'INV-' . $this->invoice->no,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('mpesa_transactions', [
            'msisdn' => '254712345678',
            'trans_amount' => '5000.00',
            'bill_ref_number' => 'INV-' . $this->invoice->no,
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_TEST_999',
            'merchant_request_id' => 'REQ-123',
        ]);

        $transaction = MpesaTransaction::query()
            ->where('checkout_request_id', 'ws_CO_TEST_999')
            ->first();

        $this->assertNotNull($transaction->raw_payload);
        $decoded = json_decode($transaction->raw_payload, true);
        $this->assertSame('ws_CO_TEST_999', $decoded['CheckoutRequestID']);
        $this->assertSame('REQ-123', $decoded['MerchantRequestID']);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest') {
                $data = $request->data();
                return $data['PhoneNumber'] === '254712345678'
                    && $data['AccountReference'] === 'INV-' . $this->invoice->no;
            }
            return true;
        });
    }

    public function test_stk_push_failure_records_failed_status(): void
    {
        config([
            'mpesa.consumer_key' => 'test_key',
            'mpesa.consumer_secret' => 'test_secret',
            'mpesa.shortcode' => '174379',
            'mpesa.passkey' => 'test_passkey',
            'mpesa.environment' => 'sandbox',
        ]);

        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
                'access_token' => 'mocked_live_token',
            ], 200),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'errorMessage' => 'Invalid credentials',
            ], 400),
        ]);

        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
            ->callAction('payMpesa', data: [
                'phone_number' => '0712345678',
                'amount' => 5000.00,
                'bill_ref_number' => 'INV-' . $this->invoice->no,
            ])
            ->assertHasActionErrors();

        $this->assertDatabaseHas('mpesa_transactions', [
            'msisdn' => '254712345678',
            'trans_amount' => '5000.00',
            'bill_ref_number' => 'INV-' . $this->invoice->no,
            'status' => MpesaTransaction::STATUS_FAILED,
        ]);
    }

    public function test_stk_push_callback_updates_pending_to_success(): void
    {
        config([
            'mpesa.consumer_key' => 'test_key',
            'mpesa.consumer_secret' => 'test_secret',
            'mpesa.shortcode' => '174379',
            'mpesa.passkey' => 'test_passkey',
            'mpesa.environment' => 'sandbox',
        ]);

        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
                'access_token' => 'mocked_live_token',
            ], 200),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'MerchantRequestID' => 'REQ-456',
                'CheckoutRequestID' => 'ws_CO_UPDATE_TEST',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ], 200),
        ]);

        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
            ->callAction('payMpesa', data: [
                'phone_number' => '0712345678',
                'amount' => 5000.00,
                'bill_ref_number' => 'INV-' . $this->invoice->no,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('mpesa_transactions', [
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_UPDATE_TEST',
        ]);

        // Simulate the Daraja callback
        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'REQ-456',
                    'CheckoutRequestID' => 'ws_CO_UPDATE_TEST',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 5000.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQDM7W6S'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $callbackPayload);

        $transaction = MpesaTransaction::query()
            ->where('checkout_request_id', 'ws_CO_UPDATE_TEST')
            ->first();

        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $transaction->status);
        $this->assertSame('0', $transaction->result_code);
        $this->assertSame('RKTQDM7W6S', $transaction->transaction_id);
        $this->assertSame('REQ-456', $transaction->merchant_request_id);
    }

    public function test_stk_push_cancellation_updates_pending_to_cancelled(): void
    {
        config([
            'mpesa.consumer_key' => 'test_key',
            'mpesa.consumer_secret' => 'test_secret',
            'mpesa.shortcode' => '174379',
            'mpesa.passkey' => 'test_passkey',
            'mpesa.environment' => 'sandbox',
        ]);

        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
                'access_token' => 'mocked_live_token',
            ], 200),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'MerchantRequestID' => 'REQ-789',
                'CheckoutRequestID' => 'ws_CO_CANCEL_TEST',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ], 200),
        ]);

        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
            ->callAction('payMpesa', data: [
                'phone_number' => '0712345678',
                'amount' => 5000.00,
                'bill_ref_number' => 'INV-' . $this->invoice->no,
            ])
            ->assertHasNoActionErrors();

        // Simulate the cancellation callback
        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'REQ-789',
                    'CheckoutRequestID' => 'ws_CO_CANCEL_TEST',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user',
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $callbackPayload);

        $transaction = MpesaTransaction::query()
            ->where('checkout_request_id', 'ws_CO_CANCEL_TEST')
            ->first();

        $this->assertSame(MpesaTransaction::STATUS_CANCELLED, $transaction->status);
        $this->assertSame('1032', $transaction->result_code);
        $this->assertSame('Request cancelled by user', $transaction->result_desc);
    }
}
