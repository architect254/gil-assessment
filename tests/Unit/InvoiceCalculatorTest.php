<?php

namespace Tests\Unit;

use App\Services\InvoiceCalculator;
use PHPUnit\Framework\TestCase;

class InvoiceCalculatorTest extends TestCase
{
    public function test_line_price_after_calculates_with_3_decimal_precision(): void
    {
        // 100.000 with 15% discount -> 85.000
        $this->assertSame(85.0, InvoiceCalculator::linePriceAfter(100.0, 15.0));

        // 33.333 with 10% discount -> 30.000
        $this->assertSame(30.0, InvoiceCalculator::linePriceAfter(33.333, 10.0));

        // 12.345 with 33.333% discount -> round(12.345 * (1 - 0.33333), 3) = 8.230
        $this->assertSame(8.230, InvoiceCalculator::linePriceAfter(12.345, 33.333));
    }

    public function test_line_price_clamps_discount_percentage(): void
    {
        // Negative discount clamped to 0%
        $this->assertSame(100.0, InvoiceCalculator::linePriceAfter(100.0, -10.0));

        // Discount > 100% clamped to 100%
        $this->assertSame(0.0, InvoiceCalculator::linePriceAfter(100.0, 150.0));
    }

    public function test_line_total_multiplies_quantity_and_discounted_price(): void
    {
        // 3 units at 100.000 with 10% discount -> 3 * 90.000 = 270.000
        $this->assertSame(270.0, InvoiceCalculator::lineTotal(3.0, 100.0, 10.0));

        // 2.500 units at 45.555 with 5% discount -> 2.5 * 43.277 = 108.193
        $this->assertSame(108.193, InvoiceCalculator::lineTotal(2.5, 45.555, 5.0));
    }

    public function test_approval_threshold_boundary_evaluation(): void
    {
        // Exactly at or below 10,000.000 does NOT require approval
        $this->assertFalse(InvoiceCalculator::needsApproval(9999.999));
        $this->assertFalse(InvoiceCalculator::needsApproval(10000.000));
        $this->assertFalse(InvoiceCalculator::needsApproval('10000.000'));
        $this->assertFalse(InvoiceCalculator::needsApproval(0));

        // Strictly exceeding 10,000.000 REQUIRES approval
        $this->assertTrue(InvoiceCalculator::needsApproval(10000.001));
        $this->assertTrue(InvoiceCalculator::needsApproval('10000.001'));
        $this->assertTrue(InvoiceCalculator::needsApproval(10001.000));
        $this->assertTrue(InvoiceCalculator::needsApproval(50000.000));
    }

    public function test_totals_computes_before_discount_and_after_correctly(): void
    {
        $rows = [
            ['quantity' => 2.0, 'price_before_discount' => 500.0, 'discount' => 10.0],
            ['quantity' => 1.0, 'price_before_discount' => 1000.0, 'discount' => 0.0],
        ];

        $totals = InvoiceCalculator::totals($rows);

        // Before: (2 * 500) + (1 * 1000) = 2000.000
        // Line 1 after: 2 * 450 = 900.000
        // Line 2 after: 1 * 1000 = 1000.000
        // After: 1900.000
        // Discount: 100.000
        $this->assertSame(2000.0, $totals['before']);
        $this->assertSame(1900.0, $totals['after']);
        $this->assertSame(100.0, $totals['discount']);
    }

    public function test_compute_lines_formats_and_skips_blank_rows(): void
    {
        $rows = [
            [
                'item_code' => 'ITM001',
                'item_description' => 'Laptop',
                'quantity' => 2.500,
                'price_before_discount' => 1200.000,
                'discount' => 5.000,
            ],
            [
                'item_code' => '',
                'item_description' => '',
                'quantity' => 0,
                'price_before_discount' => 0,
                'discount' => 0,
            ],
        ];

        [$lines, $totals] = InvoiceCalculator::computeLines($rows);

        $this->assertCount(1, $lines);
        $this->assertSame(1, $lines[0]['line_no']);
        $this->assertSame('ITM001', $lines[0]['item_code']);
        $this->assertSame('Laptop', $lines[0]['item_description']);
        $this->assertSame('2.500', $lines[0]['quantity']);
        $this->assertSame('1200.000', $lines[0]['price_before_discount']);
        $this->assertSame('5.000', $lines[0]['discount']);
        $this->assertSame('1140.000', $lines[0]['price_after_discount']);
        $this->assertSame('2850.000', $lines[0]['total']);

        $this->assertSame(3000.0, $totals['before']);
        $this->assertSame(2850.0, $totals['after']);
        $this->assertSame(150.0, $totals['discount']);
    }

    public function test_format_formats_to_exact_3_decimals(): void
    {
        $this->assertSame('100.000', InvoiceCalculator::format(100));
        $this->assertSame('12.345', InvoiceCalculator::format(12.345));
        $this->assertSame('0.000', InvoiceCalculator::format(0));
    }
}
