<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class MpesaServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MpesaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MpesaService();
    }

    public function test_normalizes_various_valid_kenyan_phone_numbers(): void
    {
        $this->assertSame('254712345678', $this->service->normalizePhoneNumber('0712345678'));
        $this->assertSame('254712345678', $this->service->normalizePhoneNumber('+254712345678'));
        $this->assertSame('254712345678', $this->service->normalizePhoneNumber('254712345678'));
        $this->assertSame('254712345678', $this->service->normalizePhoneNumber('0712-345-678'));
        $this->assertSame('254712345678', $this->service->normalizePhoneNumber(' 0712 345 678 '));
        $this->assertSame('254112345678', $this->service->normalizePhoneNumber('0112345678'));
        $this->assertSame('254112345678', $this->service->normalizePhoneNumber('+254112345678'));
    }

    public function test_throws_exception_on_invalid_phone_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->normalizePhoneNumber('12345');
    }

    public function test_throws_exception_on_non_kenyan_phone_numbers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->normalizePhoneNumber('256712345678');
    }

    public function test_generate_daraja_token_returns_token_on_success(): void
    {
        config([
            'mpesa.consumer_key' => 'test_key',
            'mpesa.consumer_secret' => 'test_secret',
            'mpesa.environment' => 'sandbox',
        ]);

        Http::fake([
            'https://sandbox.safaricom.co.ke/oauth/v1/generate*' => Http::response([
                'access_token' => 'mocked_daraja_token_xyz',
                'expires_in' => '3599',
            ], 200),
        ]);

        $token = $this->service->generateDarajaToken();

        $this->assertSame('mocked_daraja_token_xyz', $token);
    }

    public function test_generate_daraja_token_throws_when_credentials_missing(): void
    {
        config([
            'mpesa.consumer_key' => '',
            'mpesa.consumer_secret' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->service->generateDarajaToken();
    }

    public function test_send_stk_push_dispatches_expected_payload(): void
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
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_DMZ_12321_23423476',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ], 200),
        ]);

        $response = $this->service->sendStkPush(
            phone: '0712345678',
            amount: 2500.50,
            reference: 'INV-101',
            description: 'Invoice 101'
        );

        $this->assertSame('0', $response['ResponseCode']);
        $this->assertSame('ws_CO_DMZ_12321_23423476', $response['CheckoutRequestID']);

        Http::assertSent(function ($request) {
            if ($request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest') {
                $data = $request->data();
                return $data['BusinessShortCode'] === '174379'
                    && $data['PhoneNumber'] === '254712345678'
                    && $data['Amount'] === 2501
                    && $data['AccountReference'] === 'INV-101';
            }
            return true;
        });
    }

    public function test_simulate_payment_creates_persisted_transaction(): void
    {
        $transaction = $this->service->simulatePayment(
            phone: '0712345678',
            amount: 5000.00,
            billRef: 'INV-5',
            transId: 'SIM1234567',
            firstName: 'Alice',
            lastName: 'Smith'
        );

        $this->assertInstanceOf(MpesaTransaction::class, $transaction);
        $this->assertSame('SIM1234567', $transaction->transaction_id);
        $this->assertSame('254712345678', $transaction->msisdn);
        $this->assertSame('5000.00', $transaction->trans_amount);
        $this->assertSame('INV-5', $transaction->bill_ref_number);
        $this->assertSame('Alice', $transaction->first_name);

        $this->assertDatabaseHas('mpesa_transactions', [
            'transaction_id' => 'SIM1234567',
            'msisdn' => '254712345678',
            'trans_amount' => '5000.00',
            'bill_ref_number' => 'INV-5',
        ]);
    }

    public function test_find_invoice_for_reference_finds_by_raw_or_prefixed_number(): void
    {
        $customer = Customer::create([
            'code' => 'CUST001',
            'name' => 'Acme Corp',
        ]);

        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'total_after_discount' => 1500.000,
        ]);

        $foundByPrefix = $this->service->findInvoiceForReference('INV-' . $invoice->no);
        $foundByDirect = $this->service->findInvoiceForReference((string) $invoice->no);
        $foundNone = $this->service->findInvoiceForReference('INV-99999');

        $this->assertNotNull($foundByPrefix);
        $this->assertSame($invoice->id, $foundByPrefix->id);

        $this->assertNotNull($foundByDirect);
        $this->assertSame($invoice->id, $foundByDirect->id);

        $this->assertNull($foundNone);
    }

    public function test_get_transactions_for_invoice_retrieves_matched_transactions(): void
    {
        $customer = Customer::create([
            'code' => 'CUST001',
            'name' => 'Acme Corp',
        ]);

        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'total_after_discount' => 1500.000,
        ]);

        $this->service->simulatePayment('0712345678', 500.00, 'INV-' . $invoice->no, 'TX1');
        $this->service->simulatePayment('0712345678', 1000.00, (string) $invoice->no, 'TX2');
        $this->service->simulatePayment('0712345678', 999.00, 'INV-999', 'TX3');

        $transactions = $this->service->getTransactionsForInvoice($invoice);

        $this->assertCount(2, $transactions);
        $this->assertTrue($transactions->contains('transaction_id', 'TX1'));
        $this->assertTrue($transactions->contains('transaction_id', 'TX2'));
        $this->assertFalse($transactions->contains('transaction_id', 'TX3'));
    }
}
