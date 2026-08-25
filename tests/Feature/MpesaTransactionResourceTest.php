<?php

namespace Tests\Feature;

use App\Filament\Resources\MpesaTransactions\Pages\ListMpesaTransactions;
use App\Filament\Resources\MpesaTransactions\Pages\ViewMpesaTransaction;
use App\Models\MpesaTransaction;
use App\Models\User;
use App\Services\MpesaService;
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
        $service = app(MpesaService::class);
        $tx = $service->simulatePayment(
            phone: '0712345678',
            amount: 3500.00,
            billRef: 'INV-42',
            transId: 'TX_RENDER_1',
            firstName: 'Wanjiku',
            lastName: 'Njoroge'
        );

        Livewire::actingAs($this->user)
            ->test(ListMpesaTransactions::class)
            ->assertCanSeeTableRecords([$tx])
            ->assertTableColumnExists('transaction_id')
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

    public function test_header_action_can_execute_simulated_test_payment(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListMpesaTransactions::class)
            ->callAction('testPayment', data: [
                'mode' => 'simulate',
                'phone_number' => '0711223344',
                'amount' => 1500.00,
                'bill_ref_number' => 'SANDBOX-001',
                'first_name' => 'Demo',
                'last_name' => 'Tester',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('mpesa_transactions', [
            'msisdn' => '254711223344',
            'trans_amount' => '1500.00',
            'bill_ref_number' => 'SANDBOX-001',
            'first_name' => 'Demo',
            'last_name' => 'Tester',
        ]);
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
                'mode' => 'stk_push',
                'phone_number' => '0712345678',
                'amount' => 200.00,
                'bill_ref_number' => 'SANDBOX-LIVE',
            ])
            ->assertHasNoActionErrors();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        });
    }

    public function test_view_transaction_page_renders_payload_details(): void
    {
        $service = app(MpesaService::class);
        $tx = $service->simulatePayment(
            phone: '0712345678',
            amount: 7500.00,
            billRef: 'INV-99',
            transId: 'TX_VIEW_DETAIL_1'
        );

        Livewire::actingAs($this->user)
            ->test(ViewMpesaTransaction::class, ['record' => $tx->getRouteKey()])
            ->assertSee('Transaction Overview')
            ->assertSee('TX_VIEW_DETAIL_1')
            ->assertSee('7,500.00')
            ->assertSee('254712345678')
            ->assertSee('Raw Daraja Webhook Payload');
    }
}
