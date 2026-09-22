<?php

namespace App\Support;

/**
 * Sumber logo & favicon aplikasi (satu pusat, dipakai seluruh UI + PDF):
 *
 * 1. Logo perusahaan unggahan (AppSettings::company_logo) — jika ada.
 * 2. Fallback: `public/app_local/img/logo.png` — aset identitas bawaan.
 *
 * Konteks browser memakai URL langsung (`/app_local/img/logo.png`); konteks
 * PDF (Dompdf) memakai data-URI karena renderer tidak selalu resolve path
 * relatif/asset().
 */
class BrandAsset
{
    public const DEFAULT_LOGO = 'app_local/img/logo.png';

    /**
     * Path relatif logo aktif (atau default) — dipakai sebagai URL publik.
     */
    public static function logoPath(): string
    {
        $custom = AppSettings::get('company_logo');

        return $custom ? (string) $custom : self::DEFAULT_LOGO;
    }

    /**
     * URL logo untuk <img src>, navbar, login, dsb.
     */
    public static function logoUrl(): string
    {
        return asset(self::logoPath());
    }

    /**
     * Data-URI logo aktif untuk konteks PDF (Dompdf).
     */
    public static function logoDataUri(): ?string
    {
        $path = public_path(self::logoPath());
        if (! is_file($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
    }

    /**
     * Data-URI ikon versi persegi kecil (dipakai favicon & avatar kecil).
     * Logo 3000x3000 diturunkan ke 256px agar ringan dikirim per halaman.
     * Cache hasil di public/app_local/img/ — dibuat sekali saat dibutuhkan.
     */
    public static function faviconDataUri(): string
    {
        return 'data:image/png;base64,'.base64_encode((string) file_get_contents(self::faviconPath()));
    }

    /**
     * URL favicon — file turunan persegi kecil dari logo.png.
     */
    public static function faviconUrl(): string
    {
        return asset(self::faviconRelative());
    }

    public static function faviconRelative(): string
    {
        return 'app_local/img/favicon.png';
    }

    /**
     * Pastikan favicon turunan ada di disk (dibuat dari logo.png via GD,
     * sekali — file berikutnya dipakai langsung).
     */
    public static function ensureFavicon(): string
    {
        $faviconFull = public_path(self::faviconRelative());
        $source = public_path(self::DEFAULT_LOGO);

        if (is_file($faviconFull) && is_file($source)
            && filemtime($faviconFull) >= filemtime($source)) {
            return self::faviconRelative();
        }

        if (! is_file($source) || ! function_exists('imagecreatefrompng')) {
            return self::faviconRelative();
        }

        try {
            $src = @imagecreatefrompng($source);
            if ($src === false) {
                return self::faviconRelative();
            }

            $size = 256;
            $dst = imagecreatetruecolor($size, $size);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $size, $size, imagesx($src), imagesy($src));

            imagepng($dst, $faviconFull, 6);
            imagedestroy($src);
            imagedestroy($dst);
        } catch (\Throwable $e) {
            \Log::warning('Favicon generation failed: '.$e->getMessage());
        }

        return self::faviconRelative();
    }
}
