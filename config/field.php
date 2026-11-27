<?php

/*
|--------------------------------------------------------------------------
| API Mobile — BaskaDrive Operator (prefix /api/v1/field)
|--------------------------------------------------------------------------
| Nilai default kebijakan lapangan; seluruhnya dapat dioverride via .env.
*/

return [

    /*
    | Batas wewenang pembayaran operator per transaksi (Rp). Melebihi nilai
    | ini → pembayaran mobile berstatus pending menunggu approval kantor
    | (konsep B.5). 0 = tanpa batas (tidak disarankan).
    */
    'operator_payment_limit' => env('FIELD_OPERATOR_PAYMENT_LIMIT', 5000000),

    /*
    | Tarif penggantian BBM per tingkat level (empty→quarter→half→three_quarter
    | →full) saat pengembalian (konsep B.3).
    */
    'fuel_refill_rate' => env('FIELD_FUEL_REFILL_RATE', 150000),

    // Inspeksi video (praktik lapangan: rekam seluruh sisi + interior).
    'inspection_video_max_files' => env('FIELD_VIDEO_MAX_FILES', 3),
    'inspection_video_max_kb' => env('FIELD_VIDEO_MAX_KB', 51200),

    /*
    | Tarif per barang checklist yang hilang saat pengembalian (konsep B.3).
    */
    'missing_item_rate' => env('FIELD_MISSING_ITEM_RATE', 250000),

    /*
    | Versi minimum aplikasi mobile; versi lebih lama menerima 426
    | UPGRADE_REQUIRED (konsep B.7). Mobile membaca nilai ini dari GET /meta.
    */
    'min_app_version' => env('FIELD_MIN_APP_VERSION', '1.0.0'),

];
