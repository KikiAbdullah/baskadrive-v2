<?php

namespace App\Support;

use App\Models\Invoice;

/**
 * Snapshot line item invoice secara konsisten (FIN-13): komponen biaya dasar,
 * asuransi, sopir, young driver, dan add-on disusun dari sumber kebenaran yang
 * tersimpan sehingga jumlah baris = sub_total - komponen yang nol. Dipakai
 * bersama oleh tampilan detail (show.blade.php) dan PDF (print.blade.php) agar
 * layar dan dokumen tidak berbeda rincian.
 */
class InvoiceLineItems
{
    private const COMPONENT_TYPES = ['vehicle', 'driver', 'insurance'];

    /**
     * @return array<int, array{name: string, sub: string, qty: string, unit: float, total: float}>
     */
    public static function for(Invoice $invoice): array
    {
        $rental = $invoice->rental;
        $rows = [];

        $days = (int) ($rental?->rental_days ?? 1);

        if ((float) ($rental?->total_base_price ?? 0) > 0) {
            $rows[] = [
                'name' => 'Sewa Kendaraan',
                'sub' => 'Tarif harian'.(($rental?->vehicle?->license_plate) ? ' — '.$rental->vehicle->license_plate : ''),
                'qty' => $days.' hari',
                'unit' => (float) $rental->base_rate_per_day,
                'total' => (float) $rental->total_base_price,
            ];
        }

        if ((float) ($rental?->insurance_fee ?? 0) > 0) {
            $rows[] = [
                'name' => 'Asuransi perlindungan',
                'sub' => 'Proteksi selama masa sewa',
                'qty' => '1 x',
                'unit' => (float) $rental->insurance_fee,
                'total' => (float) $rental->insurance_fee,
            ];
        }

        if ((float) ($rental?->driver_fee ?? 0) > 0) {
            $rows[] = [
                'name' => 'Layanan sopir',
                'sub' => 'Jasa pengemudi',
                'qty' => $days.' hari',
                'unit' => $days > 0 ? (float) $rental->driver_fee / $days : (float) $rental->driver_fee,
                'total' => (float) $rental->driver_fee,
            ];
        }

        if ((float) ($rental?->young_driver_fee ?? 0) > 0) {
            $rows[] = [
                'name' => 'Biaya pengemudi muda',
                'sub' => 'Suplemen usia di bawah batas minimum',
                'qty' => '1 x',
                'unit' => (float) $rental->young_driver_fee,
                'total' => (float) $rental->young_driver_fee,
            ];
        }

        // Add-on tersimpan pada rental details. Baris bertipe komponen (legacy)
        // dilewati karena komponen yang sama sudah dirender dari kolom rental —
        // mencegah double count dan menjaga rekonsiliasi ke sub_total.
        foreach ($rental?->details ?? [] as $d) {
            if (in_array($d->item_type, self::COMPONENT_TYPES, true)) {
                continue;
            }

            $typeLabels = [
                'addon' => 'Layanan tambahan',
                'fuel' => 'Bahan bakar',
                'other' => 'Layanan tambahan',
            ];

            $rows[] = [
                'name' => $d->item_name ?? 'Layanan tambahan',
                'sub' => $typeLabels[$d->item_type] ?? 'Layanan tambahan',
                'qty' => ((int) $d->quantity ?? 1).' x',
                'unit' => (float) ($d->unit_price ?? 0),
                'total' => (float) ($d->total_price ?? ((float) ($d->unit_price ?? 0) * (int) ($d->quantity ?? 1))),
            ];
        }

        return $rows;
    }
}
