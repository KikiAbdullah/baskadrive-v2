<?php

namespace App\Support;

use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Wrapper tipis di atas Spatie Laravel-PDF.
 *
 * Driver mengikuti config('laravel-pdf.driver') secara penuh
 * (default: dompdf — pure PHP, jalan di mana saja tanpa binary).
 * Bisa dioverride via .env: LARAVEL_PDF_DRIVER=browsershot|chrome|gotenberg|...
 */
class PdfDocument
{
    /**
     * Render view menjadi response download PDF.
     */
    public static function download(string $view, array $data, string $name)
    {
        $filename = self::safeFilename($name);

        return Pdf::view($view, $data)
            ->format('a4')
            ->name($filename)
            ->download();
    }

    /**
     * Render view menjadi response inline (preview di browser).
     */
    public static function inline(string $view, array $data, string $name)
    {
        $filename = self::safeFilename($name);

        return Pdf::view($view, $data)
            ->format('a4')
            ->name($filename)
            ->inline();
    }

    /**
     * Simpan ke storage: storage/app/pdf/{nama}.pdf
     */
    public static function save(string $view, array $data, string $name): string
    {
        $filename = self::safeFilename($name);
        $path = 'pdf/' . $filename;

        Pdf::view($view, $data)
            ->format('a4')
            ->save(\Storage::path($path));

        return $path;
    }

    protected static function safeFilename(string $name): string
    {
        $filename = str_replace(['/', '\\'], '-', $name);
        if (! Str::endsWith(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;
    }
}
