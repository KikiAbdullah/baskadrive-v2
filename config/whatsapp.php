<?php

/*

|--------------------------------------------------------------------------
| Gateway WhatsApp Internal (Baileys + whatsapp-web.js)
|--------------------------------------------------------------------------
|
| Konfigurasi untuk gateway Node.js internal di folder `whatsapp-gateway/`.
| Baileys (https://baileys.wiki) adalah provider UTAMA — berjalan via
| WebSocket tanpa browser. whatsapp-web.js (https://wwebjs.dev) adalah
| provider CADANGAN via Puppeteer/Chrome. Masing-masing punya saklar
| on/off tersendiri (tersimpan di app_settings, diatur dari UI Sistem).
|
*/

return [

    // URL base gateway internal (server.js). Kosong = fitur WhatsApp nonaktif.
    'gateway_url' => env('WHATSAPP_GATEWAY_URL', 'http://127.0.0.1:3001'),

    // Rahasia bersama untuk header X-Gateway-Token (webhook & API gateway).
    'gateway_token' => env('WHATSAPP_GATEWAY_TOKEN'),

    // Timeout HTTP ke gateway (detik).
    'timeout' => env('WHATSAPP_GATEWAY_TIMEOUT', 10),

    // Provider default saat saklar belum pernah disimpan di app_settings.
    'default_provider' => env('WHATSAPP_DEFAULT_PROVIDER', 'baileys'),

    // Provider aktif boleh di-failover otomatis ke provider cadangan yang
    // menyala bila utama sedang tidak terhubung.
    'auto_failover' => env('WHATSAPP_AUTO_FAILOVER', true),

    // Nomor tujuan dipaksa ke nomor ini saat APP_ENV=local (uji coba aman).
    'number_testing' => env('WA_NUMBER_TESTING', '6285155300552'),

    // Gateway lama (opsional) — dipakai kirimWA() bila gateway internal OFF.
    'endpoint' => env('APP_WHATSAPP_API'),
    'session_name' => env('APP_WHATSAPP_API_NAME'),
    'session_key' => env('APP_WHATSAPP_API_KEY'),
    'allow_get_fallback' => env('APP_WHATSAPP_ALLOW_GET', false),

];
