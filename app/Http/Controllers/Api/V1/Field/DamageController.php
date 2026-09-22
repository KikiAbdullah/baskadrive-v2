<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Http\Controllers\Api\ApiController;
use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\Fine;
use App\Models\Rental;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * F-05 — Lapor kerusakan cepat (konsep B.4).
 */
class DamageController extends ApiController
{
    use InteractsWithFieldApi;

    /** POST /damages — multipart: field + photos[] (konsep B.4). */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'vehicle_query' => 'required_without:vehicle_id|string|max:50',
            'vehicle_id' => 'nullable|exists:m_vehicle,vehicle_id',
            'rental_id' => 'nullable|exists:tr_rental,rental_id',
            'damage_type' => ['nullable', 'in:'.implode(',', DamageReport::DAMAGE_TYPES)],
            'severity' => ['required', 'in:'.implode(',', DamageReport::SEVERITIES)],
            'body_points' => 'nullable|array',
            'body_points.*.side' => 'required_with:body_points|string|max:50',
            'description' => 'nullable|string|max:2000',
            'estimate' => 'nullable|numeric|min:0|max:9999999999.99',
            'occurred_at' => 'nullable|date',
            'photos' => 'nullable|array|max:8',
            'photos.*' => 'image|mimes:jpg,jpeg,png,webp|max:10240',
        ]);

        return $this->idempotent($request, 'damages', function () use ($request) {
            $vehicle = $request->filled('vehicle_id')
                ? Vehicle::findOrFail($request->integer('vehicle_id'))
                : Vehicle::where('license_plate', 'like', str_replace(' ', '', $request->input('vehicle_query')).'%')->firstOrFail();

            return DB::transaction(function () use ($request, $vehicle) {
                $occurred = $request->filled('occurred_at') ? \Carbon\Carbon::parse($request->input('occurred_at')) : now();

                $damage = DamageReport::create([
                    'rental_id' => $request->filled('rental_id') ? $request->integer('rental_id') : $vehicle->activeRental?->rental_id,
                    'vehicle_id' => $vehicle->vehicle_id,
                    'reported_date' => $occurred,
                    'damage_type' => $request->input('damage_type', 'exterior'),
                    'severity' => $request->input('severity'),
                    'location' => collect($request->input('body_points', []))->pluck('side')->implode(', ') ?: null,
                    'description' => $request->input('description'),
                    'repair_cost_estimate' => $request->input('estimate', 0),
                    'status' => 'reported',
                    'notes' => 'Dilaporkan via mobile oleh '.$request->user()?->name,
                ]);

                // Simpan foto (disk public, folder damage-photos — konvensi web).
                foreach ($request->file('photos', []) as $i => $photo) {
                    $path = $photo->store('damage-photos', 'public');
                    DamagePhoto::create([
                        'damage_id' => $damage->damage_id,
                        'photo_url' => $path,
                        'caption' => 'Mobile foto #'.($i + 1),
                        'uploaded_at' => now(),
                    ]);
                }

                $this->audit(
                    $request,
                    'create',
                    'field-damage',
                    'Lapor kerusakan (mobile) unit '.$vehicle->license_plate.' — '.$request->input('severity'),
                );

                return [
                    'damage_id' => $damage->damage_id,
                    'vehicle' => $vehicle->license_plate,
                    'status' => $damage->status,
                    'photos_uploaded' => count($request->file('photos', [])),
                ];
            });
        });
    }

    /** GET /damages?vehicle_id= — riwayat kerusakan unit. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['vehicle_id' => 'nullable|exists:m_vehicle,vehicle_id']);

        $query = DamageReport::with(['vehicle', 'photos'])
            ->orderByDesc('reported_date');

        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->integer('vehicle_id'));
        }

        $items = $query->limit(100)->get()->map(fn (DamageReport $d) => [
            'damage_id' => $d->damage_id,
            'vehicle' => $d->vehicle?->license_plate,
            'severity' => $d->severity,
            'damage_type' => $d->damage_type,
            'status' => $d->status,
            'location' => $d->location,
            'description' => $d->description,
            'reported_date' => $d->reported_date?->toIso8601String(),
            'photos' => $d->photos->map(fn ($p) => Storage::disk('public')->url($p->photo_url))->all(),
        ])->all();

        return response()->json(responseSuccess(['damages' => $items], 'Riwayat kerusakan'));
    }
}
