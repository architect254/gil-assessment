<?php

namespace Tests\Feature;

use App\Jobs\QueryStkPushStatus;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MpesaQueryJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mpesa.consumer_key' => 'test_key',
            'mpesa.consumer_secret' => 'test_secret',
            'mpesa.passkey' => 'test_passkey',
            'mpesa.shortcode' => '174379',
            'mpesa.environment' => 'sandbox',
        ]);
    }

    private function createPendingTransaction(string $checkoutRequestId = 'ws_CO_JOB_TEST'): MpesaTransaction
    {
        return MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => '29115-11111-1',
            'msisdn' => '254712345678',
            'trans_amount' => '2000.00',
            'bill_ref_number' => 'INV-30',
        ]);
    }

    private function mockDarajaForQuery(array $queryResponse): void
    {
        Http::fake([
            '*oauth*' => Http::response(['access_token' => 'fake_token'], 200),
            '*stkpushquery*' => Http::response($queryResponse, 200),
        ]);
    }

    public function test_query_job_marks_success_on_result_code_zero(): void
    {
        $transaction = $this->createPendingTransaction('ws_CO_SUCCESS_QUERY');

        $this->mockDarajaForQuery([
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successfully',
            'MerchantRequestID' => '29115-11111-1',
            'CheckoutRequestID' => 'ws_CO_SUCCESS_QUERY',
            'ResultCode' => '0',
            'ResultDesc' => 'The service request is processed successfully.',
        ]);

        (new QueryStkPushStatus($transaction->id))->handle(app(MpesaService::class));

        $transaction->refresh();
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $transaction->status);
        $this->assertSame('0', $transaction->result_code);
        $this->assertNotNull($transaction->resolved_at);
    }

    public function test_query_job_marks_timeout_on_result_code_1037(): void
    {
        $transaction = $this->createPendingTransaction('ws_CO_TIMEOUT_QUERY');

        $this->mockDarajaForQuery([
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successfully',
            'MerchantRequestID' => '29115-11111-1',
            'CheckoutRequestID' => 'ws_CO_TIMEOUT_QUERY',
            'ResultCode' => '1037',
            'ResultDesc' => 'DS timeout user cannot be reached',
        ]);

        (new QueryStkPushStatus($transaction->id))->handle(app(MpesaService::class));

        $transaction->refresh();
        $this->assertSame(MpesaTransaction::STATUS_TIMEOUT, $transaction->status);
        $this->assertSame('1037', $transaction->result_code);
    }

    public function test_query_job_skips_already_settled_transaction(): void
    {
        $transaction = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'checkout_request_id' => 'ws_CO_SETTLED_SKIP',
            'msisdn' => '254712345678',
            'trans_amount' => '1000.00',
            'bill_ref_number' => 'INV-31',
        ]);

        (new QueryStkPushStatus($transaction->id))->handle(app(MpesaService::class));

        $transaction->refresh();
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $transaction->status);
    }

    public function test_query_job_releases_when_still_pending(): void
    {
        $transaction = $this->createPendingTransaction('ws_CO_STILL_PENDING');

        // Return response WITHOUT ResultCode — simulates "still processing"
        $this->mockDarajaForQuery([
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accepted',
            'MerchantRequestID' => '29115-11111-1',
            'CheckoutRequestID' => 'ws_CO_STILL_PENDING',
        ]);

        // job is not dispatched through queue so attempts() returns 1, which is < tries(3)
        // therefore release(30) should be invoked internally
        $released = false;
        $job = \Mockery::mock(QueryStkPushStatus::class, [$transaction->id])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $job->shouldReceive('release')->once()->with(30)->andReturnUsing(function () use (&$released) {
            $released = true;
        });

        $job->handle(app(MpesaService::class));

        $this->assertTrue($released, 'release(30) should be called when Daraja returns no ResultCode');
        $transaction->refresh();
        $this->assertSame(MpesaTransaction::STATUS_PENDING, $transaction->status);
    }

    public function test_query_job_handles_callback_race_condition(): void
    {
        $transaction = $this->createPendingTransaction('ws_CO_RACE_TEST');

        $this->mockDarajaForQuery([
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
            'MerchantRequestID' => '29115-11111-1',
            'CheckoutRequestID' => 'ws_CO_RACE_TEST',
            'ResultCode' => '0',
            'ResultDesc' => 'Success',
        ]);

        // Simulate callback winning the race by settling before job runs
        $transaction->update(['status' => MpesaTransaction::STATUS_CANCELLED]);

        (new QueryStkPushStatus($transaction->id))->handle(app(MpesaService::class));

        $transaction->refresh();
        $this->assertSame(MpesaTransaction::STATUS_CANCELLED, $transaction->status);
    }

    public function test_late_callback_backfills_receipt_on_provisional_success(): void
    {
        $provisional = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'checkout_request_id' => 'ws_CO_PROVISIONAL',
            'msisdn' => '254712345678',
            'trans_amount' => '3000.00',
            'bill_ref_number' => 'INV-32',
            'result_code' => '0',
            'resolved_at' => now()->subMinutes(1),
        ]);

        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-22222-1',
                    'CheckoutRequestID' => 'ws_CO_PROVISIONAL',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 3000.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQLATE32'],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RKTQLATE32'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $callbackPayload);

        $provisional->refresh();
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $provisional->status);
        $this->assertSame('RKTQLATE32', $provisional->mpesa_receipt_number);
    }

    public function test_late_callback_does_not_clobber_invoice_id_or_raised_at(): void
    {
        $provisional = MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'checkout_request_id' => 'ws_CO_NO_CLOBBER',
            'msisdn' => '254712345678',
            'trans_amount' => '1500.00',
            'bill_ref_number' => 'INV-33',
            'raised_at' => now()->subMinutes(5),
            'resolved_at' => now()->subMinutes(1),
        ]);

        $callbackPayload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-33333-1',
                    'CheckoutRequestID' => 'ws_CO_NO_CLOBBER',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 1500.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQNOCL33'],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RKTQNOCL33'],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/api/mpesa/confirmation', $callbackPayload);

        $provisional->refresh();
        $this->assertNull($provisional->invoice_id);
        $this->assertNotNull($provisional->raised_at);
        $this->assertSame('RKTQNOCL33', $provisional->mpesa_receipt_number);
    }
}
