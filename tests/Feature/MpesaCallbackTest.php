<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
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
        $response = $this->postJson('/api/c2b/confirmation', $this->payload());

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

    public function test_validation_endpoint_accepts_transaction_within_invoice_total(): void
    {
        $customer = Customer::create(['code' => 'CUST001', 'name' => 'Acme Corp']);
        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'total_after_discount' => 2000.000,
        ]);

        $payload = $this->payload('VAL001');
        $payload['BillRefNumber'] = 'INV-' . $invoice->no;
        $payload['TransAmount'] = '1500.00';

        $response = $this->postJson('/api/c2b/validation', $payload);

        $response->assertOk()->assertJsonPath('ResultCode', '0');
        $response->assertJsonPath('ResultDesc', 'Accepted');
    }

    public function test_validation_does_not_create_transaction_record(): void
    {
        $customer = Customer::create(['code' => 'CUST001', 'name' => 'Acme Corp']);
        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'total_after_discount' => 2000.000,
        ]);

        $payload = $this->payload('VAL002');
        $payload['BillRefNumber'] = 'INV-' . $invoice->no;
        $payload['TransAmount'] = '1000.00';

        $this->postJson('/api/c2b/validation', $payload)->assertOk();

        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_validation_rejects_unknown_invoice(): void
    {
        $payload = $this->payload('VAL003');
        $payload['BillRefNumber'] = 'INV-NONEXISTENT';
        $payload['TransAmount'] = '1000.00';

        $response = $this->postJson('/api/c2b/validation', $payload);

        $response->assertOk()->assertJsonPath('ResultCode', 'C2B00012');
        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_validation_rejects_amount_above_invoice_total(): void
    {
        $customer = Customer::create(['code' => 'CUST001', 'name' => 'Acme Corp']);
        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'total_after_discount' => 1000.000,
        ]);

        $payload = $this->payload('VAL004');
        $payload['BillRefNumber'] = 'INV-' . $invoice->no;
        $payload['TransAmount'] = '1500.00';

        $response = $this->postJson('/api/c2b/validation', $payload);

        $response->assertOk()->assertJsonPath('ResultCode', 'C2B00013');
        $this->assertSame(0, MpesaTransaction::count());
    }

    public function test_duplicate_callbacks_are_idempotent(): void
    {
        $this->postJson('/api/c2b/confirmation', $this->payload());
        $this->postJson('/api/c2b/confirmation', $this->payload());

        $this->assertSame(1, MpesaTransaction::query()->where('transaction_id', 'SBX12345ABC')->count());
    }

    public function test_lookup_endpoint_returns_transaction_data(): void
    {
        $this->postJson('/api/c2b/confirmation', $this->payload());

        $response = $this->getJson('/api/c2b/transactions/SBX12345ABC');

        $response->assertOk()
            ->assertJsonPath('data.transaction_id', 'SBX12345ABC')
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.customer', 'John Doe')
            ->assertJsonPath('data.status', 'success');
    }

    public function test_callback_secret_is_enforced_when_configured(): void
    {
        config(['mpesa.callback_secret' => 'topsecret']);

        $this->postJson('/api/c2b/confirmation', $this->payload())
            ->assertStatus(401);

        $this->withHeader('X-Callback-Secret', 'topsecret')
            ->postJson('/api/c2b/confirmation', $this->payload())
            ->assertOk();

        $this->assertSame(1, MpesaTransaction::count());
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        $payload = $this->payload('DUP12345');

        $res1 = $this->postJson('/api/c2b/confirmation', $payload);
        $res1->assertOk()->assertJsonPath('ResultCode', 0);
        $this->assertSame(1, MpesaTransaction::query()->where('transaction_id', 'DUP12345')->count());

        $payload['TransAmount'] = '2000.00';
        $res2 = $this->postJson('/api/c2b/confirmation', $payload);
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

        $response = $this->postJson('/api/c2b/confirmation', $malformed);

        $response->assertOk()
            ->assertJsonPath('ResultCode', 0)
            ->assertJsonPath('ResultDesc', 'Accepted');

        $this->assertSame(1, MpesaTransaction::count());
        $this->assertNotNull(MpesaTransaction::first()->raw_payload);
    }
}
