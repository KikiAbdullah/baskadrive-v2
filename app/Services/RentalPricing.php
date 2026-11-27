<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Promo;
use App\Models\Vehicle;
use App\Support\AppSettings;

/**
 * Sumber harga sewa TUNGGAL — dipakai POS kasir, wizard web, dan API mobile
 * (hasil AUDIT_BOOKING.md §2.3: formula tidak boleh diduplikasi lagi).
 *
 * Aturan (paritas RentalController::store):
 *   subtotal = base×hari + asuransi% + sopir(harian×hari) + young_driver + addon
 *   total    = subtotal − diskon_promo + PPN
 * Promo sudah divalidasi "usable" sebelum masuk sini; deposit default
 * mengikuti model unit → setting global.
 */
class RentalPricing
{
    /**
     * Hitung rincian harga sewa penuh.
     *
     * @param  array<int, array{quantity?:int, unit_price?:float}>  $addons
     * @return array<string, float|int|null>
     */
    public static function quote(
        Vehicle $vehicle,
        ?Customer $customer,
        array $settings,
        int $rentalDays,
        bool $withDriver = false,
        ?float $driverDailyFee = null,
        ?Promo $promo = null,
        array $addons = [],
        ?float $taxPercent = null,
    ): array {
        $baseRate = (float) $vehicle->model->base_price_per_day;
        $totalBase = $baseRate * $rentalDays;
        $insuranceFee = $totalBase * ((float) $vehicle->model->insurance_rate / 100);

        // Sopir: tarif harian (input) × hari — kolom DB memakai TOTAL.
        $driverDaily = $driverDailyFee ?? (float) ($settings['driver_fee_default'] ?? 0);
        $driverFee = $withDriver ? max(0.0, $driverDaily) * $rentalDays : 0.0;

        $youngDriverFee = self::youngDriverFee($customer, $settings);

        $addonsTotal = 0.0;
        foreach ($addons as $a) {
            $addonsTotal += (float) ($a['quantity'] ?? 1) * (float) ($a['unit_price'] ?? 0);
        }

        $subtotal = $totalBase + $insuranceFee + $driverFee + $youngDriverFee + $addonsTotal;

        $discountAmount = 0.0;
        if ($promo) {
            $discountAmount = self::computeDiscount($promo, $subtotal);
        }

        $afterDiscount = $subtotal - $discountAmount;

        $taxPercent = $taxPercent ?? self::defaultTaxPercent($settings);
        $taxAmount = $afterDiscount * ($taxPercent / 100);
        $totalAmount = $afterDiscount + $taxAmount;

        $depositAmount = ! empty($settings['deposit_enabled'])
            ? (float) ($vehicle->model->deposit_amount ?: ($settings['deposit_default'] ?? 0))
            : 0.0;

        return [
            'base_rate_per_day' => $baseRate,
            'rental_days' => $rentalDays,
            'total_base_price' => round($totalBase, 2),
            'insurance_fee' => round($insuranceFee, 2),
            'driver_fee' => round($driverFee, 2),
            'young_driver_fee' => round($youngDriverFee, 2),
            'addons_total' => round($addonsTotal, 2),
            'subtotal' => round($subtotal, 2),
            'discount_amount' => round($discountAmount, 2),
            'tax_percent' => $taxPercent,
            'tax_amount' => round($taxAmount, 2),
            'deposit_amount' => $depositAmount,
            'total_amount' => round($totalAmount, 2),
        ];
    }

    /** PPN default dari setting (tax_enabled → tax_percent, else 0). */
    public static function defaultTaxPercent(array $settings): float
    {
        if (! ($settings['tax_enabled'] ?? true)) {
            return 0.0;
        }

        return (float) ($settings['tax_percent'] ?? 11);
    }

    /** Young-driver fee: usia < young_driver_age → biaya flat (0 bila tak relevan). */
    public static function youngDriverFee(?Customer $customer, array $settings): float
    {
        if (! $customer || empty($customer->date_of_birth) || empty($settings['young_driver_fee_default'])) {
            return 0.0;
        }

        $age = \Carbon\Carbon::parse($customer->date_of_birth)->age;

        return $age < (int) ($settings['young_driver_age'] ?? 21)
            ? (float) $settings['young_driver_fee_default']
            : 0.0;
    }

    /** Diskon promo: percentage vs fixed, dibatasi subtotal. */
    public static function computeDiscount(Promo $promo, float $subtotal): float
    {
        $discount = $promo->discount_type === 'percentage'
            ? $subtotal * ((float) $promo->discount_value / 100)
            : (float) $promo->discount_value;

        return max(0.0, min($discount, $subtotal));
    }
}
