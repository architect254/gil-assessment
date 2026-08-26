<?php

use App\Jobs\QueryStkPushStatus;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mpesa:backfill-invoice-ids', function () {
    $transactions = MpesaTransaction::whereNull('invoice_id')
        ->whereNotNull('bill_ref_number')
        ->get();

    $resolved = 0;
    $unresolved = 0;

    foreach ($transactions as $transaction) {
        $invoice = app(MpesaService::class)->findInvoiceForReference($transaction->bill_ref_number);
        if ($invoice) {
            $transaction->update(['invoice_id' => $invoice->id]);
            $resolved++;
        } else {
            $this->warn("Unresolved: bill_ref={$transaction->bill_ref_number} tx_id={$transaction->id}");
            $unresolved++;
        }
    }

    $this->info("Done: {$resolved} resolved, {$unresolved} unresolved.");
})->purpose('Resolve invoice_id for existing M-Pesa transactions');

Artisan::command('mpesa:reconcile', function () {
    $stale = MpesaTransaction::where('status', MpesaTransaction::STATUS_PENDING)
        ->where('created_at', '<', now()->subMinutes(3))
        ->get();

    $dispatched = 0;
    foreach ($stale as $transaction) {
        QueryStkPushStatus::dispatch($transaction->id);
        $dispatched++;
    }

    // Hard timeout: force-mark anything pending for >15 minutes
    $expired = MpesaTransaction::where('status', MpesaTransaction::STATUS_PENDING)
        ->where('created_at', '<', now()->subMinutes(15))
        ->update([
            'status' => MpesaTransaction::STATUS_TIMEOUT,
            'result_desc' => 'No callback or query response within 15 minutes',
            'resolved_at' => now(),
        ]);

    Log::info('mpesa:reconcile', [
        'dispatched' => $dispatched,
        'force_timed_out' => $expired,
    ]);

    $this->info("Reconciled: {$dispatched} dispatched for query, {$expired} force-timed out.");
})->purpose('Reconcile stuck M-Pesa transactions');

Schedule::command('mpesa:reconcile')->everyMinute();
