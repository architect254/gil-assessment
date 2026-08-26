<?php

namespace Tests\Unit;

use App\Models\MpesaTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaTransactionTest extends TestCase
{
    use RefreshDatabase;

    // ── isTransitionAllowed ──────────────────────────────────────

    public function test_is_transition_allowed_pending_to_success(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_PENDING,
            MpesaTransaction::STATUS_SUCCESS,
        ));
    }

    public function test_is_transition_allowed_pending_to_cancelled(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_PENDING,
            MpesaTransaction::STATUS_CANCELLED,
        ));
    }

    public function test_is_transition_allowed_pending_to_failed(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_PENDING,
            MpesaTransaction::STATUS_FAILED,
        ));
    }

    public function test_is_transition_allowed_pending_to_timeout(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_PENDING,
            MpesaTransaction::STATUS_TIMEOUT,
        ));
    }

    public function test_is_transition_allowed_success_to_success(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_SUCCESS,
            MpesaTransaction::STATUS_SUCCESS,
        ));
    }

    public function test_is_transition_allowed_success_to_failed_rejected(): void
    {
        $this->assertFalse(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_SUCCESS,
            MpesaTransaction::STATUS_FAILED,
        ));
    }

    public function test_is_transition_allowed_success_to_cancelled_rejected(): void
    {
        $this->assertFalse(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_SUCCESS,
            MpesaTransaction::STATUS_CANCELLED,
        ));
    }

    public function test_is_transition_allowed_success_to_timeout_rejected(): void
    {
        $this->assertFalse(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_SUCCESS,
            MpesaTransaction::STATUS_TIMEOUT,
        ));
    }

    public function test_is_transition_allowed_failed_to_success(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_FAILED,
            MpesaTransaction::STATUS_SUCCESS,
        ));
    }

    public function test_is_transition_allowed_cancelled_to_success(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_CANCELLED,
            MpesaTransaction::STATUS_SUCCESS,
        ));
    }

    public function test_is_transition_allowed_timeout_to_success(): void
    {
        $this->assertTrue(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_TIMEOUT,
            MpesaTransaction::STATUS_SUCCESS,
        ));
    }

    public function test_is_transition_allowed_failed_to_cancelled_rejected(): void
    {
        $this->assertFalse(MpesaTransaction::isTransitionAllowed(
            MpesaTransaction::STATUS_FAILED,
            MpesaTransaction::STATUS_CANCELLED,
        ));
    }

    // ── resolveStatus ────────────────────────────────────────────

    public function test_resolve_status_timeout_for_1037(): void
    {
        $this->assertSame(
            MpesaTransaction::STATUS_TIMEOUT,
            MpesaTransaction::resolveStatus('1037', isStkCallback: true),
        );
    }

    public function test_resolve_status_success_for_0(): void
    {
        $this->assertSame(
            MpesaTransaction::STATUS_SUCCESS,
            MpesaTransaction::resolveStatus('0'),
        );
    }

    public function test_resolve_status_cancelled_for_1032(): void
    {
        $this->assertSame(
            MpesaTransaction::STATUS_CANCELLED,
            MpesaTransaction::resolveStatus('1032'),
        );
    }

    public function test_resolve_status_failed_for_other_codes(): void
    {
        $this->assertSame(
            MpesaTransaction::STATUS_FAILED,
            MpesaTransaction::resolveStatus('1'),
        );
    }

    public function test_resolve_status_null_stk_returns_pending(): void
    {
        $this->assertSame(
            MpesaTransaction::STATUS_PENDING,
            MpesaTransaction::resolveStatus(null, isStkCallback: true),
        );
    }

    public function test_resolve_status_null_c2b_returns_success(): void
    {
        $this->assertSame(
            MpesaTransaction::STATUS_SUCCESS,
            MpesaTransaction::resolveStatus(null, isStkCallback: false),
        );
    }

    // ── isReceiptPending ─────────────────────────────────────────

    public function test_is_receipt_pending_filters_correctly(): void
    {
        MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'mpesa_receipt_number' => 'HAS_RECEIPT',
            'bill_ref_number' => 'INV-1',
        ]);

        MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_SUCCESS,
            'mpesa_receipt_number' => null,
            'bill_ref_number' => 'INV-2',
        ]);

        MpesaTransaction::create([
            'status' => MpesaTransaction::STATUS_PENDING,
            'mpesa_receipt_number' => null,
            'bill_ref_number' => 'INV-3',
        ]);

        $this->assertSame(1, MpesaTransaction::isReceiptPending()->count());
    }

    // ── fromCallback receipt extraction ──────────────────────────

    public function test_from_callback_extracts_mpesa_receipt_number(): void
    {
        $payload = [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-00001-1',
                    'CheckoutRequestID' => 'ws_CO_UNIT_TEST',
                    'ResultCode' => 0,
                    'ResultDesc' => 'Success',
                    'CallbackMetadata' => [
                        'items' => [
                            ['Name' => 'Amount', 'Value' => 1000.00],
                            ['Name' => 'Msisdn', 'Value' => 254712345678],
                            ['Name' => 'TransID', 'Value' => 'RKTQUNIT01'],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'RKTQUNIT01'],
                        ],
                    ],
                ],
            ],
        ];

        $transaction = MpesaTransaction::fromCallback($payload);

        $this->assertSame('RKTQUNIT01', $transaction->mpesa_receipt_number);
        $this->assertSame(MpesaTransaction::STATUS_SUCCESS, $transaction->status);
    }

    public function test_from_callback_receipt_null_when_not_in_payload(): void
    {
        $payload = [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'SBX_C2B_001',
            'TransTime' => '20260826120000',
            'TransAmount' => '500.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-10',
            'MSISDN' => '254712345678',
            'FirstName' => 'Test',
            'LastName' => 'User',
        ];

        $transaction = MpesaTransaction::fromCallback($payload);

        $this->assertNull($transaction->mpesa_receipt_number);
    }
}
