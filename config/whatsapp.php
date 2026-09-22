<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WhatsApp API
    |--------------------------------------------------------------------------
    |
    | These options configure the external WhatsApp API endpoint that is used
    | to deliver OTP / notification messages.
    |
    */

    'endpoint' => env('APP_WHATSAPP_API'),

    'session_name' => env('APP_WHATSAPP_API_NAME'),

    'session_key' => env('APP_WHATSAPP_API_KEY'),

    'number_testing' => env('WA_NUMBER_TESTING', '6285155300552'),

    // Audit keamanan: kredensial dikirim via POST header (bukan query string).
    // Set true HANYA selama transisi ke gateway yang belum mendukung POST.
    'allow_get_fallback' => env('APP_WHATSAPP_ALLOW_GET', false),

];
