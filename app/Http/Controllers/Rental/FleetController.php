<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\NumberTrait;
use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\Fine;
use App\Models\InsuranceClaim;
use App\Models\Maintenance;
use App\Models\MaintenanceType;
use App\Models\Rental;
use App\Models\Vehicle;
use App\Models\Workshop;
use App\Services\AccountingService;
use App\Services\FineSettlementService;
use App\Support\AppSettings;
use DB;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FleetController extends Controller
{
    use NumberTrait;

    /** Batas jumlah foto per laporan kerusakan (FLE-13). */
    private const MAX_PHOTOS_PER_DAMAGE = 10;

    /**
     * Status kerusakan yang menerima unggahan foto. Laporan ditutup/ditolak
     * dibekukan agar bukti tidak berubah setelah keputusan (FLE-13).
     */
    private const PHOTO_EDITABLE_STATUSES = ['reported', 'inspected', 'assessment', 'approved', 'in_repair', 'repair_in_progress'];

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
            ->addColumn('vehicle_info', fn ($m) => $m->vehicle?->license_plate.' - '.($m->vehicle?->model?->brand?->brand_name ?? '').' '.($m->vehicle?->model?->model_name ?? ''))
            ->addColumn('type', fn ($m) => $m->maintenanceType?->type_name ?? '-')
            ->addColumn('workshop_name', fn ($m) => $m->workshop?->name ?? '-')
            ->addColumn('scheduled_date', fn ($m) => $m->scheduled_date?->format('d/m/Y') ?? '-')
            // FLE-14: dua desimal agar sen ikut tampil (selaras FIN-14).
            ->addColumn('cost', fn ($m) => AppSettings::money($m->cost ?? 0))
            ->addColumn('status_badge', fn ($m) => view('components.maintenance-status-badge', ['status' => $m->status])->render())
            ->rawColumns(['status_badge'])
            ->toJson();
    }

    public function maintenanceButtonOption(Request $request)
    {
        try {
            $request->validate(['id' => 'required|integer|min:1']);
            $item = Maintenance::findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('fleet.maintenance.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Data maintenance tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    public function maintenanceCreate()
    {
        $data = [
            'vehicles' => Vehicle::whereIn('status', ['available', 'maintenance'])->get(),
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
        // Validasi di LUAR transaksi: ValidationException tidak boleh memicu
        // rollback level 0 yang meracuni koneksi untuk request berikutnya.
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:m_vehicle,vehicle_id',
            'maintenance_type_id' => 'required|exists:m_maintenance_type,type_id',
            'workshop_id' => 'nullable|exists:m_workshop,workshop_id',
            'scheduled_date' => 'required|date',
            'current_mileage' => 'nullable|integer|min:0',
            // FLE-10: nominal uang dibatasi kapasitas DECIMAL(12,2), tidak negatif.
            'cost' => 'nullable|numeric|min:0|max:9999999999.99',
            'next_maintenance_km' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                $item = Maintenance::create(array_merge($validated, ['status' => 'scheduled']));

                // FLE-03 (bagian 1): jadwal baru yang langsung dikerjakan boleh memindahkan
                // kendaraan available → maintenance, TAPI tidak pernah menyentuh kendaraan
                // yang sedang disewa (rented) atau reserved.
                if ($item->vehicle && $item->vehicle->status === 'available') {
                    $item->vehicle->update(['status' => 'maintenance']);
                }

                return redirect()->route('fleet.maintenance.index')
                    ->withSuccess('Jadwal maintenance berhasil ditambahkan.');
            });
        } catch (Exception $e) {
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
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:m_vehicle,vehicle_id',
            'maintenance_type_id' => 'required|exists:m_maintenance_type,type_id',
            'workshop_id' => 'nullable|exists:m_workshop,workshop_id',
            'scheduled_date' => 'required|date',
            'actual_date' => 'nullable|date',
            'current_mileage' => 'nullable|integer|min:0',
            'cost' => 'nullable|numeric|min:0|max:9999999999.99',
            'next_maintenance_km' => 'nullable|integer|min:0',
            'description' => 'nullable|string|max:2000',
            'status' => 'required|in:scheduled,overdue,in_progress,completed,cancelled',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            return DB::transaction(function () use ($id, $validated) {
                // FLE-04/FLE-06: kunci baris di dalam transaksi agar dua update paralel
                // membaca state yang sama secara serial.
                $item = Maintenance::lockForUpdate()->findOrFail($id);

                // FLE-03: state machine kendaraan. Transisi status kendaraan hanya terjadi
                // bila kondisi aman — kendaraan rented tidak pernah dipaksa berubah.
                $this->syncVehicleStatusForMaintenance($item, $validated['status']);

                $item->update($validated);

                return redirect()->route('fleet.maintenance.index')
                    ->withSuccess('Jadwal maintenance berhasil diperbarui.');
            });
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function maintenanceComplete(Request $request, $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $item = Maintenance::lockForUpdate()->findOrFail($id);

                if ($item->status === 'completed') {
                    return response()->json(['status' => false, 'msg' => 'Maintenance ini sudah ditandai selesai.'], 409);
                }

                $item->update([
                    'status' => 'completed',
                    'actual_date' => now(),
                ]);

                $this->syncVehicleStatusForMaintenance($item, 'completed');

                return response()->json(['status' => true, 'msg' => 'Maintenance ditandai selesai.']);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Data maintenance tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    public function maintenanceReschedule(Request $request, $id)
    {
        // FLE-05: tanggal baru tidak boleh di masa lalu.
        $validated = $request->validate([
            'scheduled_date' => 'required|date|after_or_equal:today',
        ]);

        try {
            return DB::transaction(function () use ($id, $validated) {
                // FLE-05: reschedule hanya untuk jadwal terjadwal/terlambat (bukan yang
                // selesai/dibatalkan) dan tidak boleh menabrak masa sewa aktif kendaraan.
                $item = Maintenance::lockForUpdate()
                    ->whereIn('status', ['scheduled', 'overdue'])
                    ->findOrFail($id);

                $vehicleId = $item->vehicle_id;
                $newStart = $validated['scheduled_date'].' 00:00:00';
                $newEnd = $validated['scheduled_date'].' 23:59:59';

                $conflicts = Rental::where('vehicle_id', $vehicleId)
                    ->whereIn('status', ['reserved', 'ongoing', 'overdue'])
                    ->where(function ($q) use ($newStart, $newEnd) {
                        $q->whereBetween('rental_start_date', [$newStart, $newEnd])
                            ->orWhereBetween('rental_end_date', [$newStart, $newEnd])
                            ->orWhere(function ($q2) use ($newStart, $newEnd) {
                                $q2->where('rental_start_date', '<=', $newStart)
                                    ->where('rental_end_date', '>=', $newEnd);
                            });
                    })
                    ->exists();

                if ($conflicts) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Tanggal baru berbenturan dengan masa sewa aktif kendaraan ini.',
                    ], 409);
                }

                $item->update([
                    'scheduled_date' => $validated['scheduled_date'],
                    'status' => 'scheduled',
                ]);

                return response()->json(['status' => true, 'msg' => 'Jadwal berhasil diubah.']);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Jadwal tidak ditemukan atau sudah selesai/dibatalkan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    /**
     * FLE-03: pemindahan status kendaraan akibat perubahan status maintenance.
     *
     * Aturan:
     * - in_progress  : kendaraan available → maintenance (yang rented/reserved tidak disentuh).
     * - completed    : kendaraan maintenance → available HANYA bila kendaraan sedang
     *                  tidak dipakai sewa (rental aktif).
     * - cancelled    : kendaraan yang diparkir maintenance oleh jadwal ini kembali available.
     */
    private function syncVehicleStatusForMaintenance(Maintenance $item, string $status): void
    {
        $vehicle = $item->vehicle()->lockForUpdate()->first();
        if (! $vehicle) {
            return;
        }

        if ($status === 'in_progress') {
            if ($vehicle->status === 'available') {
                $vehicle->update(['status' => 'maintenance']);
            }

            return;
        }

        if ($status === 'completed') {
            if ($vehicle->status !== 'maintenance') {
                // Kendaraan tidak dalam status maintenance (mis. sedang rented):
                // jangan ubah apa pun (FLE-03 — larang lompatan rented → available).
                return;
            }

            $activeRental = Rental::where('vehicle_id', $vehicle->vehicle_id)
                ->whereIn('status', ['reserved', 'ongoing', 'overdue'])
                ->exists();

            if (! $activeRental) {
                $vehicle->update(['status' => 'available']);
            }

            return;
        }

        if ($status === 'cancelled' && $vehicle->status === 'maintenance') {
            $stillInShop = Maintenance::where('vehicle_id', $vehicle->vehicle_id)
                ->where('maintenance_id', '!=', $item->maintenance_id)
                ->whereIn('status', ['scheduled', 'overdue', 'in_progress'])
                ->exists();

            if (! $stillInShop) {
                $vehicle->update(['status' => 'available']);
            }
        }
    }

    // ============================================================
    // DAMAGE REPORT
    // ============================================================

    public function damageIndex()
    {
        return view('fleet.damage.index')->with([
            'title' => 'Kerusakan & Klaim',
            'subtitle' => 'Laporan Kerusakan & Klaim Asuransi',
        ]);
    }

    public function damageData(Request $request)
    {
        $query = DamageReport::query()->with(['vehicle.model.brand', 'insuranceClaim']);

        // FLE-15: filter status hanya menerima nilai enum yang valid.
        if ($request->filled('status')) {
            $request->validate(['status' => ['required', 'in:'.implode(',', DamageReport::STATUSES)]]);
            $query->where('status', $request->status);
        }

        return datatables()->of($query)
            ->addColumn('vehicle_info', fn ($d) => $d->vehicle?->license_plate.' - '.($d->vehicle?->model?->brand?->brand_name ?? '').' '.($d->vehicle?->model?->model_name ?? ''))
            ->addColumn('damage_type', fn ($d) => ucfirst(str_replace('_', ' ', $d->damage_type ?? '-')))
            ->addColumn('severity', fn ($d) => ucfirst($d->severity ?? '-'))
            ->addColumn('reported_date', fn ($d) => $d->reported_date?->format('d/m/Y') ?? '-')
            // Nilai tampil: biaya aktual bila > 0, selain itu estimasi (cast decimal:2
            // menghasilkan "0.00" yang truthy — cek numerik, bukan truthiness).
            ->addColumn('repair_cost', function ($d) {
                $cost = (float) $d->actual_repair_cost;
                if ($cost <= 0) {
                    $cost = (float) ($d->repair_cost_estimate ?? 0);
                }

                return AppSettings::money($cost);
            })
            ->addColumn('status_badge', fn ($d) => view('components.damage-status-badge', ['status' => $d->status])->render())
            ->addColumn('claim_status_badge', fn ($d) => $d->insuranceClaim ? view('components.claim-status-badge', ['status' => $d->insuranceClaim->status])->render() : '<span class="text-muted">-</span>')
            ->addColumn('claim_amount', fn ($d) => $d->insuranceClaim ? AppSettings::money($d->insuranceClaim->claim_amount ?? 0) : '-')
            ->rawColumns(['status_badge', 'claim_status_badge'])
            ->toJson();
    }

    public function damageButtonOption(Request $request)
    {
        try {
            $request->validate(['id' => 'required|integer|min:1']);
            $item = DamageReport::with('insuranceClaim')->findOrFail($request->get('id'));

            return response()->json([
                'status' => true,
                'view' => view('fleet.damage.button_option')->with(['item' => $item])->render(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Laporan kerusakan tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
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
        $validated = $request->validate([
            'vehicle_id' => 'required|exists:m_vehicle,vehicle_id',
            'rental_id' => 'nullable|exists:tr_rental,rental_id',
            'reported_date' => 'required|date',
            // FLE-11: enum sesuai skema database, bukan bebas string.
            'damage_type' => ['required', 'in:'.implode(',', DamageReport::DAMAGE_TYPES)],
            'severity' => ['required', 'in:'.implode(',', DamageReport::SEVERITIES)],
            'location' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:2000',
            'repair_cost_estimate' => 'nullable|numeric|min:0|max:9999999999.99',
            'notes' => 'nullable|string|max:2000',
        ]);

        // FLE-11: rental terkait wajib milik kendaraan yang dipilih.
        if (! empty($validated['rental_id'])) {
            $rentalVehicleId = Rental::where('rental_id', $validated['rental_id'])->value('vehicle_id');
            if ((int) $rentalVehicleId !== (int) $validated['vehicle_id']) {
                return redirect()->back()->withInput()
                    ->withErrors('Sewa terkait tidak sesuai dengan kendaraan yang dipilih.');
            }
        }

        try {
            DamageReport::create(array_merge($validated, ['status' => 'reported']));

            return redirect()->route('fleet.damage.index')
                ->withSuccess('Laporan kerusakan berhasil ditambahkan.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function damageShow($id)
    {
        $item = DamageReport::with(['vehicle.model.brand', 'rental.customer', 'inspector', 'photos', 'insuranceClaim', 'fines'])
            ->findOrFail($id);

        return view('fleet.damage.show')->with([
            'title' => 'Detail Kerusakan',
            'subtitle' => 'Laporan Kerusakan #'.$item->damage_id,
            'item' => $item,
        ]);
    }

    public function damageUpdateStatus(Request $request, $id)
    {
        // FLE-02: set status kanonik — sama dengan enum DB, dropdown UI, dan badge.
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', DamageReport::STATUSES)],
        ]);

        try {
            return DB::transaction(function () use ($id, $validated) {
                $item = DamageReport::lockForUpdate()->findOrFail($id);

                $item->update([
                    'status' => $validated['status'],
                    'inspected_by' => auth()->user()->employee_id ?? null,
                    'inspected_at' => now(),
                ]);

                return response()->json(['status' => true, 'msg' => 'Status kerusakan diperbarui.']);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Laporan kerusakan tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    /**
     * FLE-12: jalur koreksi biaya aktual perbaikan — nilai ini menjadi dasar
     * penagihan penyewa (damageBillRenter) sehingga harus dapat diperbarui.
     */
    public function damageUpdateCost(Request $request, $id)
    {
        $validated = $request->validate([
            'actual_repair_cost' => 'required|numeric|min:0|max:9999999999.99',
        ]);

        try {
            return DB::transaction(function () use ($id, $validated) {
                $item = DamageReport::lockForUpdate()->findOrFail($id);

                // Konsisten dengan bekuan bukti (FLE-13): laporan final tidak diubah lagi.
                if (in_array($item->status, ['closed', 'rejected', 'written_off'], true)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Laporan berstatus '.($item->status ?? '-').' tidak dapat diubah.',
                    ], 409);
                }

                $item->update(['actual_repair_cost' => $validated['actual_repair_cost']]);

                return response()->json([
                    'status' => true,
                    'msg' => 'Biaya aktual diperbarui menjadi '.AppSettings::money($validated['actual_repair_cost']).'.',
                ]);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Laporan kerusakan tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    public function damagePhotoUpload(Request $request, $id)
    {
        // Dukungan multi-file sekaligus: field 'photos[]' dan fallback 'photo' tunggal.
        // Catatan: daftar file dibaca langsung dari FileBag — $request->merge() tidak
        // memengaruhi ->file(), sehingga penagihan validasi manual per file di bawah.
        $files = array_values(array_filter(array_merge(
            (array) $request->file('photos', []),
            $request->hasFile('photo') ? [$request->file('photo')] : []
        )));

        if (empty($files)) {
            return response()->json(['status' => false, 'msg' => 'Tidak ada file foto yang diunggah.'], 422);
        }

        if (count($files) > self::MAX_PHOTOS_PER_DAMAGE) {
            return response()->json(['status' => false, 'msg' => 'Maksimal '.self::MAX_PHOTOS_PER_DAMAGE.' foto per unggahan.'], 422);
        }

        foreach ($files as $file) {
            $validator = Validator::make(['photo' => $file], ['photo' => 'image|max:5120']);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'msg' => $validator->errors()->first()], 422);
            }
        }

        try {
            return DB::transaction(function () use ($id, $files, $request) {
                $item = DamageReport::lockForUpdate()->findOrFail($id);

                // FLE-13: laporan yang ditutup/ditolak dibekukan perubahannya.
                if (! in_array($item->status, self::PHOTO_EDITABLE_STATUSES, true)) {
                    return response()->json([
                        'status' => false,
                        'msg' => 'Foto tidak dapat ditambahkan pada laporan berstatus '.($item->status ?? '-').'.',
                    ], 409);
                }

                // FLE-13: batas total foto per laporan.
                $existingCount = $item->photos()->count();
                $incoming = count($files);
                if ($existingCount + $incoming > self::MAX_PHOTOS_PER_DAMAGE) {
                    return response()->json([
                        'status' => false,
                        'msg' => sprintf(
                            'Batas %d foto per laporan terlampaui (ada %d, akan menambah %d).',
                            self::MAX_PHOTOS_PER_DAMAGE,
                            $existingCount,
                            $incoming
                        ),
                    ], 422);
                }

                $saved = 0;
                foreach ($files as $file) {
                    $path = $file->store('damage-photos', 'public');
                    DamagePhoto::create([
                        'damage_id' => $item->damage_id,
                        'photo_url' => $path,
                        'caption' => $request->caption ?? null,
                    ]);
                    $saved++;
                }

                return response()->json(['status' => true, 'msg' => $saved.' foto berhasil diunggah.', 'count' => $saved]);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Laporan kerusakan tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    /**
     * Konversi biaya perbaikan kerusakan menjadi denda ke penyewa terkait.
     * FLE-07/FLE-08: identitas kewajiban lewat tr_fine.damage_id (bukan LIKE deskripsi),
     * dedup dengan lock, dan jurnal akrual piutang dibuat atomik dengan penagihan.
     */
    public function damageBillRenter(Request $request, $id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $item = DamageReport::with('rental.customer')->lockForUpdate()->findOrFail($id);

                if (! $item->rental_id || ! $item->rental) {
                    return response()->json(['status' => false, 'msg' => 'Laporan kerusakan ini tidak terhubung ke data sewa.'], 409);
                }

                // Catatan: cast decimal:2 menghasilkan string "0.00" yang truthy di PHP —
                // fallback harus berdasarkan nilai numerik, bukan truthiness.
                $amount = (float) $item->actual_repair_cost;
                if ($amount <= 0) {
                    $amount = (float) ($item->repair_cost_estimate ?? 0);
                }
                if ($amount <= 0) {
                    return response()->json(['status' => false, 'msg' => 'Biaya perbaikan belum diisi, tidak dapat ditagihkan.'], 422);
                }

                // FLE-07: dedup berdasarkan kolom identitas, di dalam lock — aman terhadap
                // klik ganda dan tidak bergantung pada teks deskripsi bebas.
                $existing = Fine::forDamage((int) $item->damage_id)->lockForUpdate()->first();
                if ($existing) {
                    return response()->json(['status' => false, 'msg' => 'Biaya kerusakan ini sudah pernah ditagihkan sebagai denda.'], 409);
                }

                $fine = Fine::create([
                    'rental_id' => $item->rental_id,
                    'damage_id' => $item->damage_id,
                    'fine_type' => 'damage',
                    'description' => 'Tagihan biaya perbaikan kerusakan #'.$item->damage_id.' ('.($item->damage_type ?? 'damage').')',
                    'amount' => $amount,
                    'status' => 'unpaid',
                    'issued_date' => now(),
                    'issued_by' => auth()->user()->employee_id ?? null,
                    'notes' => 'Ditagihkan otomatis dari laporan kerusakan oleh '.(auth()->user()->name ?? 'sistem'),
                ]);

                // FLE-08: akrual saat ditagih via service terpusat — piutang sewa bertambah,
                // pendapatan denda diakui. Hard post: gagal pemetaan COA = gagal penagihan
                // (rollback), bukan jurnal hilang.
                app(FineSettlementService::class)->accrueDamageCharge($fine, $item);

                return response()->json([
                    'status' => true,
                    'msg' => AppSettings::money($amount).' berhasil ditagihkan sebagai denda ke penyewa.',
                    'data' => $fine,
                ]);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Laporan kerusakan tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    public function damagePhotoDelete($photoId)
    {
        try {
            return DB::transaction(function () use ($photoId) {
                // FLE-13: file fisik ikut dihapus agar tidak menyisakan file yatim.
                $photo = DamagePhoto::lockForUpdate()->findOrFail($photoId);

                if (Storage::disk('public')->exists($photo->photo_url)) {
                    Storage::disk('public')->delete($photo->photo_url);
                }

                $photo->delete();

                return response()->json(['status' => true, 'msg' => 'Foto berhasil dihapus.']);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Foto tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }

    // ============================================================
    // INSURANCE CLAIM (dikelola dari halaman Kerusakan & Klaim)
    // ============================================================

    public function claimCreate(Request $request)
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
        $validated = $request->validate([
            'damage_id' => 'required|exists:tr_damage_report,damage_id',
            'insurance_provider' => 'required|string|max:100',
            'policy_number' => 'nullable|string|max:50',
            'claim_date' => 'required|date',
            // FLE-10: nominal sesuai kapasitas DECIMAL(12,2), tidak negatif.
            'claim_amount' => 'required|numeric|min:0.01|max:9999999999.99',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // Kardinalitas satu klaim per kerusakan dijaga di level aplikasi
                // (unique index DB tetap pagar terakhir).
                $taken = InsuranceClaim::where('damage_id', $validated['damage_id'])->lockForUpdate()->exists();
                if ($taken) {
                    return redirect()->back()->withInput()
                        ->withErrors('Kerusakan ini sudah memiliki klaim asuransi.');
                }

                $claimNumber = $this->gen_number(InsuranceClaim::class, 'claim_number', 'CLM-$/#####', now(), 'created_at', true);

                $rentalId = DamageReport::find($validated['damage_id'])?->rental_id;

                InsuranceClaim::create(array_merge($validated, [
                    'claim_number' => $claimNumber,
                    'rental_id' => $rentalId,
                    'status' => 'submitted',
                ]));

                return redirect()->route('fleet.damage.index')
                    ->withSuccess('Klaim asuransi berhasil diajukan.');
            });
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
            'subtitle' => 'Klaim '.($item->claim_number ?? ''),
            'item' => $item,
        ]);
    }

    public function claimUpdateStatus(Request $request, $id)
    {
        // FLE-12: closed kini bagian kontrak status (enum DB diperluas).
        $validated = $request->validate([
            'status' => 'required|in:draft,submitted,under_review,approved,rejected,paid,closed',
            'approved_amount' => 'nullable|numeric|min:0|max:9999999999.99',
        ]);

        try {
            return DB::transaction(function () use ($id, $validated) {
                // FLE-06: lock baris klaim sebelum membaca status — dua klik ganda
                // menjadi serial sehingga jurnal pencairan tidak bisa terjadi dua kali.
                $item = InsuranceClaim::lockForUpdate()->findOrFail($id);

                $previousStatus = $item->status;
                $update = ['status' => $validated['status']];

                if ($validated['status'] === 'approved') {
                    if ($previousStatus === 'paid') {
                        return response()->json(['status' => false, 'msg' => 'Klaim yang sudah dibayar tidak dapat diubah ke disetujui.'], 409);
                    }

                    $update['approved_date'] = now();
                    $update['approved_amount'] = $validated['approved_amount'] ?? $item->claim_amount;
                }

                if ($validated['status'] === 'paid') {
                    if ($previousStatus === 'paid') {
                        return response()->json(['status' => false, 'msg' => 'Klaim ini sudah dibayar sebelumnya.'], 409);
                    }

                    if (! in_array($previousStatus, ['approved', 'under_review'], true)) {
                        return response()->json(['status' => false, 'msg' => 'Klaim harus disetujui sebelum dibayar.'], 409);
                    }

                    // FLE-09: guard nominal SEBELUM status ditulis, bukan sesudahnya.
                    $amount = (float) ($validated['approved_amount'] ?? $item->approved_amount ?: $item->claim_amount ?? 0);
                    if ($amount <= 0) {
                        return response()->json(['status' => false, 'msg' => 'Nilai disetujui klaim tidak valid untuk pencairan.'], 422);
                    }

                    $update['paid_date'] = now();
                    $update['paid_at'] = now();
                    $update['approved_amount'] = $amount;
                    if (! $item->approved_date) {
                        $update['approved_date'] = now();
                    }
                }

                $item->update($update);

                // FLE-09: jurnal pencairan dibukukan HARD di dalam transaksi perubahan status.
                // Gagal posting (COA tidak terpetakan, tutup buku) = rollback status klaim.
                if ($validated['status'] === 'paid' && $previousStatus !== 'paid') {
                    $service = app(AccountingService::class);
                    $amount = (float) ($update['approved_amount'] ?? 0);

                    $service->post(
                        now()->toDateString(),
                        'CLM-'.$item->claim_number,
                        'Pencairan klaim asuransi '.($item->claim_number ?? '#'.$item->claim_id),
                        'insurance',
                        [
                            ['account' => $service->resolveCoa('1-1200'), 'debit' => $amount, 'credit' => 0],
                            ['account' => $service->resolveCoa('4-3000'), 'debit' => 0, 'credit' => $amount],
                        ]
                    );
                }

                return response()->json(['status' => true, 'msg' => 'Status klaim diperbarui.']);
            });
        } catch (ModelNotFoundException) {
            return response()->json(['status' => false, 'msg' => 'Klaim tidak ditemukan.'], 404);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()], 422);
        }
    }
}
