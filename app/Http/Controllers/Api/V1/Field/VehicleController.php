<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Http\Controllers\Api\ApiController;
use App\Models\Maintenance;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F-06 — Scan & cari unit (konsep B.4).
 */
class VehicleController extends ApiController
{
    use InteractsWithFieldApi;

    /** GET /vehicles/search?q= — cari by plat/kode. */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return response()->json(responseFailed('Parameter q wajib diisi.'), 422);
        }

        $normalized = str_replace(' ', '', strtoupper($q));

        $vehicles = Vehicle::with(['model.brand', 'location'])
            ->where(function ($query) use ($normalized, $q) {
                $query->whereRaw('REPLACE(UPPER(license_plate), " ", "") LIKE ?', ["%{$normalized}%"])
                    ->orWhere('vin', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get()
            ->map(fn (Vehicle $v) => [
                'vehicle_id' => $v->vehicle_id,
                'plate' => $v->license_plate,
                'model' => trim(($v->model?->brand?->brand_name ?? '').' '.($v->model?->model_name ?? '')),
                'year' => $v->year,
                'color' => $v->color,
                'status' => $v->status,
                'location' => $v->location?->location_name,
            ])
            ->all();

        return response()->json(responseSuccess(['vehicles' => $vehicles], 'Hasil pencarian unit'));
    }

    /** GET /vehicles/{vehicle} — profil unit + sewa aktif + maintenance berikutnya. */
    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->load(['model.brand', 'location']);

        $activeRental = $vehicle->activeRental()->with(['customer', 'pickupLocation'])->first();

        // Maintenance tanpa SoftDeletes — kolom deleted_at tidak ada di tabel.
        $nextMaintenance = Maintenance::where('vehicle_id', $vehicle->vehicle_id)
            ->whereIn('status', ['scheduled', 'overdue'])
            ->orderBy('scheduled_date')
            ->with('maintenanceType')
            ->first();

        $lastDamages = $vehicle->damageReports()->orderByDesc('reported_date')->limit(5)->get(['damage_id', 'severity', 'status', 'reported_date']);

        return response()->json(responseSuccess([
            'vehicle_id' => $vehicle->vehicle_id,
            'plate' => $vehicle->license_plate,
            'model' => trim(($vehicle->model?->brand?->brand_name ?? '').' '.($vehicle->model?->model_name ?? '')),
            'year' => $vehicle->year,
            'color' => $vehicle->color,
            'status' => $vehicle->status,
            'mileage' => $vehicle->mileage,
            'location' => $vehicle->location?->location_name,
            'active_rental' => $activeRental ? [
                'rental_id' => $activeRental->rental_id,
                'rental_code' => $activeRental->rental_code,
                'status' => $activeRental->status,
                'customer' => $activeRental->customer?->full_name,
                'rental_end_date' => $activeRental->rental_end_date?->toIso8601String(),
            ] : null,
            'next_maintenance' => $nextMaintenance ? [
                'maintenance_id' => $nextMaintenance->maintenance_id,
                'type' => $nextMaintenance->maintenanceType?->type_name,
                'scheduled_date' => $nextMaintenance->scheduled_date?->toIso8601String(),
                'status' => $nextMaintenance->status,
            ] : null,
            'recent_damages' => $lastDamages->map(fn ($d) => [
                'damage_id' => $d->damage_id,
                'severity' => $d->severity,
                'status' => $d->status,
                'reported_date' => $d->reported_date?->toIso8601String(),
            ])->all(),
        ], 'Profil unit'));
    }
}
