<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Vehicle;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $today = Carbon::today();

        $stats = [
            'revenueToday' => Rental::whereIn('status', ['ongoing', 'completed'])
                ->whereDate('created_at', $today)->sum('total_amount'),
            'ongoingCount' => Rental::where('status', 'ongoing')->count(),
            'reservedCount' => Rental::where('status', 'reserved')->count(),
            'availableVehicles' => Vehicle::where('status', 'available')->count(),
            'totalVehicles' => Vehicle::count(),
        ];

        $upcomingReturns = Rental::with(['customer', 'vehicle'])
            ->where('status', 'ongoing')
            ->whereBetween('rental_end_date', [$today, $today->copy()->addDays(7)])
            ->orderBy('rental_end_date')
            ->limit(10)
            ->get();

        $upcomingPickups = Rental::with(['customer', 'vehicle'])
            ->where('status', 'reserved')
            ->whereBetween('rental_start_date', [$today, $today->copy()->addDays(7)])
            ->orderBy('rental_start_date')
            ->limit(10)
            ->get();

        return view('dashboard.index')->with([
            'title' => 'Dashboard',
            'subtitle' => 'Ringkasan Operasional',
            'stats' => $stats,
            'upcomingReturns' => $upcomingReturns,
            'upcomingPickups' => $upcomingPickups,
        ]);
    }

    public function calendar()
    {
        try {
            $start = request('start');
            $end = request('end');

            $query = Rental::with(['customer', 'vehicle']);

            if ($start) {
                $query->where('rental_end_date', '>=', Carbon::parse($start));
            }
            if ($end) {
                $query->where('rental_start_date', '<=', Carbon::parse($end));
            }

            $rentals = $query->get()->map(function ($r) {
                $color = match ($r->status) {
                    'reserved' => '#03a9f4',
                    'ongoing' => '#28c76f',
                    'completed' => '#82868b',
                    'cancelled' => '#ea5455',
                    default => '#82868b',
                };

                return [
                    'id' => $r->rental_id,
                    'title' => ($r->vehicle?->license_plate ?? '?') . ' - ' . ($r->customer?->full_name ?? '?'),
                    'start' => $r->rental_start_date?->toDateString(),
                    'end' => $r->rental_end_date?->toDateString(),
                    'color' => $color,
                    'url' => route('rental.show', $r->rental_id),
                ];
            });

            return response()->json($rentals);
        } catch (Exception $e) {
            return response()->json([]);
        }
    }

    public function fleetMap()
    {
        try {
            $vehicles = Vehicle::with(['model.brand'])
                ->whereIn('status', ['available', 'rented', 'reserved', 'maintenance'])
                ->get()
                ->map(function ($v) {
                    return [
                        'id' => $v->vehicle_id,
                        'plate' => $v->license_plate,
                        'name' => ($v->model?->brand?->brand_name ?? '') . ' ' . ($v->model?->model_name ?? ''),
                        'status' => $v->status,
                        'lat' => $v->latitude ?? config('baska.map_default_lat', -6.2),
                        'lng' => $v->longitude ?? config('baska.map_default_lng', 106.816666),
                        'rental' => $v->rentals()->whereIn('status', ['ongoing'])->first()?->rental_code,
                    ];
                });

            return response()->json(['status' => true, 'data' => $vehicles]);
        } catch (Exception $e) {
            return response()->json(['status' => false, 'msg' => $e->getMessage(), 'data' => []]);
        }
    }
}
