<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    /**
     * Validate a C2B payment before the money moves.
     *
     * Read-only: no transaction record is written here. The purpose is to
     * accept or reject a payment based on the referenced invoice before
     * Safaricom lets the transaction proceed. Only fires when External
     * Validation is enabled on the shortcode (disabled by default).
     */
    public function validation(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('M-Pesa validation received', [
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'payload' => $payload,
        ]);

        $invoice = app(MpesaService::class)
            ->findInvoiceForReference((string) ($payload['BillRefNumber'] ?? ''));

        if (! $invoice) {
            return $this->validationResponse('C2B00012', 'Rejected');
        }

        $amount = (float) ($payload['TransAmount'] ?? 0);
        $total = (float) $invoice->total_after_discount;

        if ($amount > $total) {
            return $this->validationResponse('C2B00013', 'Rejected');
        }

        return $this->validationResponse('0', 'Accepted');
    }

    /**
     * Persist a C2B confirmation callback (the authoritative "payment happened"
     * signal) and update the linked invoice's payment status.
     */
    public function confirmation(Request $request): JsonResponse
    {
        $secret = config('mpesa.callback_secret');

        if ($secret !== null && $secret !== '' && $request->header('X-Callback-Secret') !== $secret) {
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Rejected: invalid callback secret',
            ], 401);
        }

        $payload = $request->all();

        Log::info('M-Pesa callback received', [
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'payload' => $payload,
        ]);

        try {
            $transaction = MpesaTransaction::fromCallback($payload);

            if (filled($transaction->transaction_id)) {
                $existing = MpesaTransaction::query()
                    ->where('transaction_id', $transaction->transaction_id)
                    ->first();

                if ($existing) {
                    if (MpesaTransaction::isTransitionAllowed($existing->status, $transaction->status)) {
                        // Allowed transition: merge non-null callback fields only — prevents C2B failure
                        // callbacks (which carry no customer details) from nullifying msisdn, bill_ref, names, etc.
                        $existing->update(array_merge(
                            collect($transaction->getAttributes())
                                ->except(['invoice_id', 'raised_at'])
                                ->filter(fn ($v) => $v !== null)
                                ->toArray(),
                            ['resolved_at' => now()],
                        ));
                    } else {
                        $existing->update($transaction->only([
                            'mpesa_receipt_number',
                            'raw_payload',
                        ]));
                    }
                } else {
                    $transaction->resolved_at = now();
                    $transaction->save();
                }

                $this->linkInvoiceAndRecompute($payload);
            } else {
                $transaction->save();
            }
        } catch (\Throwable $e) {
            Log::warning('M-Pesa callback handling fallback: '.$e->getMessage(), [
                'payload' => $payload,
            ]);

            try {
                MpesaTransaction::query()->create([
                    'raw_payload' => json_encode($payload),
                ]);
            } catch (\Throwable $inner) {
                Log::error('M-Pesa raw payload persistence failed: '.$inner->getMessage());
            }
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }

    /**
     * Look up a stored C2B transaction by its M-Pesa transaction id.
     */
    public function show(string $transactionId): JsonResponse
    {
        $transaction = MpesaTransaction::query()
            ->where('transaction_id', $transactionId)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'transaction_id' => $transaction->transaction_id,
                'status' => $transaction->status,
                'result_code' => $transaction->result_code,
                'result_desc' => $transaction->result_desc,
                'amount' => $transaction->trans_amount,
                'bill_ref_number' => $transaction->bill_ref_number,
                'msisdn' => $transaction->msisdn,
                'customer' => trim(($transaction->first_name ?? '').' '.($transaction->last_name ?? '')),
                'trans_time' => $transaction->trans_time,
                'received_at' => $transaction->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Link the confirmation to its invoice (when resolvable) and refresh the
     * invoice's payment status based on all settled transactions.
     */
    private function linkInvoiceAndRecompute(array $payload): void
    {
        $invoice = app(MpesaService::class)
            ->findInvoiceForReference((string) ($payload['BillRefNumber'] ?? ''));

        if (! $invoice) {
            return;
        }

        MpesaTransaction::query()
            ->where('transaction_id', $payload['TransID'] ?? $payload['TransactionID'] ?? null)
            ->whereNull('invoice_id')
            ->update(['invoice_id' => $invoice->id]);

        $invoice->recomputePaymentStatus();
    }

    private function validationResponse(string $resultCode, string $resultDesc): JsonResponse
    {
        return response()->json([
            'ResultCode' => $resultCode,
            'ResultDesc' => $resultDesc,
        ]);
    }
}
