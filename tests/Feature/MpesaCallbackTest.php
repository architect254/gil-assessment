<?php

namespace Tests\Feature;

use App\Models\MpesaTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            'status' => MpesaTransaction::STATUS_SUCCESS,
        ]);
    }

    public function test_validation_endpoint_accepts_transaction(): void
    {
        $response = $this->postJson('/api/mpesa/validation', $this->payload('VAL001'));

        $response->assertOk()->assertJsonPath('ResultCode', 0);
        $this->assertDatabaseHas('mpesa_transactions', [
            'transaction_id' => 'VAL001',
            'status' => MpesaTransaction::STATUS_SUCCESS,
        ]);
    }

    public function test_duplicate_callbacks_are_idempotent(): void
    {
        $this->postJson('/api/mpesa/confirmation', $this->payload());
        $this->postJson('/api/mpesa/confirmation', $this->payload());

        $this->assertSame(1, MpesaTransaction::query()->where('transaction_id', 'SBX12345ABC')->count());
    }

    public function test_stk_push_success_payload_is_parsed(): void
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
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'result_code' => '0',
            'checkout_request_id' => 'ws_CO_DMZ_12321_23423476',
            'merchant_request_id' => '29115-34620561-1',
        ]);
    }

    public function test_stk_push_cancellation_is_recorded(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_CANCEL_123',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user',
                ],
            ],
        ];

        $response = $this->postJson('/api/mpesa/confirmation', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('mpesa_transactions', [
            'checkout_request_id' => 'ws_CO_CANCEL_123',
            'status' => MpesaTransaction::STATUS_CANCELLED,
            'result_code' => '1032',
            'result_desc' => 'Request cancelled by user',
            'merchant_request_id' => '29115-34620561-1',
        ]);
    }

    public function test_stk_push_failure_is_recorded(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_FAIL_456',
                    'ResultCode' => 1037,
                    'ResultDesc' => 'DS timeout user cannot be reached',
                ],
            ],
        ];

        $response = $this->postJson('/api/mpesa/confirmation', $payload);

        $response->assertOk();
        $this->assertDatabaseHas('mpesa_transactions', [
            'checkout_request_id' => 'ws_CO_FAIL_456',
            'status' => MpesaTransaction::STATUS_TIMEOUT,
            'result_code' => '1037',
            'result_desc' => 'DS timeout user cannot be reached',
        ]);
    }

    public function test_stk_push_cancellation_updates_pending_record(): void
    {
        $pending = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_MATCH_789',
            'msisdn' => '254712345678',
            'trans_amount' => '1000.00',
            'bill_ref_number' => 'INV-1',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_MATCH_789',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user',
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $pending->refresh();
        $this->assertSame(MpesaTransaction::STATUS_CANCELLED, $pending->status);
        $this->assertSame('1032', $pending->result_code);
        $this->assertSame('Request cancelled by user', $pending->result_desc);
        $this->assertSame('29115-34620561-1', $pending->merchant_request_id);
    }

    public function test_stk_push_success_updates_pending_record(): void
    {
        $pending = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_SUCCESS_999',
            'msisdn' => '254712345678',
            'trans_amount' => '2500.00',
            'bill_ref_number' => 'INV-2',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_SUCCESS_999',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 2500.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQDM7W6S'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $pending->refresh();
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $pending->status);
        $this->assertSame('0', $pending->result_code);
        $this->assertSame('RKTQDM7W6S', $pending->transaction_id);
    }

    public function test_lookup_endpoint_returns_transaction_data(): void
    {
        $this->postJson('/api/mpesa/confirmation', $this->payload());

        $response = $this->getJson('/api/mpesa/transactions/SBX12345ABC');

        $response->assertOk()
            ->assertJsonPath('data.transaction_id', 'SBX12345ABC')
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.customer', 'John Doe')
            ->assertJsonPath('data.status', 'success');
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

        $res1 = $this->postJson('/api/mpesa/confirmation', $payload);
        $res1->assertOk()->assertJsonPath('ResultCode', 0);
        $this->assertSame(1, MpesaTransaction::query()->where('transaction_id', 'DUP12345')->count());

        $payload['TransAmount'] = '2000.00';
        $res2 = $this->postJson('/api/mpesa/confirmation', $payload);
        $res2->assertOk()->assertJsonPath('ResultCode', 0);

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

    public function test_success_to_failed_callback_is_rejected(): void
    {
        $success = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'checkout_request_id' => 'ws_CO_GUARD_TEST',
            'transaction_id' => 'GUARD_TX_001',
            'msisdn' => '254712345678',
            'trans_amount' => '1000.00',
            'bill_ref_number' => 'INV-99',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-99999-1',
                    'CheckoutRequestID' => 'ws_CO_GUARD_TEST',
                    'ResultCode' => 1,
                    'ResultDesc' => 'Insufficient balance',
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $success->refresh();
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $success->status);
        $this->assertSame('GUARD_TX_001', $success->transaction_id);
    }

    public function test_late_callback_backfills_receipt_on_provisional_success(): void
    {
        $provisional = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'checkout_request_id' => 'ws_CO_RECEIPT_TEST',
            'msisdn' => '254712345678',
            'trans_amount' => '5000.00',
            'bill_ref_number' => 'INV-50',
            'resolved_at' => now()->subMinutes(1),
        ]);

        $this->assertNull($provisional->mpesa_receipt_number);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-88888-1',
                    'CheckoutRequestID' => 'ws_CO_RECEIPT_TEST',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 5000.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQRCPT99'],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RKTQRCPT99'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $provisional->refresh();
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $provisional->status);
        $this->assertSame('RKTQRCPT99', $provisional->mpesa_receipt_number);
        $this->assertSame('RKTQRCPT99', $provisional->transaction_id);
    }

    public function test_late_callback_does_not_clobber_invoice_id_or_raised_at(): void
    {
        // Create a pending record directly via DB to set raised_at (not in Fillable)
        DB::table('mpesa_transactions')->insert([
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_CLOBBER_TEST',
            'msisdn' => '254712345678',
            'trans_amount' => '2500.00',
            'bill_ref_number' => 'INV-77',
            'raised_at' => now()->subMinutes(2),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pending = MpesaTransaction::where('checkout_request_id', 'ws_CO_CLOBBER_TEST')->first();

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-77777-1',
                    'CheckoutRequestID' => 'ws_CO_CLOBBER_TEST',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 2500.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQCLOB77'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $pending->refresh();
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $pending->status);
        $this->assertNull($pending->invoice_id);
        $this->assertNotNull($pending->raised_at);
    }

    public function test_timeout_result_code_1037_maps_correctly(): void
    {
        $pending = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_TIMEOUT_TEST',
            'msisdn' => '254712345678',
            'trans_amount' => '750.00',
            'bill_ref_number' => 'INV-80',
        ]);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-66666-1',
                    'CheckoutRequestID' => 'ws_CO_TIMEOUT_TEST',
                    'ResultCode' => 1037,
                    'ResultDesc' => 'DS timeout user cannot be reached',
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $pending->refresh();
        $this->assertSame(MpesaTransaction::STATUS_TIMEOUT, $pending->status);
        $this->assertSame('1037', $pending->result_code);
        $this->assertNotNull($pending->resolved_at);
    }

    public function test_mpesa_receipt_number_extracted_from_callback_metadata(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-55555-1',
                    'CheckoutRequestID' => 'ws_CO_RECEIPT_EXTRACT',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 3000.00],
                            ['Name' => 'Msisdn', 'Value' => 254798765432],
                            ['Name' => 'TransID', 'Value' => 'RKTQEXTR55'],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RKTQEXTR55'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $this->assertDatabaseHas('mpesa_transactions', [
            'checkout_request_id' => 'ws_CO_RECEIPT_EXTRACT',
            'mpesa_receipt_number' => 'RKTQEXTR55',
            'status' => MpesaTransaction::STATUS_SUCCESS,
        ]);
    }

    public function test_resolved_at_set_on_callback(): void
    {
        $pending = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_RESOLVED_TEST',
            'msisdn' => '254712345678',
            'trans_amount' => '1500.00',
            'bill_ref_number' => 'INV-60',
        ]);

        $this->assertNull($pending->resolved_at);

        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-44444-1',
                    'CheckoutRequestID' => 'ws_CO_RESOLVED_TEST',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 1500.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQRES60'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $payload);

        $pending->refresh();
        $this->assertNotNull($pending->resolved_at);
    }
}
