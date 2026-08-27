<?php

namespace App\Models;

use App\Services\MpesaService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'status',
    'created_by',
])]
class Invoice extends Model
{
    use HasFactory;

    public const APPROVAL_THRESHOLD = 10000;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PARTIALLY_PAID = 'partially_paid';
    public const STATUS_PAID = 'paid';

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'total_before_discount' => 'decimal:3',
            'discount' => 'decimal:3',
            'total_after_discount' => 'decimal:3',
            'needs_approval' => 'boolean',
            'status' => 'string',
        ];
    }

    /**
     * Recompute the invoice payment status from its settled M-Pesa transactions.
     *
     * Paid when the sum of successful transactions equals or exceeds the total,
     * partially paid when a positive amount less than the total has been received,
     * unpaid otherwise.
     */
    public function recomputePaymentStatus(): string
    {
        $total = (float) $this->total_after_discount;
        $paid = (float) app(MpesaService::class)->settledAmountForInvoice($this);

        $status = $paid >= $total
            ? static::STATUS_PAID
            : ($paid > 0 ? static::STATUS_PARTIALLY_PAID : static::STATUS_UNPAID);

        $this->update(['status' => $status]);

        return $status;
    }

    /**
     * Whether this invoice is fully paid.
     */
    public function isPaid(): bool
    {
        $total = (float) $this->total_after_discount;
        $paid = (float) app(MpesaService::class)->settledAmountForInvoice($this);

        return $paid >= $total;
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

    /**
     * Create an invoice with sequential numbering and retry protection on concurrency collisions.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $lines
     */
    public static function createWithNextNumber(array $attributes, array $lines = [], int $maxRetries = 3): self
    {
        $attempts = 0;
        while (true) {
            $attempts++;
            try {
                return DB::transaction(function () use ($attributes, $lines): Invoice {
                    $attributes['no'] = static::nextNumber();
                    $invoice = static::query()->create($attributes);
                    if (! empty($lines)) {
                        $invoice->lines()->createMany($lines);
                    }

                    return $invoice;
                });
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                $isUniqueViolation = str_contains($msg, 'UNIQUE') ||
                    str_contains($msg, 'Unique') ||
                    str_contains($msg, 'unique') ||
                    str_contains($msg, '23000') ||
                    str_contains($msg, '2627') ||
                    str_contains($msg, '2601');

                if ($attempts < $maxRetries && $isUniqueViolation) {
                    usleep(random_int(5000, 25000));

                    continue;
                }

                throw $e;
            }
        }
    }
}
