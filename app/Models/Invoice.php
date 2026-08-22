<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'no',
    'customer_id',
    'posting_date',
    'sales_employee_id',
    'remarks',
    'total_before_discount',
    'discount',
    'total_after_discount',
    'needs_approval',
    'created_by',
])]
class Invoice extends Model
{
    use HasFactory;

    public const APPROVAL_THRESHOLD = 10000;

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'total_before_discount' => 'decimal:3',
            'discount' => 'decimal:3',
            'total_after_discount' => 'decimal:3',
            'needs_approval' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesEmployee(): BelongsTo
    {
        return $this->belongsTo(SalesEmployee::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Next sequential document number.
     */
    public static function nextNumber(): int
    {
        return (int) (static::query()->max('no') ?? 0) + 1;
    }
}
