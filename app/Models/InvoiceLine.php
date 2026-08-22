<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'item_code',
    'item_description',
    'quantity',
    'price_before_discount',
    'discount',
    'price_after_discount',
    'total',
    'line_no',
])]
class InvoiceLine extends Model
{
    use HasFactory;

    public const MAX_DISCOUNT = 50;

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'price_before_discount' => 'decimal:3',
            'discount' => 'decimal:3',
            'price_after_discount' => 'decimal:3',
            'total' => 'decimal:3',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
