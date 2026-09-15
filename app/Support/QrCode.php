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
        $renderer = new ImageRenderer(new RendererStyle($size, $margin), new SvgImageBackEnd());

        $svg = (new Writer($renderer))->writeString($text);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Tanda tangan verifikasi untuk dokumen kwitansi (audit 3).
     */
    public static function receiptSignature(int $paymentId): string
    {
        return substr(hash_hmac('sha256', 'receipt|'.$paymentId, config('app.key')), 0, 16);
    }
}
