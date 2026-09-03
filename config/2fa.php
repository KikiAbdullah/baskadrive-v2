<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Two Factor Authentication
    |--------------------------------------------------------------------------
    |
    | This option controls whether the Two Factor Authentication (via WhatsApp
    | OTP) is enabled for the application.
    |
    */

    'enabled' => env('APP_2FA', false),

    'cookie_name' => env('APP_2FA_NAME', 'token_2fa'),

];