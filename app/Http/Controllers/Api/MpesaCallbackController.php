<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaCallbackController extends Controller
{
    public function validation(Request $request): JsonResponse
    {
        return $this->handle($request);
    }

    public function confirmation(Request $request): JsonResponse
    {
        return $this->handle($request);
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

    private function handle(Request $request): JsonResponse
    {
        $secret = config('mpesa.callback_secret');

        if ($secret !== null && $secret !== '' && $request->header('X-Callback-Secret') !== $secret) {
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Rejected: invalid callback secret',
            ], 401);
        }

        $payload = $request->all();

        try {
            $transaction = MpesaTransaction::fromCallback($payload);

            if (filled($transaction->checkout_request_id)) {
                $existing = MpesaTransaction::query()
                    ->where('checkout_request_id', $transaction->checkout_request_id)
                    ->first();

                if ($existing) {
                    if (MpesaTransaction::isTransitionAllowed($existing->status, $transaction->status)) {
                        // Allowed transition: merge callback fields, exclude invoice_id/raised_at to prevent nullification
                        $existing->update(array_merge(
                            collect($transaction->getAttributes())
                                ->except(['invoice_id', 'raised_at'])
                                ->toArray(),
                            ['resolved_at' => now()],
                        ));
                    } else {
                        // Late callback at already-settled transaction: backfill receipt + metadata only, no status change
                        $existing->update(collect($transaction->only([
                            'mpesa_receipt_number',
                            'trans_time',
                            'trans_amount',
                            'org_account_balance',
                            'first_name',
                            'middle_name',
                            'last_name',
                            'raw_payload',
                        ]))->filter()->toArray());
                    }
                } else {
                    // No existing record — first time seeing this checkout_request_id
                    $transaction->resolved_at = now();
                    $transaction->save();
                }
            } elseif (filled($transaction->transaction_id)) {
                $existing = MpesaTransaction::query()
                    ->where('transaction_id', $transaction->transaction_id)
                    ->first();

                if ($existing) {
                    if (MpesaTransaction::isTransitionAllowed($existing->status, $transaction->status)) {
                        $existing->update(array_merge(
                            collect($transaction->getAttributes())
                                ->except(['invoice_id', 'raised_at'])
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
}
