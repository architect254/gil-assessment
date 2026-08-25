<?php

namespace Tests\Feature;

use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Models\User;
use App\Services\MpesaService;
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

    public function test_can_trigger_simulated_payment_from_invoices_table(): void
    {
        Livewire::actingAs($this->user)
            ->test(ListInvoices::class)
            ->callTableAction('payMpesa', $this->invoice, data: [
                'mode' => 'simulate',
                'phone_number' => '0712345678',
                'amount' => 5000.00,
                'bill_ref_number' => 'INV-' . $this->invoice->no,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('mpesa_transactions', [
            'msisdn' => '254712345678',
            'trans_amount' => '5000.00',
            'bill_ref_number' => 'INV-' . $this->invoice->no,
            'first_name' => 'John',
        ]);
    }

    public function test_can_trigger_simulated_payment_from_view_invoice_page(): void
    {
        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
            ->callAction('payMpesa', data: [
                'mode' => 'simulate',
                'phone_number' => '0722000111',
                'amount' => 2500.00,
                'bill_ref_number' => 'INV-' . $this->invoice->no,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('mpesa_transactions', [
            'msisdn' => '254722000111',
            'trans_amount' => '2500.00',
            'bill_ref_number' => 'INV-' . $this->invoice->no,
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
                'mode' => 'stk_push',
                'phone_number' => '0712345678',
                'amount' => 5000.00,
                'bill_ref_number' => 'INV-' . $this->invoice->no,
            ])
            ->assertHasNoActionErrors();

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest') {
                $data = $request->data();
                return $data['PhoneNumber'] === '254712345678'
                    && $data['AccountReference'] === 'INV-' . $this->invoice->no;
            }
            return true;
        });
    }

    public function test_invoice_infolist_displays_linked_mpesa_transactions(): void
    {
        $service = app(MpesaService::class);
        $service->simulatePayment('0712345678', 5000.00, 'INV-' . $this->invoice->no, 'TXN_INFOLIST_1');

        Livewire::actingAs($this->user)
            ->test(ViewInvoice::class, ['record' => $this->invoice->getRouteKey()])
            ->assertSee('M-Pesa Payments')
            ->assertSee('TXN_INFOLIST_1')
            ->assertSee('5,000.000');
    }
}
