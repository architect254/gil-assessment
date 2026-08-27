<?php

use App\Http\Controllers\Api\MpesaCallbackController;
use Illuminate\Support\Facades\Route;

// Safaricom Daraja C2B callback endpoints (rate limited, no session auth – M-Pesa calls these directly)
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/c2b/validation', [MpesaCallbackController::class, 'validation'])
        ->name('c2b.validation');
    Route::post('/c2b/confirmation', [MpesaCallbackController::class, 'confirmation'])
        ->name('c2b.confirmation');
});

// Helper endpoint to verify stored transactions
Route::get('/c2b/transactions/{transactionId}', [MpesaCallbackController::class, 'show'])
    ->name('c2b.transactions.show');
