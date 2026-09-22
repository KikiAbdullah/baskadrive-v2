<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Http\Controllers\Api\ApiController;
use App\Models\Rental;
use App\Models\RentalInspection;
use App\Services\DriverCommissionService;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;
use Carbon\Carbon;

/**
 * F-03/F-04 — Serah terima keluar & masuk via mobile (konsep B.2/B.3).
 *
 * Prinsip konsep §9.2: mobile memanggil **service yang sama dengan web**
 * (`AccountingService`, `DriverCommissionService`, `RentalSettlementService`)
 * sehingga jurnal, settlement, dan tutup buku identik dengan web.
 */
class HandoverController extends ApiController
{
    use InteractsWithFieldApi;

    /**
     * POST /rentals/{rental}/handover-out — multipart (konsep B.2).
     *
     * Efek server: inspeksi awal (`handover_out`) → sewa reserved → ongoing,
     * kendaraan → rented. Idempotent terhadap `client_uuid`.
     */
    public function handoverOut(Request $request, Rental $rental): JsonResponse
    {
        $request->validate([
            'odometer' => 'required|integer|min:0',
            'fuel_level' => 'required|in:full,three_quarter,half,quarter,empty',
            'checklist' => 'nullable|array',
            'checklist.*.key' => 'required_with:checklist|string',
            'checklist.*.present' => 'required_with:checklist|boolean',
            'body_damage_points' => 'nullable|array',
            'body_damage_points.*.side' => 'required_with:body_damage_points|string',
            'notes' => 'nullable|string|max:1000',
            'customer_name_signed' => 'nullable|string|max:100',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        return $this->idempotent($request, 'handover-out', function () use ($request, $rental) {
            $this->rentalInStatus($rental, ['reserved']);

            return DB::transaction(function () use ($request, $rental) {
                $rental = Rental::where('rental_id', $rental->rental_id)->lockForUpdate()->firstOrFail();

                if ($rental->handoverOut) {
                    // Inspeksi awal sudah ada → idempotent business-level.
                    return ['rental_id' => $rental->rental_id, 'status' => $rental->status];
                }

                RentalInspection::create([
                    'rental_id' => $rental->rental_id,
                    'vehicle_id' => $rental->vehicle_id,
                    'inspection_type' => 'handover_out',
                    'odometer' => (int) $request->input('odometer'),
                    'fuel_level' => $request->input('fuel_level'),
                    'body_damage_points' => $request->input('body_damage_points'),
                    'checklist' => collect($request->input('checklist', []))
                        ->mapWithKeys(fn ($item) => [(string) $item['key'] => (bool) $item['present']])
                        ->all(),
                    'notes' => $request->input('notes'),
                    'created_by' => $request->user()?->id,
                ]);

                // Sinkronkan odometer kendaraan (audit §3.7: tidak boleh mundur).
                $rental->vehicle?->update([
                    'mileage' => max((int) ($rental->vehicle->mileage ?? 0), (int) $request->input('odometer')),
                ]);

                // Reuse logika web: reservasi menjadi sewa aktif.
                $rental->update(['status' => 'ongoing']);
                $rental->vehicle?->update(['status' => 'rented']);

                $this->audit(
                    $request,
                    'create',
                    'field-handover',
                    'Serah terima awal (mobile) sewa '.$rental->rental_code.' — odometer '.$request->input('odometer').', BBM '.$request->input('fuel_level'),
                );

                return [
                    'rental_id' => $rental->rental_id,
                    'rental_code' => $rental->rental_code,
                    'rental_status' => 'ongoing',
                ];
            });
        });
    }

    /**
     * POST /rentals/{rental}/return/calculate — simulasi biaya (konsep B.3).
     * Satu-satunya sumber kebenaran perhitungan; mobile hanya menampilkan.
     */
    public function returnCalculate(Request $request, Rental $rental): JsonResponse
    {
        $request->validate([
            'odometer_end' => 'nullable|integer|min:0',
            'fuel_level_end' => 'nullable|in:full,three_quarter,half,quarter,empty',
            'new_damages' => 'nullable|array',
            'new_damages.*.severity' => 'required_with:new_damages|in:minor,moderate,severe,total_loss',
            'new_damages.*.estimate' => 'nullable|numeric|min:0',
            'missing_items' => 'nullable|array',
            'occurred_at' => 'nullable|date',
        ]);

        $rental->load(['handoverOut', 'vehicle']);
        $this->rentalInStatus($rental, ['ongoing', 'overdue']);

        $pickupOdo = (int) ($rental->handoverOut?->odometer ?? $rental->vehicle?->mileage ?? 0);
        $odometerEnd = (int) $request->input('odometer_end', $pickupOdo);

        if ($odometerEnd < $pickupOdo) {
            throw new \App\Http\Controllers\Api\V1\Field\Concerns\FieldApiException(
                'Odometer pengembalian harus >= odometer serah terima awal ('.$pickupOdo.' km).',
                422,
                'VALIDATION_ERROR',
            );
        }

        $occurred = $request->filled('occurred_at') ? Carbon::parse($request->input('occurred_at')) : now();
        $due = $rental->rental_end_date?->copy();

        // Keterlambatan: per hari dari jatuh tempo (sama dengan aturan denda web).
        $lateDays = 0;
        if ($due && $occurred->gt($due->endOfDay())) {
            $lateDays = (int) ceil($due->endOfDay()->diffInHours($occurred) / 24);
        }
        $baseRate = (float) $rental->base_rate_per_day;
        $lateCharge = $lateDays * $baseRate;

        // Selisih BBM: level awal vs akhir (tarif per level, sederhana & eksplisit).
        $fuelOrder = ['empty' => 0, 'quarter' => 1, 'half' => 2, 'three_quarter' => 3, 'full' => 4];
        $startLevel = $fuelOrder[$rental->handoverOut?->fuel_level ?? 'full'] ?? 4;
        $endLevel = $fuelOrder[$request->input('fuel_level_end', 'full')] ?? 4;
        $fuelDeficit = max(0, $startLevel - $endLevel);
        $fuelRate = (float) config('field.fuel_refill_rate', 150000);
        $fuelCharge = $fuelDeficit * $fuelRate;

        $damageEstimates = collect($request->input('new_damages', []))
            ->sum(fn ($d) => (float) ($d['estimate'] ?? 0));
        $missingItems = (int) count($request->input('missing_items', []));
        $missingCharge = $missingItems * (float) config('field.missing_item_rate', 250000);

        $total = $lateCharge + $fuelCharge + $damageEstimates + $missingCharge;

        return response()->json(responseSuccess([
            'rental_id' => $rental->rental_id,
            'rental_code' => $rental->rental_code,
            'late_days' => $lateDays,
            'late_charge' => $lateCharge,
            'fuel_deficit_levels' => $fuelDeficit,
            'fuel_charge' => $fuelCharge,
            'damage_estimate' => (float) $damageEstimates,
            'missing_items_count' => $missingItems,
            'missing_charge' => $missingCharge,
            'total_additional' => (float) $total,
            'currency' => 'IDR',
            'note' => 'Simulasi — nilai final ditetapkan server saat submit pengembalian.',
        ], 'Simulasi biaya pengembalian'));
    }

    /**
     * POST /rentals/{rental}/return — pengembalian penuh (konsep B.3).
     * Inspeksi akhir → tr_return → denda/kerusakan → completed + akrual komisi
     * sopir (service web yang sama) — semua dalam satu transaksi.
     */
    public function returnRental(Request $request, Rental $rental): JsonResponse
    {
        $request->validate([
            'odometer_end' => 'required|integer|min:0',
            'fuel_level_end' => 'required|in:full,three_quarter,half,quarter,empty',
            'vehicle_condition' => 'nullable|in:excellent,good,fair,damaged',
            'new_damages' => 'nullable|array',
            'new_damages.*.severity' => 'required_with:new_damages|in:minor,moderate,severe,total_loss',
            'new_damages.*.estimate' => 'nullable|numeric|min:0',
            'new_damages.*.side' => 'nullable|string|max:50',
            'new_damages.*.description' => 'nullable|string|max:500',
            'missing_items' => 'nullable|array',
            'accepted_total' => 'nullable|numeric|min:0',
            'occurred_at' => 'nullable|date',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        return $this->idempotent($request, 'return', function () use ($request, $rental) {
            $this->rentalInStatus($rental, ['ongoing', 'overdue']);

            return DB::transaction(function () use ($request, $rental) {
                $rental = Rental::whereIn('status', ['ongoing', 'overdue'])
                    ->lockForUpdate()->findOrFail($rental->rental_id);

                $pickupOdo = (int) ($rental->handoverOut?->odometer ?? $rental->vehicle?->mileage ?? 0);
                $odometerEnd = (int) $request->input('odometer_end');

                if ($odometerEnd < $pickupOdo) {
                    throw new Exception('Odometer pengembalian harus >= odometer serah terima awal ('.$pickupOdo.' km).');
                }

                $occurred = $request->filled('occurred_at')
                    ? Carbon::parse($request->input('occurred_at'))
                    : now();

                app(AccountingService::class)->assertPeriodOpen($occurred->toDateString());

                $newDamages = collect($request->input('new_damages', []));
                $missingItems = collect($request->input('missing_items', []));

                $damageEstimate = $newDamages->sum(fn ($d) => (float) ($d['estimate'] ?? 0));
                $missingCharge = $missingItems->count() * (float) config('field.missing_item_rate', 250000);
                $extraCharge = $damageEstimate + $missingCharge;

                $condition = $request->input('vehicle_condition')
                    ?? ($newDamages->isNotEmpty() ? 'damaged' : 'excellent');

                $return = \App\Models\ReturnCar::create([
                    'rental_id' => $rental->rental_id,
                    'return_date' => $occurred,
                    'return_mileage' => $odometerEnd,
                    'fuel_level' => $request->input('fuel_level_end'),
                    'vehicle_condition' => $condition,
                    'damage_description' => $newDamages->isNotEmpty()
                        ? $newDamages->map(fn ($d) => trim(($d['side'] ?? '').': '.($d['description'] ?? $d['severity'])))->implode('; ')
                        : null,
                    'repair_cost_estimate' => $damageEstimate,
                    'extra_charge' => $extraCharge,
                ]);

                // Inspeksi akhir (idempotent per jenis per sewa).
                RentalInspection::updateOrCreate(
                    [
                        'rental_id' => $rental->rental_id,
                        'inspection_type' => 'handover_in',
                    ],
                    [
                        'vehicle_id' => $rental->vehicle_id,
                        'odometer' => $odometerEnd,
                        'fuel_level' => $request->input('fuel_level_end'),
                        'notes' => 'Inspeksi akhir via mobile.',
                        'created_by' => $request->user()?->id,
                    ],
                );

                $rental->update([
                    'status' => 'completed',
                    'actual_return_date' => $occurred,
                ]);

                // Kontrak audit: unit rusak → maintenance (bukan langsung available).
                if ($rental->vehicle) {
                    $vehicleStatus = in_array($condition, ['damaged', 'fair'], true) ? 'maintenance' : 'available';
                    $rental->vehicle->update([
                        'status' => $vehicleStatus,
                        'mileage' => max((int) ($rental->vehicle->mileage ?? 0), $odometerEnd),
                    ]);
                }

                // Extra charge → naikkan kewajiban + jurnal (FIN-08, konvensi web:
                // total rental direkalkulasi SEBELUM jurnal; rekonsiliasi invoice
                // draft ditangani kantor via dokumen penyesuaian).
                if ($extraCharge > 0) {
                    $rental->update(['total_amount' => (float) $rental->total_amount + $extraCharge]);
                    app(AccountingService::class)->post(
                        $occurred->toDateString(),
                        'RET-'.$rental->rental_code,
                        'Extra charge pengembalian '.$rental->rental_code.' (mobile)',
                        'rental',
                        [
                            ['account' => app(AccountingService::class)->resolveCoa('1-2100'), 'debit' => $extraCharge, 'credit' => 0],
                            ['account' => app(AccountingService::class)->resolveCoa('4-1200'), 'debit' => 0, 'credit' => $extraCharge],
                        ],
                    );
                }

                // Kerusakan baru → tr_damage_report (reported) + denda biaya
                // perbaikan via FineSettlementService (akrual jurnal — FLE-08,
                // alur yang sama dengan damageBillRenter web).
                foreach ($newDamages as $d) {
                    $damage = \App\Models\DamageReport::create([
                        'rental_id' => $rental->rental_id,
                        'vehicle_id' => $rental->vehicle_id,
                        'return_id' => $return->return_id,
                        'reported_date' => $occurred,
                        'damage_type' => 'exterior',
                        'severity' => $d['severity'],
                        'location' => $d['side'] ?? null,
                        'description' => $d['description'] ?? null,
                        'repair_cost_estimate' => $d['estimate'] ?? 0,
                        'status' => 'reported',
                        'inspected_by' => $rental->employee_id,
                        'notes' => 'Dilaporkan via mobile pada pengembalian.',
                    ]);

                    if ((float) ($d['estimate'] ?? 0) > 0) {
                        $fine = \App\Models\Fine::create([
                            'rental_id' => $rental->rental_id,
                            'return_id' => $return->return_id,
                            'damage_id' => $damage->damage_id,
                            'fine_type' => 'damage',
                            'description' => 'Biaya perbaikan kerusakan '.$rental->rental_code.' ('.$d['severity'].')',
                            'amount' => $d['estimate'],
                            'status' => 'unpaid',
                            'issued_date' => $occurred,
                            'issued_by' => $rental->employee_id,
                        ]);

                        app(\App\Services\FineSettlementService::class)->accrueDamageCharge($fine, $damage);
                    }
                }

                // Akrual komisi sopir — service yang sama dengan web (hard post).
                app(DriverCommissionService::class)->accrueOnReturn($rental->fresh(), $occurred);

                $this->audit(
                    $request,
                    'update',
                    'field-handover',
                    'Pengembalian (mobile) sewa '.$rental->rental_code.' — kondisi '.$condition.', tambahan Rp '.number_format($extraCharge, 0, ',', '.'),
                );

                return [
                    'rental_id' => $rental->rental_id,
                    'rental_code' => $rental->rental_code,
                    'rental_status' => 'completed',
                    'vehicle_status' => $rental->vehicle?->fresh()?->status,
                    'extra_charge' => $extraCharge,
                    'damages_reported' => $newDamages->count(),
                    'return_id' => $return->return_id,
                ];
            });
        });
    }
}
