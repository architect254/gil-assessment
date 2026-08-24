<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MpesaTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                'amount' => $transaction->trans_amount,
                'bill_ref_number' => $transaction->bill_ref_number,
                'msisdn' => $transaction->msisdn,
                'customer' => trim(($transaction->first_name ?? '') . ' ' . ($transaction->last_name ?? '')),
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

        // Idempotent upsert – M-Pesa may retry callbacks.
        $transaction = MpesaTransaction::fromCallback($payload);

        if (filled($transaction->transaction_id)) {
            MpesaTransaction::query()->updateOrCreate(
                ['transaction_id' => $transaction->transaction_id],
                $transaction->getAttributes(),
            );
        } else {
            $transaction->save();
        }

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }
}
