<?php

namespace App\Services;

use App\Models\Invoice;

class InvoiceCalculator
{
    public const APPROVAL_THRESHOLD = 10000.000;

    public const MAX_DISCOUNT = 50.000;

    /**
     * Calculate discounted price for a single unit.
     */
    public static function linePriceAfter(float $priceBefore, float $discountPercent): float
    {
        $discountClamped = min(max($discountPercent, 0), 100);

        return round($priceBefore * (1 - ($discountClamped / 100)), 3);
    }

    /**
     * Calculate line total for a given quantity, unit price, and discount percentage.
     */
    public static function lineTotal(float $quantity, float $priceBefore, float $discountPercent): float
    {
        $priceAfter = self::linePriceAfter($priceBefore, $discountPercent);

        return round($quantity * $priceAfter, 3);
    }

    /**
     * Compute totals across an array of row inputs.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{before: float, discount: float, after: float}
     */
    public static function totals(array $rows): array
    {
        $before = 0.0;
        $after = 0.0;

        foreach ($rows as $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $price = (float) ($row['price_before_discount'] ?? 0);
            $discountPercent = (float) ($row['discount'] ?? 0);

            $before += round($quantity * $price, 3);
            $after += self::lineTotal($quantity, $price, $discountPercent);
        }

        $before = round($before, 3);
        $after = round($after, 3);

        return [
            'before' => $before,
            'discount' => round($before - $after, 3),
            'after' => $after,
        ];
    }

    /**
     * Process raw form rows into normalized invoice line items and overall totals.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{0: array<int, array<string, mixed>>, 1: array{before: float, discount: float, after: float}}
     */
    public static function computeLines(array $rows): array
    {
        $lines = [];
        $before = 0.0;
        $after = 0.0;

        foreach ($rows as $index => $row) {
            $quantity = (float) ($row['quantity'] ?? 0);
            $priceBefore = (float) ($row['price_before_discount'] ?? 0);
            $discountPercent = min(max((float) ($row['discount'] ?? 0), 0), 100);

            if ($quantity <= 0 && blank($row['item_code'] ?? null)) {
                continue;
            }

            $priceAfter = self::linePriceAfter($priceBefore, $discountPercent);
            $total = round($quantity * $priceAfter, 3);
            $lineBefore = round($quantity * $priceBefore, 3);

            $before += $lineBefore;
            $after += $total;

            $lines[] = [
                'line_no' => $index + 1,
                'item_code' => trim((string) ($row['item_code'] ?? '')),
                'item_description' => trim((string) ($row['item_description'] ?? '')),
                'quantity' => sprintf('%.3F', $quantity),
                'price_before_discount' => sprintf('%.3F', $priceBefore),
                'discount' => sprintf('%.3F', $discountPercent),
                'price_after_discount' => sprintf('%.3F', $priceAfter),
                'total' => sprintf('%.3F', $total),
            ];
        }

        $before = round($before, 3);
        $after = round($after, 3);

        return [
            $lines,
            [
                'before' => $before,
                'discount' => round($before - $after, 3),
                'after' => $after,
            ],
        ];
    }

    /**
     * Determine whether the given total amount requires approval.
     */
    public static function needsApproval(float|string $totalAfterDiscount): bool
    {
        return (float) $totalAfterDiscount > self::APPROVAL_THRESHOLD;
    }

    /**
     * Format an amount to standard 3 decimal places.
     */
    public static function format(float|string $amount): string
    {
        return sprintf('%.3F', (float) $amount);
    }
}
