<?php

use App\Http\Controllers\Api\MpesaCallbackController;
use Illuminate\Support\Facades\Route;

// Safaricom Daraja C2B endpoints (rate limited, no session auth – M-Pesa calls these directly)
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/mpesa/validation', [MpesaCallbackController::class, 'validation'])
        ->name('mpesa.validation');
    Route::post('/mpesa/confirmation', [MpesaCallbackController::class, 'confirmation'])
        ->name('mpesa.confirmation');
});

// Helper endpoint to verify stored transactions
Route::get('/mpesa/transactions/{transactionId}', [MpesaCallbackController::class, 'show'])
    ->name('mpesa.transactions.show');
