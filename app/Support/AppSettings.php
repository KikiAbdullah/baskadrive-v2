<?php

namespace App\Support;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Repository pengaturan aplikasi — tersimpan di database (tabel app_settings),
 * dengan cache agar tidak query berulang di setiap render.
 *
 * Pemakaian: AppSettings::get('tax_percent') / AppSettings::all() / AppSettings::set(...)
 */
class AppSettings
{
    protected const CACHE_KEY = 'app_settings_all';

    /**
     * Nilai default seluruh setting (single source of truth).
     */
    public static function defaults(): array
    {
        return [
            // Profil perusahaan
            'company_name' => 'BaskaDrive',
            'company_tagline' => 'Drive easy, arrive happy',
            'company_phone' => '',
            'company_email' => '',
            'company_address' => '',
            'company_logo' => null,

            // Dokumen
            'invoice_footer_note' => 'Terima kasih telah mempercayakan perjalanan Anda kepada kami.',
            'contract_terms' => "1. Penyewa bertanggung jawab atas seluruh kerosakan atau kehilangan kendaraan selama masa sewa berlangsung.\n2. Kendaraan wajib dikembalikan sesuai jadwal dengan kondisi dan tingkat bahan bakar yang sama.\n3. Deposit jaminan dikembalikan setelah pemeriksaan kondisi kendaraan.",

            // Keuangan & pajak
            'tax_enabled' => true,
            'tax_percent' => 11,
            'tax_label' => 'PPN',
            'deposit_enabled' => true,
            'deposit_default' => 500000,
            'deposit_required' => false,

            // Operasional sewa
            'late_hour_charge' => 50000,
            'overdue_grace_minutes' => 60,
            'invoice_due_days' => 7,
            'young_driver_age' => 21,
            'young_driver_fee_default' => 0,
            'driver_fee_default' => 150000,

            // Aplikasi
            'currency' => 'IDR',
            'currency_symbol' => 'Rp',
            'timezone' => 'Asia/Jakarta',
            'date_format' => 'd F Y',

            // Akuntansi (audit 2.6 - tutup buku)
            'accounting_closing_date' => null,
        ];
    }

    /**
     * Semua setting ter-merge dengan default.
     */
    public static function all(): array
    {
        $defaults = self::defaults();

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return AppSetting::query()
                ->pluck('value', 'key')
                ->toArray();
        });

        // Decode tipe data (bool/numeric) berdasarkan tipe default
        foreach ($stored as $key => $value) {
            if (! array_key_exists($key, $defaults)) {
                continue;
            }

            $default = $defaults[$key];

            $stored[$key] = match (true) {
                is_bool($default) => $value === 'true' || $value === '1',
                is_int($default) => (int) $value,
                is_float($default) => (float) $value,
                $default === null && ($value === 'null' || $value === '') => null,
                default => $value,
            };
        }

        return array_merge($defaults, $stored);
    }

    public static function get(string $key, mixed $fallback = null): mixed
    {
        $all = self::all();

        return $all[$key] ?? $fallback;
    }

    /**
     * Formatter uang bersama (FIN-14): dua desimal agar sen ikut tampil dan
     * rekonsiliasi kwitansi/invoice dengan transaksi tidak kehilangan presisi.
     * Nilai non-numerik ditampilkan apa adanya (mis. '-').
     */
    public static function money(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '-';
        }

        return 'Rp '.number_format((float) $value, 2, ',', '.');
    }

    public static function set(string $key, mixed $value): void
    {
        AppSetting::updateOrCreate(
            ['key' => $key],
            ['value' => self::stringify($value)]
        );

        self::flush();
    }

    public static function setMany(array $pairs): void
    {
        foreach ($pairs as $key => $value) {
            AppSetting::updateOrCreate(
                ['key' => $key],
                ['value' => self::stringify($value)]
            );
        }

        self::flush();
    }

    protected static function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }

    public static function forget(string $key): void
    {
        AppSetting::where('key', $key)->delete();

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Hapus logo dari storage.
     */
    public static function deleteLogo(): void
    {
        $old = self::get('company_logo');

        if ($old && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }
    }
}
