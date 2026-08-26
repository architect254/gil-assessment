<?php

return [

    /*
    |--------------------------------------------------------------------------
    | M-Pesa Daraja API Configuration
    |--------------------------------------------------------------------------
    |
    | Credentials for the Safaricom Daraja portal. The callback URLs are
    | served by MpesaCallbackController (validation + confirmation).
    |
    */

    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),

    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),

    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),

    'shortcode' => env('MPESA_SHORTCODE', '174379'),

    /*
    | STK Push (Lipa Na M-Pesa Online) may require a different shortcode
    | than C2B (Pay Bill). Falls back to `shortcode` if not set.
    */
    'stk_shortcode' => env('MPESA_STK_SHORTCODE', env('MPESA_SHORTCODE', '174379')),

    'passkey' => env('MPESA_PASSKEY', ''),

    /*
    | Optional shared secret checked against the X-Callback-Secret header
    | before a callback is accepted. Leave null to disable the check.
    */
    'callback_secret' => env('MPESA_CALLBACK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | STK Push Callback URL
    |--------------------------------------------------------------------------
    |
    | Override the callback URL sent to Safaricom for STK Push requests.
    | Required when APP_URL is not publicly reachable (e.g. localhost).
    | Falls back to APP_URL + /api/c2b/confirmation if not set.
    |
    */
    'stk_callback_url' => env('MPESA_STK_CALLBACK_URL'),

];
