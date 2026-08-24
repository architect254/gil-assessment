<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'transaction_type',
    'transaction_id',
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

    /**
     * Map a raw M-Pesa C2B callback payload to model attributes.
     * Every field is stored as a string.
     */
    public static function fromCallback(array $payload): self
    {
        $callback = data_get($payload, 'Body.stkCallback', data_get($payload, 'Body'));

        if (is_array(data_get($callback, 'CallbackMetadata.items'))) {
            // STK push style: items is a list of {Name, Value}
            foreach (data_get($callback, 'CallbackMetadata.items', []) as $entry) {
                $callback['fields'][$entry['Name'] ?? ''] = $entry['Value'] ?? null;
            }
        }

        $string = static fn (mixed $value): ?string => $value === null ? null : (string) $value;

        return new static([
            'transaction_type' => $string(data_get($payload, 'TransactionType')),
            'transaction_id' => $string(
                data_get($payload, 'TransactionID')
                ?? data_get($payload, 'TransID')
                ?? data_get($callback, 'TransactionID')
                ?? data_get($callback, 'fields.TransID')
            ),
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
            'raw_payload' => json_encode($payload),
        ]);
    }
}
