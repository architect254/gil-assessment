<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
    'checkout_request_id',
    'merchant_request_id',
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

    /**
     * Derive transaction status from M-Pesa ResultCode.
     * C2B callbacks (direct paybill) have no ResultCode and are inherently successful.
     */
    public static function resolveStatus(?string $resultCode, bool $isStkCallback = false): string
    {
        if ($resultCode === null) {
            // C2B confirmation callbacks have no ResultCode — they're successful transactions.
            return $isStkCallback ? static::STATUS_PENDING : static::STATUS_SUCCESS;
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
     * Map a raw M-Pesa callback payload to model attributes.
     */
    public static function fromCallback(array $payload): self
    {
        $callback = data_get($payload, 'Body.stkCallback', data_get($payload, 'Body'));

        // STK push callbacks store metadata in CallbackMetadata.items
        if (is_array(data_get($callback, 'CallbackMetadata.items'))) {
            foreach (data_get($callback, 'CallbackMetadata.items', []) as $entry) {
                $callback['fields'][$entry['Name'] ?? ''] = $entry['Value'] ?? null;
            }
        }

        $string = static fn (mixed $value): ?string => $value === null ? null : (string) $value;

        $resultCode = $string(data_get($callback, 'ResultCode'));
        $checkoutRequestId = $string(data_get($callback, 'CheckoutRequestID'));
        $merchantRequestId = $string(data_get($callback, 'MerchantRequestID'));
        $isStkCallback = data_get($payload, 'Body.stkCallback') !== null;

        return new static([
            'status' => static::resolveStatus($resultCode, $isStkCallback),
            'transaction_type' => $string(data_get($payload, 'TransactionType')),
            'transaction_id' => $string(
                data_get($payload, 'TransactionID')
                ?? data_get($payload, 'TransID')
                ?? data_get($callback, 'TransactionID')
                ?? data_get($callback, 'fields.TransID')
            ),
            'result_code' => $resultCode,
            'result_desc' => $string(data_get($callback, 'ResultDesc')),
            'checkout_request_id' => $checkoutRequestId,
            'merchant_request_id' => $merchantRequestId,
            'trans_time' => $string(
                data_get($payload, 'TransTime')
                ?? data_get($callback, 'TransTime')
            ),
            'trans_amount' => $string(
                data_get($payload, 'TransAmount')
                ?? data_get($callback, 'TransAmount')
                ?? data_get($callback, 'fields.TransAmount')
                ?? data_get($callback, 'fields.Amount')
            ),
            'business_short_code' => $string(
                data_get($payload, 'BusinessShortCode')
                ?? data_get($callback, 'BusinessShortCode')
            ),
            'bill_ref_number' => $string(
                data_get($payload, 'BillRefNumber')
                ?? data_get($callback, 'BillRefNumber')
            ),
            'invoice_number' => $string(
                data_get($payload, 'InvoiceNumber')
                ?? data_get($callback, 'InvoiceNumber')
            ),
            'org_account_balance' => $string(
                data_get($payload, 'OrgAccountBalance')
                ?? data_get($callback, 'OrgAccountBalance')
                ?? data_get($callback, 'fields.OrgAccountBalance')
            ),
            'third_party_trans_id' => $string(
                data_get($payload, 'ThirdPartyTransID')
                ?? data_get($callback, 'ThirdPartyTransID')
            ),
            'msisdn' => $string(
                data_get($payload, 'MSISDN')
                ?? data_get($callback, 'MSISDN')
                ?? data_get($callback, 'fields.Msisdn')
            ),
            'first_name' => $string(
                data_get($payload, 'FirstName')
                ?? data_get($callback, 'FirstName')
                ?? data_get($callback, 'fields.FirstName')
            ),
            'middle_name' => $string(
                data_get($payload, 'MiddleName')
                ?? data_get($callback, 'MiddleName')
                ?? data_get($callback, 'fields.MiddleName')
            ),
            'last_name' => $string(
                data_get($payload, 'LastName')
                ?? data_get($callback, 'LastName')
                ?? data_get($callback, 'fields.LastName')
            ),
            'mpesa_receipt_number' => $string(
                data_get($callback, 'fields.MpesaReceiptNumber')
                ?? data_get($callback, 'MpesaReceiptNumber')
            ),
            'raw_payload' => json_encode($payload),
        ]);
    }
}
