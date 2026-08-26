<?php

namespace Tests\Feature;

use App\Filament\Resources\MpesaTransactions\Pages\ListMpesaTransactions;
use App\Filament\Resources\MpesaTransactions\Pages\ViewMpesaTransaction;
use App\Models\MpesaTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MpesaTransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_transactions_page_requires_authentication(): void
    {
        $this->get('/admin/mpesa-transactions')->assertRedirect();
    }

    public function test_authenticated_user_can_access_transactions_list(): void
    {
        $this->actingAs($this->user)
            ->get('/admin/mpesa-transactions')
            ->assertOk();
    }

    public function test_transactions_table_renders_records_and_columns(): void
    {
        // Create a transaction via the C2B confirmation callback
        $this->postJson('/api/mpesa/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TX_RENDER_1',
            'TransTime' => '20260822143015',
            'TransAmount' => '3500.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-42',
            'MSISDN' => '254712345678',
            'FirstName' => 'Wanjiku',
            'LastName' => 'Njoroge',
        ]);

        $tx = MpesaTransaction::query()->where('transaction_id', 'TX_RENDER_1')->first();

        Livewire::actingAs($this->user)
            ->test(ListMpesaTransactions::class)
            ->assertCanSeeTableRecords([$tx])
            ->assertTableColumnExists('transaction_id')
            ->assertTableColumnExists('status')
            ->assertTableColumnExists('trans_amount')
            ->assertTableColumnExists('msisdn')
            ->assertTableColumnExists('bill_ref_number')
            ->assertTableColumnExists('first_name')
            ->assertTableColumnExists('created_at')
            ->assertSee('TX_RENDER_1')
            ->assertSee('3,500.00')
            ->assertSee('254712345678')
            ->assertSee('INV-42')
            ->assertSee('Wanjiku Njoroge');
    }

    public function test_header_action_can_dispatch_live_stk_push(): void
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
                'access_token' => 'mocked_token',
            ], 200),
            'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest' => Http::response([
                'MerchantRequestID' => 'REQ-TEST-1',
                'CheckoutRequestID' => 'ws_CO_TEST_LIVE',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Accepted',
            ], 200),
        ]);

        Livewire::actingAs($this->user)
            ->test(ListMpesaTransactions::class)
            ->callAction('testPayment', data: [
                'phone_number' => '0712345678',
                'amount' => 200.00,
                'bill_ref_number' => 'SANDBOX-LIVE',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('mpesa_transactions', [
            'msisdn' => '254712345678',
            'trans_amount' => '200.00',
            'bill_ref_number' => 'SANDBOX-LIVE',
            'status' => MpesaTransaction::STATUS_PENDING,
            'checkout_request_id' => 'ws_CO_TEST_LIVE',
            'merchant_request_id' => 'REQ-TEST-1',
        ]);

        $transaction = MpesaTransaction::query()
            ->where('checkout_request_id', 'ws_CO_TEST_LIVE')
            ->first();

        $this->assertNotNull($transaction->raw_payload);
        $decoded = json_decode($transaction->raw_payload, true);
        $this->assertSame('ws_CO_TEST_LIVE', $decoded['CheckoutRequestID']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        });
    }

    public function test_view_transaction_page_renders_payload_details(): void
    {
        // Create a transaction via the C2B confirmation callback
        $this->postJson('/api/mpesa/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TX_VIEW_DETAIL_1',
            'TransTime' => '20260822143015',
            'TransAmount' => '7500.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-99',
            'MSISDN' => '254712345678',
            'FirstName' => 'Test',
            'LastName' => 'User',
        ]);

        $tx = MpesaTransaction::query()->where('transaction_id', 'TX_VIEW_DETAIL_1')->first();

        Livewire::actingAs($this->user)
            ->test(ViewMpesaTransaction::class, ['record' => $tx->getRouteKey()])
            ->assertSee('Transaction Overview')
            ->assertSee('TX_VIEW_DETAIL_1')
            ->assertSee('7,500.00')
            ->assertSee('254712345678')
            ->assertSee('Daraja API Response')
            ->assertSee('Raw Daraja Webhook Payload');
    }
}
