<?php

use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

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
