<?php

namespace Tests\Feature;

use App\Filament\Pages\NewInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\SalesEmployee;
use App\Models\User;
use App\Services\InvoiceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_invoice_page_requires_authentication(): void
    {
        $this->get('/admin/new-invoice')->assertRedirect();
    }

    public function test_authenticated_user_can_access_new_invoice_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/new-invoice')
            ->assertOk();
    }

    public function test_invoice_creation_persists_header_and_lines(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST001',
            'name' => 'Acme Corporation',
        ]);
        $employee = SalesEmployee::query()->create([
            'code' => 'SE001',
            'name' => 'Jane Sales',
        ]);
        $item = Item::query()->create([
            'code' => 'ITM001',
            'description' => 'Industrial Widget',
            'unit_price' => '250.000',
        ]);

        $rows = [
            [
                'item_code' => $item->code,
                'item_description' => $item->description,
                'quantity' => '4.000',
                'price_before_discount' => '250.000',
                'discount' => '10.000',
            ],
        ];

        [$lines, $totals] = InvoiceCalculator::computeLines($rows);

        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'sales_employee_id' => $employee->id,
            'remarks' => 'Urgent priority delivery order',
            'total_before_discount' => InvoiceCalculator::format($totals['before']),
            'discount' => InvoiceCalculator::format($totals['discount']),
            'total_after_discount' => InvoiceCalculator::format($totals['after']),
            'needs_approval' => InvoiceCalculator::needsApproval($totals['after']),
            'created_by' => $user->id,
        ], $lines);

        $this->assertSame(1, $invoice->no);
        $this->assertSame('1000.000', $invoice->total_before_discount);
        $this->assertSame('100.000', $invoice->discount);
        $this->assertSame('900.000', $invoice->total_after_discount);
        $this->assertFalse($invoice->needs_approval);

        $this->assertDatabaseHas('invoices', [
            'no' => 1,
            'customer_id' => $customer->id,
            'remarks' => 'Urgent priority delivery order',
            'needs_approval' => false,
        ]);

        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $invoice->id,
            'item_code' => 'ITM001',
            'quantity' => '4.000',
            'price_after_discount' => '225.000',
            'total' => '900.000',
        ]);
    }

    public function test_invoice_exceeding_ten_thousand_flags_needs_approval(): void
    {
        $user = User::factory()->create();
        $customer = Customer::query()->create([
            'code' => 'CUST002',
            'name' => 'Global Logistics Ltd',
        ]);

        $rows = [
            [
                'item_code' => 'ITM999',
                'item_description' => 'Heavy Turbine',
                'quantity' => '2.000',
                'price_before_discount' => '6000.000',
                'discount' => '0.000',
            ],
        ];

        [$lines, $totals] = InvoiceCalculator::computeLines($rows);

        $invoice = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'remarks' => 'High-value equipment sale',
            'total_before_discount' => InvoiceCalculator::format($totals['before']),
            'discount' => InvoiceCalculator::format($totals['discount']),
            'total_after_discount' => InvoiceCalculator::format($totals['after']),
            'needs_approval' => InvoiceCalculator::needsApproval($totals['after']),
            'created_by' => $user->id,
        ], $lines);

        $this->assertSame('12000.000', $invoice->total_after_discount);
        $this->assertTrue($invoice->needs_approval);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'needs_approval' => true,
        ]);
    }

    public function test_sequential_document_numbering_increments_consecutively(): void
    {
        $customer = Customer::query()->create(['code' => 'CUST003', 'name' => 'Testing Corp']);

        $inv1 = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'remarks' => 'First Order',
            'total_before_discount' => '100.000',
            'discount' => '0.000',
            'total_after_discount' => '100.000',
            'needs_approval' => false,
        ]);

        $inv2 = Invoice::createWithNextNumber([
            'customer_id' => $customer->id,
            'posting_date' => now()->toDateString(),
            'remarks' => 'Second Order',
            'total_before_discount' => '200.000',
            'discount' => '0.000',
            'total_after_discount' => '200.000',
            'needs_approval' => false,
        ]);

        $this->assertSame(1, $inv1->no);
        $this->assertSame(2, $inv2->no);
    }

    public function test_new_invoice_option_is_not_registered_in_navigation(): void
    {
        $this->assertFalse(NewInvoice::shouldRegisterNavigation());
    }

    public function test_invoices_list_page_has_create_header_action_linking_to_new_invoice(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Invoices\Pages\ListInvoices::class)
            ->assertActionExists('create')
            ->assertActionVisible('create');
    }

    public function test_invoice_validation_rules_discount_max_and_remarks_required(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(NewInvoice::class)
            ->fillForm([
                'customer_code' => 'INVALID_CODE',
                'remarks' => '', // empty remarks
                'lines' => [
                    [
                        'item_code' => 'ITM001',
                        'quantity' => 1,
                        'price_before_discount' => 100,
                        'discount' => 55, // > 50% discount
                    ],
                ],
            ])
            ->call('save')
            ->assertHasFormErrors([
                'remarks' => 'required',
                'lines.0.discount',
                'customer_code',
            ]);
    }
}
