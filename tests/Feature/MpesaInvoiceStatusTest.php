<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MpesaTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaInvoiceStatusTest extends TestCase
{
    use RefreshDatabase;

    private function createInvoice(float $total): array
    {
        $customer = Customer::create(['code' => 'CUST001', 'name' => 'Acme Corp']);

        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'total_after_discount' => $total,
        ]);

        return [$customer, $invoice];
    }

    private function confirm(int $invoiceNo, string $transId, string $amount): void
    {
        $this->postJson('/api/c2b/confirmation', [
            'TransactionType' => 'Pay Bill',
            'TransID' => $transId,
            'TransTime' => '20260822143015',
            'TransAmount' => $amount,
            'BusinessShortCode' => '174379',
            'BillRefNumber' => 'INV-' . $invoiceNo,
            'MSISDN' => '254712345678',
            'FirstName' => 'John',
            'LastName' => 'Doe',
        ])->assertOk();
    }

    public function test_single_full_payment_sets_invoice_paid(): void
    {
        [, $invoice] = $this->createInvoice(5000);

        $this->confirm($invoice->no, 'TX_PAID_1', '5000.00');

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_partial_payment_sets_invoice_partially_paid(): void
    {
        [, $invoice] = $this->createInvoice(5000);

        $this->confirm($invoice->no, 'TX_PART_1', '2000.00');

        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->fresh()->status);
    }

    public function test_sum_of_payments_to_total_sets_invoice_paid(): void
    {
        [, $invoice] = $this->createInvoice(5000);

        $this->confirm($invoice->no, 'TX_SUM_1', '3000.00');
        $this->confirm($invoice->no, 'TX_SUM_2', '2000.00');

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_sum_below_total_remains_partially_paid(): void
    {
        [, $invoice] = $this->createInvoice(5000);

        $this->confirm($invoice->no, 'TX_BELOW_1', '3000.00');
        $this->confirm($invoice->no, 'TX_BELOW_2', '1000.00');

        $this->assertSame(Invoice::STATUS_PARTIALLY_PAID, $invoice->fresh()->status);
    }

    public function test_overpayment_marks_invoice_paid(): void
    {
        [, $invoice] = $this->createInvoice(5000);

        $this->confirm($invoice->no, 'TX_OVER_1', '6000.00');

        $this->assertSame(Invoice::STATUS_PAID, $invoice->fresh()->status);
    }

    public function test_confirmation_links_invoice_id_when_resolvable(): void
    {
        [, $invoice] = $this->createInvoice(5000);

        $this->confirm($invoice->no, 'TX_LINK_1', '5000.00');

        $tx = MpesaTransaction::where('transaction_id', 'TX_LINK_1')->first();
        $this->assertNotNull($tx);
        $this->assertSame($invoice->id, $tx->invoice_id);
    }
}
