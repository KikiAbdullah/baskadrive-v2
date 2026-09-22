<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Http\Controllers\Api\ApiController;
use App\Models\FieldTaskAssignment;
use App\Models\Rental;
use App\Models\UserLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * F-02 — Beranda "Tugas Hari Ini" (konsep B.1).
 *
 * Tugas operator = gabungan:
 * 1. Sewa yang ditugaskan eksplisit via `field_task_assignments`;
 * 2. Semua sewa aktif hari ini (reserved/ongoing/overdue) bila operator
 *    berwenang lapangan — fallback agar tidak ada tugas terlewat.
 */
class TaskController extends ApiController
{
    use InteractsWithFieldApi;

    public function index(Request $request): JsonResponse
    {
        $date = $request->date('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : Carbon::today();

        $scope = $request->query('scope', 'today');

        $query = Rental::with(['customer', 'vehicle.model.brand', 'pickupLocation', 'returnLocation'])
            ->whereIn('status', ['reserved', 'ongoing', 'overdue']);

        match ($scope) {
            'upcoming' => $query->where('rental_start_date', '>', $date->copy()->endOfDay()),
            'all' => null,
            default => $query->where(function ($q) use ($date) {
                // Tugas hari ini: mulai sewa hari ini, jatuh tempo hari ini,
                // atau sudah lewat jatuh tempo (belum dikembalikan).
                $q->whereBetween('rental_start_date', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->orWhereBetween('rental_end_date', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                    ->orWhere('rental_end_date', '<', $date->copy()->startOfDay());
            }),
        };

        $rentals = $query->get();

        // Penugasan eksplisit diprioritaskan (NOT_ASSIGNED bila ditugaskan ke orang lain).
        $assignedRentals = FieldTaskAssignment::pending()
            ->where('assigned_to', $request->user()->id)
            ->pluck('rental_id')
            ->all();
        $assignedToOthers = FieldTaskAssignment::pending()
            ->where('assigned_to', '!=', $request->user()->id)
            ->pluck('rental_id')
            ->all();

        $tasks = $rentals
            ->reject(fn (Rental $r) => in_array($r->rental_id, $assignedToOthers, true))
            ->values()
            ->map(fn (Rental $r) => $this->presentTask($r))
            ->all();

        $summary = [
            'handover_out' => count(array_filter($tasks, fn ($t) => $t['task_type'] === 'handover_out')),
            'handover_in' => count(array_filter($tasks, fn ($t) => $t['task_type'] === 'handover_in')),
            'overdue' => count(array_filter($tasks, fn ($t) => $t['is_overdue'])),
        ];

        return response()->json(responseSuccess([
            'date' => $date->toDateString(),
            'summary' => $summary,
            'tasks' => $tasks,
        ], 'Daftar tugas'));
    }

    public function show(Request $request, Rental $rental): JsonResponse
    {
        $rental->load([
            'customer', 'vehicle.model.brand', 'pickupLocation', 'returnLocation',
            'details', 'driver', 'handoverOut', 'handoverIn', 'invoices', 'payments',
            'fines', 'damageReports',
        ]);

        $this->assertAssigned($request, $rental);

        $out = $rental->handoverOut;
        $insuranceFee = (float) ($rental->insurance_fee ?? 0);
        $driverFee = (float) ($rental->driver_fee ?? 0);

        return response()->json(responseSuccess([
            'rental_id' => $rental->rental_id,
            'rental_code' => $rental->rental_code,
            'rental_status' => $rental->status,
            'task_type' => $rental->status === 'reserved' ? 'handover_out' : 'handover_in',
            'rental_start_date' => $rental->rental_start_date?->toIso8601String(),
            'rental_end_date' => $rental->rental_end_date?->toIso8601String(),
            'is_overdue' => $rental->status === 'overdue'
                || ($rental->status === 'ongoing' && $rental->rental_end_date?->isPast()),
            'customer' => [
                'name' => $rental->customer?->full_name,
                'phone' => $rental->customer?->phone,
                'id_card_number' => $rental->customer?->id_card_number,
            ],
            'vehicle' => [
                'vehicle_id' => $rental->vehicle_id,
                'plate' => $rental->vehicle?->license_plate,
                'model' => trim(($rental->vehicle?->model?->brand?->brand_name ?? '').' '.($rental->vehicle?->model?->model_name ?? '')),
                'year' => $rental->vehicle?->year,
                'color' => $rental->vehicle?->color,
                'mileage' => $rental->vehicle?->mileage,
            ],
            'location' => [
                'name' => $rental->pickupLocation?->location_name,
                'lat' => $rental->pickupLocation?->latitude !== null ? (float) $rental->pickupLocation->latitude : null,
                'lng' => $rental->pickupLocation?->longitude !== null ? (float) $rental->pickupLocation->longitude : null,
            ],
            'pricing' => [
                'base_rate_per_day' => (float) $rental->base_rate_per_day,
                'rental_days' => $rental->rental_days,
                'insurance_fee' => $insuranceFee,
                'driver_fee' => $driverFee,
                'discount_amount' => (float) $rental->discount_amount,
                'deposit_amount' => (float) $rental->deposit_amount,
                'total_amount' => (float) $rental->total_amount,
                'payment_status' => $rental->payment_status,
            ],
            'handover_out_inspection' => $out ? [
                'odometer' => $out->odometer,
                'fuel_level' => $out->fuel_level,
                'body_damage_points' => $out->body_damage_points,
                'checklist' => $out->checklist,
            ] : null,
            'active_invoices' => $rental->invoices->whereNotIn('status', ['paid', 'cancelled'])->map(fn ($i) => [
                'invoice_id' => $i->invoice_id,
                'invoice_number' => $i->invoice_number,
                'total_amount' => (float) $i->total_amount,
                'paid_amount' => (float) $i->paid_amount,
                'status' => $i->status,
            ])->values()->all(),
        ], 'Detail tugas'));
    }

    /** Telemetri "di jalan" (opsional, konsep B.1). */
    public function enroute(Request $request, Rental $rental): JsonResponse
    {
        $this->assertAssigned($request, $rental);
        $request->validate(['lat' => 'nullable|numeric', 'lng' => 'nullable|numeric']);

        $this->audit($request, 'update', 'field-tasks', 'Operator menuju lokasi sewa '.$rental->rental_code);

        return $this->ok([
            'rental_id' => $rental->rental_id,
            'enroute_at' => now()->toIso8601String(),
        ], 'Status di jalan dicatat.');
    }

    /** Penugasan eksplisit menentukan hak akses tugas (konsep §9.2). */
    protected function assertAssigned(Request $request, Rental $rental): void
    {
        $assignment = FieldTaskAssignment::pending()->where('rental_id', $rental->rental_id)->first();

        if ($assignment && (int) $assignment->assigned_to !== (int) $request->user()->id) {
            throw new \App\Http\Controllers\Api\V1\Field\Concerns\FieldApiException(
                'Tugas '.$rental->rental_code.' bukan milik operator ini.',
                403,
                'NOT_ASSIGNED',
            );
        }
    }

    private function presentTask(Rental $r): array
    {
        $isOut = $r->status === 'reserved';

        return [
            'rental_id' => $r->rental_id,
            'rental_code' => $r->rental_code,
            'task_type' => $isOut ? 'handover_out' : 'handover_in',
            'scheduled_at' => ($isOut ? $r->rental_start_date : $r->rental_end_date)?->toIso8601String(),
            'is_overdue' => $r->status === 'overdue'
                || ($r->status === 'ongoing' && $r->rental_end_date?->isPast()),
            'customer' => [
                'name' => $r->customer?->full_name,
                'phone' => $r->customer?->phone,
            ],
            'vehicle' => [
                'plate' => $r->vehicle?->license_plate,
                'model' => trim(($r->vehicle?->model?->brand?->brand_name ?? '').' '.($r->vehicle?->model?->model_name ?? '')),
            ],
            'location' => [
                'name' => $r->pickupLocation?->location_name,
            ],
            'rental_status' => $r->status,
        ];
    }
}
