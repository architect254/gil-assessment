<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Invoice;
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

        // Create transactions directly via C2B callback payload (the only way to create them now)
        $this->postJson('/api/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TX1',
            'TransTime' => '20260822143015',
            'TransAmount' => '500.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-' . $invoice->no,
            'MSISDN' => '254712345678',
            'FirstName' => 'Test',
            'LastName' => 'User',
        ]);

        $this->postJson('/api/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TX2',
            'TransTime' => '20260822143015',
            'TransAmount' => '1000.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => (string) $invoice->no,
            'MSISDN' => '254712345678',
            'FirstName' => 'Test',
            'LastName' => 'User',
        ]);

        $this->postJson('/api/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'TX3',
            'TransTime' => '20260822143015',
            'TransAmount' => '999.00',
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-999',
            'MSISDN' => '254712345678',
            'FirstName' => 'Test',
            'LastName' => 'User',
        ]);

        $transactions = $this->service->getTransactionsForInvoice($invoice);

        $this->assertCount(2, $transactions);
        $this->assertTrue($transactions->contains('transaction_id', 'TX1'));
        $this->assertTrue($transactions->contains('transaction_id', 'TX2'));
        $this->assertFalse($transactions->contains('transaction_id', 'TX3'));
    }
}
