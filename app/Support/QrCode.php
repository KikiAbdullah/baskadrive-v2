<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCode
{
    /**
     * Buat QR code sebagai data-URI SVG (kompatibel browser & Dompdf).
     */
    public static function dataUriSvg(string $text, int $size = 120, int $margin = 2): string
    {
        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd);

        $svg = (new Writer($renderer))->writeString($text);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Tanda tangan verifikasi generik untuk dokumen sistem: kwitansi, kontrak
     * sewa, dan jenis dokumen lain berikutnya (butir 3 audit_12092026).
     * `jenis|id` dicampur APP_KEY via HMAC-SHA256 — URL tanpa `t` yang sesuai
     * tidak sah sebagai bukti keaslian dokumen.
     */
    public static function documentSignature(string $kind, int $id): string
    {
        return substr(hash_hmac('sha256', $kind.'|'.$id, config('app.key')), 0, 16);
    }

    /**
     * Tanda tangan verifikasi kwitansi (audit 3) — kontrak tidak berubah agar
     * kwitansi yang sudah beredar tetap terverifikasi.
     */
    public static function receiptSignature(int $paymentId): string
    {
        return self::documentSignature('receipt', $paymentId);
    }

    /**
     * Tanda tangan verifikasi kontrak sewa (butir 3 audit_12092026).
     */
    public static function contractSignature(int $rentalId): string
    {
        return self::documentSignature('contract', $rentalId);
    }
}
