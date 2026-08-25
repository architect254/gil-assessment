<?php

namespace Tests\Feature;

use App\Models\MpesaTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $transId = 'SBX12345ABC'): array
    {
        return [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transId,
            'TransTime' => '20260822143015',
            'TransAmount' => '1500.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-1',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '50000.00',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'MiddleName' => '',
            'LastName' => 'Doe',
        ];
    }

    public function test_confirmation_accepts_and_stores_transaction(): void
    {
        $response = $this->postJson('/api/mpesa/confirmation', $this->payload());

        $response->assertOk()
            ->assertJsonPath('ResultCode', 0);

        $this->assertDatabaseHas('mpesa_transactions', [
            'transaction_id' => 'SBX12345ABC',
            'trans_amount' => '1500.00',
            'bill_ref_number' => 'INV-1',
            'msisdn' => '254712345678',
            'first_name' => 'John',
        ]);
    }

    public function test_validation_endpoint_accepts_transaction(): void
    {
        $response = $this->postJson('/api/mpesa/validation', $this->payload('VAL001'));

        $response->assertOk()->assertJsonPath('ResultCode', 0);
        $this->assertDatabaseHas('mpesa_transactions', ['transaction_id' => 'VAL001']);
    }

    public function test_duplicate_callbacks_are_idempotent(): void
    {
        $this->postJson('/api/mpesa/confirmation', $this->payload());
        $this->postJson('/api/mpesa/confirmation', $this->payload());

        $this->assertSame(1, MpesaTransaction::query()->where('transaction_id', 'SBX12345ABC')->count());
    }

    public function test_stk_push_style_payload_is_parsed(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_DMZ_12321_23423476',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 1.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQDM7W6S'],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/mpesa/confirmation', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('mpesa_transactions', [
            'transaction_id' => 'RKTQDM7W6S',
            'msisdn' => '254712345678',
            'trans_amount' => '1',
        ]);
    }

    public function test_lookup_endpoint_returns_transaction_data(): void
    {
        $this->postJson('/api/mpesa/confirmation', $this->payload());

        $response = $this->getJson('/api/mpesa/transactions/SBX12345ABC');

        $response->assertOk()
            ->assertJsonPath('data.transaction_id', 'SBX12345ABC')
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.customer', 'John Doe');
    }

    public function test_callback_secret_is_enforced_when_configured(): void
    {
        config(['mpesa.callback_secret' => 'topsecret']);

        $this->postJson('/api/mpesa/confirmation', $this->payload())
            ->assertStatus(401);

        $this->withHeader('X-Callback-Secret', 'topsecret')
            ->postJson('/api/mpesa/confirmation', $this->payload())
            ->assertOk();

        $this->assertSame(1, MpesaTransaction::count());
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $payload = $this->payload('DUP12345');

        // First callback
        $res1 = $this->postJson('/api/mpesa/confirmation', $payload);
        $res1->assertOk()->assertJsonPath('ResultCode', 0);
        $this->assertSame(1, MpesaTransaction::query()->where('transaction_id', 'DUP12345')->count());

        // Second duplicate callback
        $payload['TransAmount'] = '2000.00';
        $res2 = $this->postJson('/api/mpesa/confirmation', $payload);
        $res2->assertOk()->assertJsonPath('ResultCode', 0);

        // Count must still be 1, amount updated
        $this->assertSame(1, MpesaTransaction::query()->where('transaction_id', 'DUP12345')->count());
        $this->assertSame('2000.00', MpesaTransaction::query()->where('transaction_id', 'DUP12345')->value('trans_amount'));
    }

    public function test_malformed_payload_is_defensively_handled_and_persisted(): void
    {
        $malformed = [
            'UnexpectedKey' => 12345,
            'CorruptedData' => ['nested' => true],
        ];

        $response = $this->postJson('/api/mpesa/confirmation', $malformed);

        $response->assertOk()
            ->assertJsonPath('ResultCode', 0)
            ->assertJsonPath('ResultDesc', 'Accepted');

        $this->assertSame(1, MpesaTransaction::count());
        $this->assertNotNull(MpesaTransaction::first()->raw_payload);
    }
}
