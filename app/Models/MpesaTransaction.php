<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'status',
    'invoice_id',
    'raised_at',
    'resolved_at',
    'mpesa_receipt_number',
    'transaction_type',
    'transaction_id',
    'result_code',
    'result_desc',
    'trans_time',
    'trans_amount',
    'business_short_code',
    'bill_ref_number',
    'invoice_number',
    'org_account_balance',
    'third_party_trans_id',
    'msisdn',
    'first_name',
    'middle_name',
    'last_name',
    'raw_payload',
])]
class MpesaTransaction extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_TIMEOUT = 'timeout';

    public const METHOD_C2B = 'c2b';

    /**
     * Derive transaction status from M-Pesa ResultCode.
     * C2B confirmation callbacks have no ResultCode and are inherently successful.
     */
    public static function resolveStatus(?string $resultCode): string
    {
        if ($resultCode === null) {
            return static::STATUS_SUCCESS;
        }

        return match ($resultCode) {
            '0' => static::STATUS_SUCCESS,
            '1032' => static::STATUS_CANCELLED,
            '1037' => static::STATUS_TIMEOUT,
            default => static::STATUS_FAILED,
        };
    }

    /**
     * Determine whether a status transition is allowed.
     *
     * Rules:
     *  - pending → anything (first settlement)
     *  - success → success only (idempotent re-apply)
     *  - non-success settled → success (late confirmation)
     *  - everything else → rejected (never regress, never overwrite settled with noise)
     */
    public static function isTransitionAllowed(string $current, string $incoming): bool
    {
        if ($current === static::STATUS_PENDING) {
            return true;
        }

        if ($current === static::STATUS_SUCCESS && $incoming === static::STATUS_SUCCESS) {
            return true;
        }

        if ($current !== static::STATUS_SUCCESS && $incoming === static::STATUS_SUCCESS) {
            return true;
        }

        return false;
    }

    /**
     * Scope: transactions marked success but missing a receipt number (provisional success).
     */
    public static function isReceiptPending(): Builder
    {
        return static::query()
            ->where('status', static::STATUS_SUCCESS)
            ->whereNull('mpesa_receipt_number');
    }

    /**
     * Derive payment method. C2B is the only supported channel.
     */
    protected function paymentMethod(): Attribute
    {
        return Attribute::get(fn () => static::METHOD_C2B);
    }

    /**
     * Map a raw M-Pesa C2B callback payload to model attributes.
     */
    public static function fromCallback(array $payload): self
    {
        $string = static fn (mixed $value): ?string => $value === null ? null : (string) $value;

        $resultCode = $string(data_get($payload, 'ResultCode'));

        return new static([
            'status' => static::resolveStatus($resultCode),
            'transaction_type' => $string(data_get($payload, 'TransactionType')),
            'transaction_id' => $string(
                data_get($payload, 'TransactionID')
                ?? data_get($payload, 'TransID')
            ),
            'result_code' => $resultCode,
            'result_desc' => $string(data_get($payload, 'ResultDesc')),
            'trans_time' => $string(data_get($payload, 'TransTime')),
            'trans_amount' => $string(
                data_get($payload, 'TransAmount')
                ?? data_get($payload, 'Amount')
            ),
            'business_short_code' => $string(data_get($payload, 'BusinessShortCode')),
            'bill_ref_number' => $string(data_get($payload, 'BillRefNumber')),
            'invoice_number' => $string(data_get($payload, 'InvoiceNumber')),
            'org_account_balance' => $string(data_get($payload, 'OrgAccountBalance')),
            'third_party_trans_id' => $string(data_get($payload, 'ThirdPartyTransID')),
            'msisdn' => $string(data_get($payload, 'MSISDN')),
            'first_name' => $string(data_get($payload, 'FirstName')),
            'middle_name' => $string(data_get($payload, 'MiddleName')),
            'last_name' => $string(data_get($payload, 'LastName')),
            'mpesa_receipt_number' => $string(
                data_get($payload, 'MpesaReceiptNumber')
                ?? data_get($payload, 'TransID')
                ?? data_get($payload, 'TransactionID')
            ),
            'raw_payload' => json_encode($payload),
        ]);
    }
}
