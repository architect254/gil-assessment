<?php

namespace App\Jobs;

use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class QueryStkPushStatus implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 40;

    public int $tries = 3;

    public function __construct(
        public int $mpesaTransactionId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->mpesaTransactionId;
    }

    public function handle(MpesaService $mpesaService): void
    {
        $transaction = MpesaTransaction::find($this->mpesaTransactionId);

        if (! $transaction || $transaction->status !== MpesaTransaction::STATUS_PENDING) {
            return;
        }

        try {
            $response = $mpesaService->queryStkPushStatus(
                $transaction->checkout_request_id
            );

            $resultCode = data_get($response, 'ResultCode');

            if ($resultCode === null) {
                // Still pending — release back to queue if under retry limit
                if ($this->attempts() < $this->tries) {
                    $this->release(30);
                }

                return;
            }

            $resolvedStatus = MpesaTransaction::resolveStatus(
                $resultCode,
                isStkCallback: true
            );

            // Re-check: callback may have settled it while we were querying
            $transaction->refresh();

            if ($transaction->status !== MpesaTransaction::STATUS_PENDING) {
                return;
            }

            if (MpesaTransaction::isTransitionAllowed($transaction->status, $resolvedStatus)) {
                $transaction->update([
                    'status' => $resolvedStatus,
                    'result_code' => $resultCode,
                    'result_desc' => data_get($response, 'ResultDesc'),
                    'resolved_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('STK Push query failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
