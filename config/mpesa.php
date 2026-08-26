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

    'passkey' => env('MPESA_PASSKEY', ''),

    /*
    | Optional shared secret checked against the X-Callback-Secret header
    | before a callback is accepted. Leave null to disable the check.
    */
    'callback_secret' => env('MPESA_CALLBACK_SECRET'),

];
