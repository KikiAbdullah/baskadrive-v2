<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\NumberTrait;
use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\InsuranceClaim;
use App\Models\Maintenance;
use App\Models\MaintenanceType;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\Workshop;
use DB;
use Exception;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    use NumberTrait;
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ============================================================
    // MAINTENANCE
    // ============================================================

    public function maintenanceIndex()
    {
        return view('fleet.maintenance.index')->with([
            'title' => 'Jadwal Maintenance',
            'subtitle' => 'Jadwal Maintenance Kendaraan',
        ]);
    }

    public function maintenanceData(Request $request)
    {
        $query = Maintenance::with(['vehicle.model.brand', 'workshop', 'maintenanceType']);

        return datatables()->of($query)
            ->addColumn('vehicle_info', fn($m) => $m->vehicle?->license_plate . ' - ' . ($m->vehicle?->model?->brand?->brand_name ?? '') . ' ' . ($m->vehicle?->model?->model_name ?? ''))
            ->addColumn('type', fn($m) => $m->maintenanceType?->type_name ?? '-')
            ->addColumn('workshop_name', fn($m) => $m->workshop?->name ?? '-')
            ->addColumn('scheduled_date', fn($m) => $m->scheduled_date?->format('d/m/Y') ?? '-')
            ->addColumn('cost', fn($m) => 'Rp ' . number_format($m->cost ?? 0, 0, ',', '.'))
            ->addColumn('status_badge', fn($m) => view('components.maintenance-status-badge', ['status' => $m->status])->render())
            ->addColumn('action', function ($m) {
                $html = '<div class="d-flex gap-1">';
                $html .= '<a href="' . route('fleet.maintenance.edit', $m->maintenance_id) . '" class="btn btn-sm btn-outline-primary"><i class="ri-edit-line"></i></a>';
                if ($m->status !== 'completed') {
                    $html .= '<button type="button" class="btn btn-sm btn-success btn-complete" data-id="' . $m->maintenance_id . '"><i class="ri-check-line"></i></button>';
                }
                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function maintenanceCreate()
    {
        $data = [
            'vehicles' => Vehicle::where('status', 'available')->orWhere('status', 'maintenance')->get(),
            'workshops' => Workshop::active()->get(),
            'types' => MaintenanceType::active()->get(),
        ];

        return view('fleet.maintenance.form')->with(array_merge([
            'title' => 'Buat Maintenance',
            'subtitle' => 'Tambah Jadwal Maintenance',
            'item' => null,
        ], $data));
    }

    public function maintenanceStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'vehicle_id' => 'required|exists:m_vehicle,vehicle_id',
                'maintenance_type_id' => 'required|exists:m_maintenance_type,type_id',
                'workshop_id' => 'nullable|exists:m_workshop,workshop_id',
                'scheduled_date' => 'required|date',
                'current_mileage' => 'nullable|integer',
                'cost' => 'nullable|numeric',
                'next_maintenance_km' => 'nullable|integer',
                'description' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            Maintenance::create(array_merge($validated, ['status' => 'scheduled']));

            DB::commit();

            return redirect()->route('fleet.maintenance.index')
                ->withSuccess('Jadwal maintenance berhasil ditambahkan.');
        } catch (Exception $e) {
            DB::rollback();

            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function maintenanceEdit($id)
    {
        $item = Maintenance::findOrFail($id);

        $data = [
            'vehicles' => Vehicle::all(),
            'workshops' => Workshop::active()->get(),
            'types' => MaintenanceType::active()->get(),
        ];

        return view('fleet.maintenance.form')->with(array_merge([
            'title' => 'Edit Maintenance',
            'subtitle' => 'Edit Jadwal Maintenance',
            'item' => $item,
        ], $data));
    }

    public function maintenanceUpdate(Request $request, $id)
    {
        try {
            $item = Maintenance::findOrFail($id);

            $validated = $request->validate([
                'vehicle_id' => 'required|exists:m_vehicle,vehicle_id',
                'maintenance_type_id' => 'required|exists:m_maintenance_type,type_id',
                'workshop_id' => 'nullable|exists:m_workshop,workshop_id',
                'scheduled_date' => 'required|date',
                'actual_date' => 'nullable|date',
                'current_mileage' => 'nullable|integer',
                'cost' => 'nullable|numeric',
                'next_maintenance_km' => 'nullable|integer',
                'description' => 'nullable|string',
                'status' => 'required|in:scheduled,overdue,in_progress,completed,cancelled',
                'notes' => 'nullable|string',
            ]);

            if ($validated['status'] === 'in_progress' && $item->vehicle) {
                $item->vehicle->update(['status' => 'maintenance']);
            }

            if ($validated['status'] === 'completed' && $item->vehicle) {
                $item->vehicle->update(['status' => 'available']);
            }

            $item->update($validated);

            return redirect()->route('fleet.maintenance.index')
                ->withSuccess('Jadwal maintenance berhasil diperbarui.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function maintenanceComplete(Request $request, $id)
    {
        try {
            $item = Maintenance::findOrFail($id);
            $item->update([
                'status' => 'completed',
                'actual_date' => now(),
            ]);

            if ($item->vehicle) {
                $item->vehicle->update(['status' => 'available']);
            }

            return response()->json(['status' => true, 'msg' => 'Maintenance ditandai selesai.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function maintenanceReschedule(Request $request, $id)
    {
        try {
            $item = Maintenance::findOrFail($id);
            $request->validate(['scheduled_date' => 'required|date']);
            $item->update([
                'scheduled_date' => $request->scheduled_date,
                'status' => 'scheduled',
            ]);

            return response()->json(['status' => true, 'msg' => 'Jadwal berhasil diubah.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ============================================================
    // DAMAGE REPORT
    // ============================================================

    public function damageIndex()
    {
        return view('fleet.damage.index')->with([
            'title' => 'Daftar Kerusakan',
            'subtitle' => 'Laporan Kerusakan Kendaraan',
        ]);
    }

    public function damageData(Request $request)
    {
        $query = DamageReport::with(['vehicle.model.brand', 'rental']);

        return datatables()->of($query)
            ->addColumn('vehicle_info', fn($d) => $d->vehicle?->license_plate . ' - ' . ($d->vehicle?->model?->brand?->brand_name ?? '') . ' ' . ($d->vehicle?->model?->model_name ?? ''))
            ->addColumn('damage_type', fn($d) => ucfirst(str_replace('_', ' ', $d->damage_type ?? '-')))
            ->addColumn('severity', fn($d) => ucfirst($d->severity ?? '-'))
            ->addColumn('reported_date', fn($d) => $d->reported_date?->format('d/m/Y') ?? '-')
            ->addColumn('repair_cost', fn($d) => 'Rp ' . number_format($d->actual_repair_cost ?? $d->repair_cost_estimate ?? 0, 0, ',', '.'))
            ->addColumn('status_badge', fn($d) => view('components.damage-status-badge', ['status' => $d->status])->render())
            ->addColumn('action', fn($d) => '<a href="' . route('fleet.damage.show', $d->damage_id) . '" class="btn btn-sm btn-outline-primary"><i class="ri-eye-line"></i> Detail</a>')
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function damageCreate()
    {
        $data = [
            'vehicles' => Vehicle::all(),
            'rentals' => Rental::orderBy('created_at', 'desc')->limit(50)->get(),
        ];

        return view('fleet.damage.form')->with(array_merge([
            'title' => 'Buat Laporan Kerusakan',
            'subtitle' => 'Tambah Laporan Kerusakan',
            'item' => null,
        ], $data));
    }

    public function damageStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'vehicle_id' => 'required|exists:m_vehicle,vehicle_id',
                'rental_id' => 'nullable|exists:tr_rental,rental_id',
                'reported_date' => 'required|date',
                'damage_type' => 'required|string',
                'severity' => 'required|in:minor,moderate,severe',
                'location' => 'nullable|string',
                'description' => 'nullable|string',
                'repair_cost_estimate' => 'nullable|numeric',
                'notes' => 'nullable|string',
            ]);

            DamageReport::create(array_merge($validated, ['status' => 'reported']));

            return redirect()->route('fleet.damage.index')
                ->withSuccess('Laporan kerusakan berhasil ditambahkan.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function damageShow($id)
    {
        $item = DamageReport::with(['vehicle.model.brand', 'rental.customer', 'inspector', 'photos', 'insuranceClaim'])
            ->findOrFail($id);

        return view('fleet.damage.show')->with([
            'title' => 'Detail Kerusakan',
            'subtitle' => 'Laporan Kerusakan #' . $item->damage_id,
            'item' => $item,
        ]);
    }

    public function damageUpdateStatus(Request $request, $id)
    {
        try {
            $item = DamageReport::findOrFail($id);
            $request->validate(['status' => 'required|in:reported,inspected,approved,in_repair,repaired,rejected,closed']);
            $item->update([
                'status' => $request->status,
                'inspected_by' => auth()->user()->employee_id ?? null,
                'inspected_at' => now(),
            ]);

            return response()->json(['status' => true, 'msg' => 'Status kerusakan diperbarui.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function damagePhotoUpload(Request $request, $id)
    {
        try {
            $item = DamageReport::findOrFail($id);
            $request->validate(['photo' => 'required|image|max:5120']);

            $path = $request->file('photo')->store('damage-photos', 'public');

            DamagePhoto::create([
                'damage_id' => $item->damage_id,
                'photo_url' => $path,
                'caption' => $request->caption ?? null,
            ]);

            return response()->json(['status' => true, 'msg' => 'Foto berhasil diunggah.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function damagePhotoDelete($photoId)
    {
        try {
            $photo = DamagePhoto::findOrFail($photoId);
            $photo->delete();

            return response()->json(['status' => true, 'msg' => 'Foto berhasil dihapus.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    // ============================================================
    // INSURANCE CLAIM
    // ============================================================

    public function claimIndex()
    {
        return view('fleet.insurance-claim.index')->with([
            'title' => 'Klaim Asuransi',
            'subtitle' => 'Daftar Klaim Asuransi',
        ]);
    }

    public function claimData(Request $request)
    {
        $query = InsuranceClaim::with(['damageReport.vehicle', 'rental.customer']);

        return datatables()->of($query)
            ->addColumn('claim_number', fn($c) => $c->claim_number ?? '-')
            ->addColumn('vehicle_info', fn($c) => $c->damageReport?->vehicle?->license_plate ?? '-')
            ->addColumn('provider', fn($c) => $c->insurance_provider ?? '-')
            ->addColumn('claim_amount', fn($c) => 'Rp ' . number_format($c->claim_amount ?? 0, 0, ',', '.'))
            ->addColumn('status_badge', fn($c) => view('components.claim-status-badge', ['status' => $c->status])->render())
            ->addColumn('action', fn($c) => '<a href="' . route('fleet.insurance-claim.show', $c->claim_id) . '" class="btn btn-sm btn-outline-primary"><i class="ri-eye-line"></i> Detail</a>')
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function claimCreate()
    {
        $data = [
            'damages' => DamageReport::whereNotIn('status', ['closed', 'rejected'])->get(),
        ];

        return view('fleet.insurance-claim.form')->with(array_merge([
            'title' => 'Buat Klaim Asuransi',
            'subtitle' => 'Ajukan Klaim Asuransi',
            'item' => null,
        ], $data));
    }

    public function claimStore(Request $request)
    {
        try {
            $validated = $request->validate([
                'damage_id' => 'required|exists:tr_damage_report,damage_id',
                'insurance_provider' => 'required|string',
                'policy_number' => 'nullable|string',
                'claim_date' => 'required|date',
                'claim_amount' => 'required|numeric',
                'notes' => 'nullable|string',
            ]);

            $claimNumber = $this->gen_number(InsuranceClaim::class, 'claim_number', 'CLM-$/#####', now(), 'created_at', true);

            $rentalId = DamageReport::find($validated['damage_id'])?->rental_id;

            InsuranceClaim::create(array_merge($validated, [
                'claim_number' => $claimNumber,
                'rental_id' => $rentalId,
                'status' => 'submitted',
            ]));

            return redirect()->route('fleet.insurance-claim.index')
                ->withSuccess('Klaim asuransi berhasil diajukan.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function claimShow($id)
    {
        $item = InsuranceClaim::with(['damageReport.vehicle.model.brand', 'damageReport.photos', 'rental.customer'])
            ->findOrFail($id);

        return view('fleet.insurance-claim.show')->with([
            'title' => 'Detail Klaim Asuransi',
            'subtitle' => 'Klaim ' . ($item->claim_number ?? ''),
            'item' => $item,
        ]);
    }

    public function claimUpdateStatus(Request $request, $id)
    {
        try {
            $item = InsuranceClaim::findOrFail($id);
            $request->validate(['status' => 'required|in:draft,submitted,under_review,approved,rejected,paid,closed']);

            $update = ['status' => $request->status];
            if ($request->status === 'approved') {
                $update['approved_date'] = now();
                $update['approved_amount'] = $request->approved_amount ?? $item->claim_amount;
            }

            $item->update($update);

            return response()->json(['status' => true, 'msg' => 'Status klaim diperbarui.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }
}
