<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Fine;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Promo;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\RentalExtension;
use App\Models\Refund;
use App\Models\ReturnCar;
use App\Models\Vehicle;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function __construct(Rental $model)
    {
        $this->middleware('auth');
        $this->model = $model;
    }

    public function create()
    {
        $step = session()->get('rental_wizard_step', 1);
        $data = session()->get('rental_wizard_data', []);

        $view = [
            'title' => 'Buat Sewa',
            'subtitle' => 'Buat Sewa Baru',
            'step' => $step,
            'data' => $data,
            'customers' => Customer::orderBy('first_name')->get(),
            'locations' => Location::active()->get(),
            'drivers' => Driver::active()->licenseValid()->get(),
            'promos' => Promo::active()->get(),
        ];

        return view('rental.create')->with($view);
    }

    public function createStep(Request $request, $step)
    {
        $request->session()->put('rental_wizard_step', $step);

        if ($request->ajax()) {
            return $this->renderStep($request, $step);
        }

        return redirect()->route('rental.create');
    }

    public function store(Request $request)
    {
        try {
            DB::beginTransaction();

            $data = $request->session()->get('rental_wizard_data', []);

            if (empty($data['customer_id']) || empty($data['vehicle_id'])) {
                throw new Exception('Data pelanggan dan kendaraan harus diisi.');
            }

            $vehicle = Vehicle::with('model')->findOrFail($data['vehicle_id']);
            $startDate = Carbon::parse($data['rental_start_date']);
            $endDate = Carbon::parse($data['rental_end_date']);
            $rentalDays = $startDate->diffInDays($endDate) ?: 1;

            $baseRate = $vehicle->model->base_price_per_day;
            $totalBase = $baseRate * $rentalDays;
            $insuranceFee = $totalBase * ($vehicle->model->insurance_rate / 100);
            $driverFee = ($data['is_with_driver'] ?? false) ? ($data['driver_fee'] ?? 0) : 0;
            $discountAmount = 0;
            $taxAmount = 0;
            $depositAmount = $vehicle->model->deposit_amount ?? 0;
            $totalAmount = $totalBase + $insuranceFee + $driverFee;

            if (!empty($data['promo_id'])) {
                $promo = Promo::find($data['promo_id']);
                if ($promo) {
                    if ($promo->discount_type === 'percentage') {
                        $discountAmount = $totalAmount * ($promo->discount_value / 100);
                    } else {
                        $discountAmount = $promo->discount_value;
                    }
                    $totalAmount -= $discountAmount;
                }
            }

            $taxAmount = $totalAmount * 0.11;
            $totalAmount += $taxAmount;

            $rental = $this->model->create([
                'rental_code' => $this->gen_number($this->model, 'rental_code', 'RNT-$/#####', now(), 'created_at', true),
                'customer_id' => $data['customer_id'],
                'vehicle_id' => $data['vehicle_id'],
                'employee_id' => auth()->user()->employee_id ?? null,
                'pickup_location_id' => $data['pickup_location_id'] ?? null,
                'return_location_id' => $data['return_location_id'] ?? null,
                'promo_id' => $data['promo_id'] ?? null,
                'rental_start_date' => $startDate,
                'rental_end_date' => $endDate,
                'rental_days' => $rentalDays,
                'is_with_driver' => $data['is_with_driver'] ?? false,
                'driver_id' => $data['driver_id'] ?? null,
                'base_rate_per_day' => $baseRate,
                'total_base_price' => $totalBase,
                'insurance_fee' => $insuranceFee,
                'driver_fee' => $driverFee,
                'young_driver_fee' => 0,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'deposit_amount' => $depositAmount,
                'total_amount' => $totalAmount,
                'status' => 'reserved',
                'payment_status' => 'unpaid',
                'notes' => $data['notes'] ?? null,
            ]);

            if (!empty($data['addons'])) {
                foreach ($data['addons'] as $addon) {
                    RentalDetail::create([
                        'rental_id' => $rental->rental_id,
                        'item_type' => $addon['item_type'] ?? 'other',
                        'item_name' => $addon['item_name'],
                        'quantity' => $addon['quantity'] ?? 1,
                        'unit_price' => $addon['unit_price'] ?? 0,
                        'total_price' => ($addon['quantity'] ?? 1) * ($addon['unit_price'] ?? 0),
                    ]);
                }
            }

            $vehicle->update(['status' => 'reserved']);

            DB::commit();

            $request->session()->forget(['rental_wizard_step', 'rental_wizard_data']);

            if ($request->ajax()) {
                return response()->json([
                    'status' => true,
                    'msg' => 'Sewa berhasil dibuat. Kode: ' . $rental->rental_code,
                    'redirect' => route('rental.show', $rental->rental_id),
                ]);
            }

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Sewa berhasil dibuat. Kode: ' . $rental->rental_code);
        } catch (Exception $e) {
            DB::rollback();

            if ($request->ajax()) {
                return response()->json([
                    'status' => false,
                    'msg' => $e->getMessage(),
                ]);
            }

            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function searchCustomer(Request $request)
    {
        $search = $request->get('q', '');
        $customers = Customer::where(function ($q) use ($search) {
            $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        })
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->customer_id,
                    'text' => $c->full_name . ' - ' . $c->phone,
                    'full_name' => $c->full_name,
                    'phone' => $c->phone,
                    'email' => $c->email,
                    'customer_type' => $c->customer_type,
                    'is_verified' => $c->is_verified,
                ];
            });

        return response()->json(['results' => $customers]);
    }

    public function availableVehicles(Request $request)
    {
        $startDate = Carbon::parse($request->get('start_date'));
        $endDate = Carbon::parse($request->get('end_date'));

        $rentedVehicleIds = Rental::whereIn('status', ['ongoing', 'reserved'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('rental_start_date', [$startDate, $endDate])
                    ->orWhereBetween('rental_end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('rental_start_date', '<=', $startDate)
                            ->where('rental_end_date', '>=', $endDate);
                    });
            })
            ->pluck('vehicle_id');

        $vehicles = Vehicle::with('model.brand')
            ->where('status', 'available')
            ->whereNotIn('vehicle_id', $rentedVehicleIds)
            ->get()
            ->map(function ($v) {
                $model = $v->model;
                $brand = $model?->brand;

                return [
                    'id' => $v->vehicle_id,
                    'text' => $v->license_plate . ' - ' . ($brand?->brand_name ?? '') . ' ' . ($model?->model_name ?? ''),
                    'license_plate' => $v->license_plate,
                    'model_name' => $model?->model_name ?? '',
                    'brand_name' => $brand?->brand_name ?? '',
                    'year' => $v->year,
                    'color' => $v->color,
                    'transmission' => $model?->transmission ?? '',
                    'seat_capacity' => $model?->seat_capacity ?? '',
                    'base_price_per_day' => $model?->base_price_per_day ?? 0,
                    'deposit_amount' => $model?->deposit_amount ?? 0,
                ];
            });

        return response()->json(array_values($vehicles->toArray()));
    }

    public function calculateTotal(Request $request)
    {
        $vehicleId = $request->get('vehicle_id');
        $startDate = Carbon::parse($request->get('rental_start_date'));
        $endDate = Carbon::parse($request->get('rental_end_date'));
        $rentalDays = $startDate->diffInDays($endDate) ?: 1;
        $withDriver = $request->boolean('is_with_driver');
        $promoId = $request->get('promo_id');

        $vehicle = Vehicle::with('model')->findOrFail($vehicleId);
        $baseRate = $vehicle->model->base_price_per_day;
        $totalBase = $baseRate * $rentalDays;
        $insuranceFee = $totalBase * ($vehicle->model->insurance_rate / 100);
        $depositAmount = $vehicle->model->deposit_amount ?? 0;
        $driverFee = 0;

        if ($withDriver) {
            $driver = Driver::find($request->get('driver_id'));
            if ($driver) {
                $driverFee = $request->get('driver_fee', 150000);
            }
        }

        $subtotal = $totalBase + $insuranceFee + $driverFee;
        $discountAmount = 0;

        if ($promoId) {
            $promo = Promo::find($promoId);
            if ($promo && $rentalDays >= $promo->min_rental_days) {
                if ($promo->discount_type === 'percentage') {
                    $discountAmount = $subtotal * ($promo->discount_value / 100);
                } else {
                    $discountAmount = $promo->discount_value;
                }
            }
        }

        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = $afterDiscount * 0.11;
        $totalAmount = $afterDiscount + $taxAmount;

        return response()->json([
            'status' => true,
            'data' => [
                'base_rate_per_day' => (float) $baseRate,
                'rental_days' => $rentalDays,
                'total_base_price' => round($totalBase, 2),
                'insurance_fee' => round($insuranceFee, 2),
                'driver_fee' => round($driverFee, 2),
                'discount_amount' => round($discountAmount, 2),
                'tax_amount' => round($taxAmount, 2),
                'deposit_amount' => (float) $depositAmount,
                'total_amount' => round($totalAmount, 2),
            ],
        ]);
    }

    private function renderStep(Request $request, $step)
    {
        $data = $request->session()->get('rental_wizard_data', []);

        $viewData = [
            'step' => $step,
            'data' => $data,
            'customers' => Customer::orderBy('first_name')->get(),
            'locations' => Location::active()->get(),
            'drivers' => Driver::active()->licenseValid()->get(),
            'promos' => Promo::active()->get(),
            'vehicles' => Vehicle::with('model.brand')->where('status', 'available')->get(),
        ];

        $view = match ((int) $step) {
            2 => view('rental._step-2', $viewData)->render(),
            3 => view('rental._step-3', $viewData)->render(),
            4 => view('rental._step-4', $viewData)->render(),
            default => view('rental._step-1', $viewData)->render(),
        };

        return response()->json([
            'status' => true,
            'view' => $view,
            'step' => (int) $step,
        ]);
    }

    public function saveStep(Request $request)
    {
        $step = $request->get('step', 1);
        $data = $request->session()->get('rental_wizard_data', []);

        $rules = match ((int) $step) {
            2 => ['vehicle_id' => 'required'],
            3 => ['rental_start_date' => 'required|date', 'rental_end_date' => 'required|date|after:rental_start_date'],
            default => ['customer_id' => 'required'],
        };

        $request->validate($rules);

        $data = array_merge($data, $request->except('_token', 'step'));
        $request->session()->put('rental_wizard_data', $data);
        $request->session()->put('rental_wizard_step', (int) $step + 1);

        return response()->json([
            'status' => true,
            'step' => (int) $step + 1,
        ]);
    }

    public function index(Request $request)
    {
        $tabs = [
            'all' => 'Semua',
            'reserved' => 'Reservasi',
            'ongoing' => 'Berjalan',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            'overdue' => 'Terlambat',
        ];

        $counts = [
            'all' => Rental::count(),
            'reserved' => Rental::where('status', 'reserved')->count(),
            'ongoing' => Rental::where('status', 'ongoing')->count(),
            'completed' => Rental::where('status', 'completed')->count(),
            'cancelled' => Rental::where('status', 'cancelled')->count(),
            'overdue' => Rental::where('status', 'ongoing')->where('rental_end_date', '<', now())->count(),
        ];

        return view('rental.index')->with([
            'title' => 'Sewa',
            'subtitle' => 'Daftar Transaksi Sewa',
            'status' => $request->get('status', 'all'),
            'tabs' => $tabs,
            'counts' => $counts,
        ]);
    }

    public function data(Request $request)
    {
        $status = $request->get('status', 'all');

        $rentals = Rental::with(['customer', 'vehicle.model.brand', 'pickupLocation']);

        match ($status) {
            'reserved' => $rentals->where('status', 'reserved'),
            'ongoing' => $rentals->where('status', 'ongoing'),
            'completed' => $rentals->where('status', 'completed'),
            'cancelled' => $rentals->where('status', 'cancelled'),
            'overdue' => $rentals->where('status', 'ongoing')->where('rental_end_date', '<', now()),
            default => null,
        };

        $rentals->orderBy('created_at', 'desc');

        return datatables()->of($rentals)
            ->addColumn('rental_code', fn($r) => $r->rental_code)
            ->addColumn('customer_name', fn($r) => $r->customer?->full_name)
            ->addColumn('vehicle_info', fn($r) => $r->vehicle?->license_plate . ' - ' . ($r->vehicle?->model?->brand?->brand_name ?? '') . ' ' . ($r->vehicle?->model?->model_name ?? ''))
            ->addColumn('pickup', fn($r) => $r->pickupLocation?->location_name)
            ->addColumn('date_range', fn($r) => $r->rental_start_date?->format('d/m/Y') . ' - ' . $r->rental_end_date?->format('d/m/Y'))
            ->addColumn('total_amount', fn($r) => 'Rp ' . number_format($r->total_amount, 0, ',', '.'))
            ->addColumn('status_badge', fn($r) => view('components.rental-status-badge', ['status' => $r->status])->render())
            ->addColumn('action', function ($r) {
                $html = '<div class="d-flex gap-1">';
                $html .= '<a href="' . route('rental.show', $r->rental_id) . '" class="btn btn-sm btn-outline-primary"><i class="ri-eye-line"></i></a>';

                if ($r->status === 'reserved') {
                    $html .= '<button type="button" class="btn btn-sm btn-success btn-confirm" data-id="' . $r->rental_id . '" title="Konfirmasi Penjemputan"><i class="ri-check-line"></i></button>';
                    $html .= '<button type="button" class="btn btn-sm btn-outline-danger btn-cancel" data-id="' . $r->rental_id . '" title="Batalkan"><i class="ri-close-line"></i></button>';
                } elseif ($r->status === 'ongoing') {
                    $html .= '<a href="' . route('rental.detail.return.form', $r->rental_id) . '" class="btn btn-sm btn-outline-warning" title="Proses Pengembalian"><i class="ri-arrow-go-back-line"></i></a>';

                }

                $html .= '</div>';
                return $html;
            })
            ->rawColumns(['status_badge', 'action'])
            ->toJson();
    }

    public function confirmPickup(Request $request, $id)
    {
        try {
            $rental = $this->model->where('status', 'reserved')->findOrFail($id);
            $rental->update(['status' => 'ongoing']);

            return response()->json([
                'status' => true,
                'msg' => 'Reservasi berhasil dikonfirmasi sebagai sewa aktif.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function cancelReservation(Request $request, $id)
    {
        try {
            $rental = $this->model->whereIn('status', ['reserved', 'ongoing'])->findOrFail($id);
            $rental->update(['status' => 'cancelled']);

            if ($rental->vehicle) {
                $rental->vehicle->update(['status' => 'available']);
            }

            return response()->json([
                'status' => true,
                'msg' => 'Reservasi berhasil dibatalkan.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'msg' => $e->getMessage(),
            ]);
        }
    }

    public function show($rental)
    {
        $rental = $this->model->with([
            'customer', 'vehicle.model.brand', 'driver', 'pickupLocation',
            'returnLocation', 'promo', 'details', 'extensions', 'fines', 'invoices', 'payments',
        ])->findOrFail($rental);

        $view = [
            'title' => 'Detail Transaksi',
            'subtitle' => 'Detail Transaksi - ' . $rental->rental_code,
            'rental' => $rental,
        ];

        return view('rental.show')->with($view);
    }

    public function edit(Request $request, $rental)
    {
        $rental = $this->model->with([
            'customer', 'vehicle.model.brand', 'driver', 'pickupLocation', 'returnLocation', 'promo',
        ])->findOrFail($rental);

        $view = [
            'title' => 'Edit Sewa',
            'subtitle' => 'Edit ' . $rental->rental_code,
            'rental' => $rental,
            'customers' => Customer::orderBy('first_name')->get(),
            'locations' => Location::active()->get(),
            'drivers' => Driver::active()->licenseValid()->get(),
            'promos' => Promo::active()->get(),
        ];

        return view('rental.edit')->with($view);
    }

    public function update(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'rental_start_date' => 'required|date',
                'rental_end_date' => 'required|date|after:rental_start_date',
                'pickup_location_id' => 'nullable|exists:m_location,location_id',
                'return_location_id' => 'nullable|exists:m_location,location_id',
                'is_with_driver' => 'nullable|boolean',
                'driver_id' => 'nullable|exists:m_driver,driver_id',
                'promo_id' => 'nullable|exists:m_promo,promo_id',
                'status' => 'required|in:reserved,ongoing,completed,cancelled',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $startDate = Carbon::parse($validated['rental_start_date']);
            $endDate = Carbon::parse($validated['rental_end_date']);
            $days = $startDate->diffInDays($endDate) ?: 1;

            $baseRate = $rental->base_rate_per_day;
            $totalBase = $baseRate * $days;
            $insuranceFee = $rental->vehicle?->model ? $totalBase * ($rental->vehicle->model->insurance_rate / 100) : $rental->insurance_fee;
            $driverFee = ($validated['is_with_driver'] ?? false) ? ($rental->driver_fee ?: 150000 * $days) : 0;

            $subtotal = $totalBase + $insuranceFee + $driverFee;
            $discountAmount = 0;

            if (!empty($validated['promo_id'])) {
                $promo = Promo::find($validated['promo_id']);
                if ($promo) {
                    if ($promo->discount_type === 'percentage') {
                        $discountAmount = $subtotal * ($promo->discount_value / 100);
                    } else {
                        $discountAmount = $promo->discount_value;
                    }
                }
            } elseif ($rental->promo_id) {
                $promo = Promo::find($rental->promo_id);
                if ($promo) {
                    $discountAmount = $promo->discount_type === 'percentage'
                        ? $subtotal * ($promo->discount_value / 100)
                        : $promo->discount_value;
                }
            }

            $afterDiscount = $subtotal - $discountAmount;
            $taxAmount = $afterDiscount * 0.11;
            $totalAmount = $afterDiscount + $taxAmount;

            $rental->update([
                'rental_start_date' => $startDate,
                'rental_end_date' => $endDate,
                'rental_days' => $days,
                'pickup_location_id' => $validated['pickup_location_id'] ?? null,
                'return_location_id' => $validated['return_location_id'] ?? null,
                'is_with_driver' => $validated['is_with_driver'] ?? false,
                'driver_id' => ($validated['is_with_driver'] ?? false) ? ($validated['driver_id'] ?? null) : null,
                'promo_id' => $validated['promo_id'] ?? null,
                'total_base_price' => $totalBase,
                'insurance_fee' => $insuranceFee,
                'driver_fee' => $driverFee,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Data sewa berhasil diperbarui.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function printContract($rental)
    {
        $rental = $this->model->with([
            'customer', 'vehicle.model.brand', 'driver', 'pickupLocation', 'returnLocation',
        ])->findOrFail($rental);

        $view = [
            'title' => 'Cetak Kontrak',
            'rental' => $rental,
        ];

        return view('rental.print')->with($view);
    }

    public function export(Request $request)
    {
        $type = $request->get('type', 'excel');
    }

    // ============================================================
    // DETAIL SUB-ACTIONS
    // ============================================================

    public function extensionStore(Request $request, $rental)
    {
        try {
            $rental = Rental::whereIn('status', ['reserved', 'ongoing'])->findOrFail($rental);

            $validated = $request->validate([
                'new_end_date' => 'required|date|after:' . $rental->rental_end_date,
                'notes' => 'nullable|string',
            ]);

            $newEnd = Carbon::parse($validated['new_end_date']);
            $oldEnd = Carbon::parse($rental->rental_end_date);
            $days = $oldEnd->diffInDays($newEnd);

            if ($days < 1) {
                return response()->json(['status' => false, 'msg' => 'Perpanjangan minimal 1 hari.']);
            }

            $additionalBase = $rental->base_rate_per_day * $days;
            $additionalTax = $additionalBase * 0.11;

            $extension = RentalExtension::create([
                'rental_id' => $rental->rental_id,
                'old_end_date' => $rental->rental_end_date,
                'new_end_date' => $validated['new_end_date'],
                'extended_days' => $days,
                'additional_base_price' => $additionalBase,
                'additional_tax' => $additionalTax,
                'additional_total' => $additionalBase + $additionalTax,
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json(['status' => true, 'msg' => 'Permintaan perpanjangan dibuat.', 'data' => $extension]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function extensionApprove(Request $request, $rental, $extensionId)
    {
        try {
            DB::beginTransaction();

            $extension = RentalExtension::where('rental_id', $rental)->where('status', 'pending')->findOrFail($extensionId);
            $rental = Rental::findOrFail($rental);

            $extension->update([
                'status' => 'approved',
                'approved_by' => auth()->user()->employee_id ?? null,
                'approved_at' => now(),
            ]);

            $rental->update([
                'rental_end_date' => $extension->new_end_date,
                'total_base_price' => ($rental->total_base_price ?? 0) + $extension->additional_base_price,
                'tax_amount' => ($rental->tax_amount ?? 0) + $extension->additional_tax,
                'total_amount' => ($rental->total_amount ?? 0) + $extension->additional_total,
            ]);

            DB::commit();

            return response()->json(['status' => true, 'msg' => 'Perpanjangan disetujui.']);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function extensionReject(Request $request, $rental, $extensionId)
    {
        try {
            $extension = RentalExtension::where('rental_id', $rental)->where('status', 'pending')->findOrFail($extensionId);
            $extension->update(['status' => 'rejected']);

            return response()->json(['status' => true, 'msg' => 'Perpanjangan ditolak.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function returnForm($rental)
    {
        $rental = Rental::with(['customer', 'vehicle.model.brand'])->where('status', 'ongoing')->findOrFail($rental);

        return view('rental.return')->with([
            'title' => 'Form Pengembalian',
            'subtitle' => 'Pengembalian ' . $rental->rental_code,
            'rental' => $rental,
        ]);
    }

    public function returnStore(Request $request, $rental)
    {
        try {
            $rental = Rental::where('status', 'ongoing')->findOrFail($rental);

            $validated = $request->validate([
                'return_date' => 'required|date',
                'return_mileage' => 'nullable|integer',
                'fuel_level' => 'nullable|string',
                'vehicle_condition' => 'required|in:good,minor_damage,damage',
                'damage_description' => 'nullable|string',
                'extra_charge' => 'nullable|numeric',
                'deposit_refund' => 'nullable|numeric',
            ]);

            DB::beginTransaction();

            $return = ReturnCar::create(array_merge($validated, ['rental_id' => $rental->rental_id]));

            $rental->update([
                'status' => 'completed',
                'actual_return_date' => $validated['return_date'],
            ]);

            if ($rental->vehicle) {
                $rental->vehicle->update(['status' => 'available']);
            }

            DB::commit();

            return redirect()->route('rental.show', $rental->rental_id)
                ->withSuccess('Pengembalian berhasil diproses.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function fineStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'fine_type' => 'required|string',
                'description' => 'required|string',
                'amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $fine = Fine::create([
                'rental_id' => $rental->rental_id,
                'fine_type' => $validated['fine_type'],
                'description' => $validated['description'],
                'amount' => $validated['amount'],
                'status' => 'unpaid',
                'issued_date' => now(),
                'issued_by' => auth()->user()->employee_id ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json(['status' => true, 'msg' => 'Denda berhasil ditambahkan.', 'data' => $fine]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function finePay(Request $request, $rental, $fineId)
    {
        try {
            $fine = Fine::where('rental_id', $rental)->where('status', 'unpaid')->findOrFail($fineId);
            $fine->update(['status' => 'paid', 'paid_date' => now()]);

            return response()->json(['status' => true, 'msg' => 'Denda berhasil dibayar.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function fineWaive(Request $request, $rental, $fineId)
    {
        try {
            $fine = Fine::where('rental_id', $rental)->where('status', 'unpaid')->findOrFail($fineId);
            $fine->update(['status' => 'waived']);

            return response()->json(['status' => true, 'msg' => 'Denda dibebaskan.']);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function invoiceGenerate($rental)
    {
        $rental = Rental::with(['customer', 'vehicle.model.brand', 'details'])->findOrFail($rental);

        $existing = Invoice::where('rental_id', $rental->rental_id)->first();
        if ($existing) {
            return redirect()->route('finance.invoice.show', $existing->invoice_id);
        }

        return view('rental.invoice')->with([
            'title' => 'Buat Invoice',
            'subtitle' => 'Invoice ' . $rental->rental_code,
            'rental' => $rental,
        ]);
    }

    public function invoiceStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            if (Invoice::where('rental_id', $rental->rental_id)->exists()) {
                return redirect()->route('rental.show', $rental->rental_id)
                    ->withErrors('Invoice untuk sewa ini sudah ada.');
            }

            $validated = $request->validate([
                'due_date' => 'required|date',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $invoiceNumber = $this->gen_number(Invoice::class, 'invoice_number', 'INV-$/#####', now(), 'created_at', true);

            Invoice::create([
                'rental_id' => $rental->rental_id,
                'invoice_number' => $invoiceNumber,
                'issue_date' => now(),
                'due_date' => $validated['due_date'],
                'sub_total' => $rental->total_amount - ($rental->tax_amount ?? 0),
                'tax' => $rental->tax_amount ?? 0,
                'discount' => $rental->discount_amount ?? 0,
                'total_amount' => $rental->total_amount,
                'paid_amount' => $rental->payments()->where('status', 'completed')->sum('amount'),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            return redirect()->route('finance.invoice.index')->withSuccess('Invoice berhasil dibuat.');
        } catch (Exception $e) {
            DB::rollback();
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    public function invoicePrint($rental)
    {
        $invoice = Invoice::with(['rental.customer', 'rental.vehicle.model.brand', 'rental.details', 'payments'])
            ->where('rental_id', $rental)->firstOrFail();

        return view('rental.invoice-print')->with(['item' => $invoice]);
    }

    public function paymentStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|in:cash,transfer,credit_card,debit_card,e_wallet',
                'reference_number' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            DB::beginTransaction();

            $invoice = Invoice::where('rental_id', $rental->rental_id)->first();

            $payment = Payment::create([
                'invoice_id' => $invoice?->invoice_id,
                'rental_id' => $rental->rental_id,
                'payment_date' => now(),
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'status' => 'completed',
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($invoice) {
                $paid = $invoice->paid_amount + $validated['amount'];
                $invoice->update([
                    'paid_amount' => $paid,
                    'status' => $paid >= $invoice->total_amount ? 'paid' : 'partially_paid',
                ]);
            }

            $rental->update(['payment_status' => 'paid']);

            DB::commit();

            return response()->json(['status' => true, 'msg' => 'Pembayaran berhasil dicatat.', 'data' => $payment]);
        } catch (Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function refundStore(Request $request, $rental)
    {
        try {
            $rental = Rental::findOrFail($rental);

            $validated = $request->validate([
                'payment_id' => 'required|exists:tr_payment,payment_id',
                'amount' => 'required|numeric|min:0.01',
                'refund_type' => 'required|in:deposit,cancelled,overpayment',
                'notes' => 'nullable|string',
            ]);

            $refund = Refund::create([
                'payment_id' => $validated['payment_id'],
                'rental_id' => $rental->rental_id,
                'refund_date' => now(),
                'amount' => $validated['amount'],
                'refund_type' => $validated['refund_type'],
                'status' => 'processed',
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json(['status' => true, 'msg' => 'Refund berhasil dicatat.', 'data' => $refund]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage()]);
        }
    }
}