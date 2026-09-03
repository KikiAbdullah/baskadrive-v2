<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\RentalExtension;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RentalErpRentalSeeder extends Seeder
{
    private function calcTotal(float $ratePerDay, int $days, float $insuranceFee, float $driverFeePerDay, bool $withDriver, float $discount): float
    {
        $base = $ratePerDay * $days;
        $driverTotal = $withDriver ? $driverFeePerDay * $days : 0;
        $subtotal = $base + $insuranceFee + $driverTotal - $discount;
        $tax = $subtotal * 0.11;
        return $subtotal + $tax;
    }

    public function run(): void
    {
        $now = Carbon::now();

        $customers = \App\Models\Customer::pluck('customer_id', 'driver_license_number')->toArray();
        $vehicles = Vehicle::pluck('vehicle_id', 'license_plate')->toArray();
        $employees = \App\Models\Employee::pluck('employee_id', 'username')->toArray();
        $locations = Location::pluck('location_id', 'location_name')->toArray();
        $drivers = \App\Models\Driver::pluck('driver_id', 'license_number')->toArray();
        $promos = \App\Models\Promo::pluck('promo_id', 'promo_code')->toArray();

        $jakarta = $locations['Cabang Jakarta'];
        $bandung = $locations['Cabang Bandung'];
        $manager = $employees['manager'];
        $kasir = $employees['kasir'];

        $rentals = [
            // ===== SEWA ONGOING (sedang berjalan) =====
            // Sewa 1: Innova 3 hari, dengan driver, tanpa promo
            ['code' => 'RNT-2026-0001', 'customer' => 'SIM-001', 'vehicle' => 'B 5678 DEF', 'emp' => 'kasir', 'pickup' => $jakarta, 'return' => $jakarta,
             'start' => $now->copy()->subDays(2), 'end' => $now->copy()->addDays(1), 'wd' => true, 'driver' => 'DRV-1001',
             'rate' => 550000, 'ins' => 0, 'driverFee' => 150000, 'disc' => 0, 'status' => 'ongoing', 'pay' => 'unpaid'],

            // Sewa 2: Avanza 5 hari, tanpa driver, promo WELCOME10
            ['code' => 'RNT-2026-0002', 'customer' => 'SIM-002', 'vehicle' => 'B 1234 ABC', 'emp' => 'kasir', 'pickup' => $jakarta, 'return' => $jakarta,
             'start' => $now->copy()->subDays(1), 'end' => $now->copy()->addDays(4), 'wd' => false, 'driver' => null,
             'rate' => 350000, 'ins' => 50000, 'driverFee' => 0, 'disc' => 175000, 'promo' => 'WELCOME10', 'status' => 'ongoing', 'pay' => 'partial'],

            // ===== SEWA RESERVED (akan datang) =====
            ['code' => 'RNT-2026-0003', 'customer' => 'SIM-003', 'vehicle' => 'B 2468 PQR', 'emp' => 'kasir', 'pickup' => $jakarta, 'return' => $jakarta,
             'start' => $now->copy()->addDays(3), 'end' => $now->copy()->addDays(7), 'wd' => false, 'driver' => null,
             'rate' => 650000, 'ins' => 100000, 'driverFee' => 0, 'disc' => 0, 'status' => 'reserved', 'pay' => 'unpaid'],

            // ===== SEWA COMPLETED (selesai) =====
            // Sewa 4: Fortuner 7 hari, dengan driver, selesai 5 hari lalu
            ['code' => 'RNT-2026-0004', 'customer' => 'SIM-004', 'vehicle' => 'B 3456 JKL', 'emp' => 'manager', 'pickup' => $jakarta, 'return' => $bandung,
             'start' => $now->copy()->subDays(12), 'end' => $now->copy()->subDays(5), 'wd' => true, 'driver' => 'DRV-1002',
             'rate' => 1200000, 'ins' => 200000, 'driverFee' => 150000, 'disc' => 0, 'status' => 'completed', 'pay' => 'paid'],

            // Sewa 5: Ertiga 3 hari, korporat, selesai 10 hari lalu
            ['code' => 'RNT-2026-0005', 'customer' => 'SIM-005', 'vehicle' => 'B 7890 MNO', 'emp' => 'kasir', 'pickup' => $jakarta, 'return' => $jakarta,
             'start' => $now->copy()->subDays(15), 'end' => $now->copy()->subDays(12), 'wd' => false, 'driver' => null,
             'rate' => 380000, 'ins' => 30000, 'driverFee' => 0, 'disc' => 171000, 'promo' => 'KORPORAT', 'status' => 'completed', 'pay' => 'paid'],

            // ===== SEWA CANCELLED =====
            ['code' => 'RNT-2026-0006', 'customer' => 'SIM-006', 'vehicle' => 'B 1357 STU', 'emp' => 'kasir', 'pickup' => $bandung, 'return' => $bandung,
             'start' => $now->copy()->subDays(3), 'end' => $now->copy()->addDays(2), 'wd' => false, 'driver' => null,
             'rate' => 330000, 'ins' => 20000, 'driverFee' => 0, 'disc' => 0, 'status' => 'cancelled', 'pay' => 'refunded'],

            // ===== SEWA OVERDUE (telat) =====
            ['code' => 'RNT-2026-0007', 'customer' => 'SIM-007', 'vehicle' => 'B 9012 GHI', 'emp' => 'kasir', 'pickup' => $jakarta, 'return' => $jakarta,
             'start' => $now->copy()->subDays(10), 'end' => $now->copy()->subDays(3), 'wd' => false, 'driver' => null,
             'rate' => 250000, 'ins' => 0, 'driverFee' => 0, 'disc' => 0, 'status' => 'overdue', 'pay' => 'unpaid'],

            // Sewa 8: Xenia 4 hari, with driver, selesai 20 hari lalu
            ['code' => 'RNT-2026-0008', 'customer' => 'SIM-008', 'vehicle' => 'B 1357 STU', 'emp' => 'kasir', 'pickup' => $jakarta, 'return' => $jakarta,
             'start' => $now->copy()->subDays(24), 'end' => $now->copy()->subDays(20), 'wd' => true, 'driver' => 'DRV-1003',
             'rate' => 330000, 'ins' => 25000, 'driverFee' => 130000, 'disc' => 0, 'status' => 'completed', 'pay' => 'paid'],
        ];

        $createdRentals = [];
        foreach ($rentals as $r) {
            $days = (int) $r['start']->diffInDays($r['end']);
            $baseTotal = $r['rate'] * $days;
            $driverTotal = $r['wd'] ? $r['driverFee'] * $days : 0;
            $subtotal = $baseTotal + $r['ins'] + $driverTotal - $r['disc'];
            $tax = $subtotal * 0.11;
            $total = $subtotal + $tax;

            $rental = Rental::create([
                'rental_code' => $r['code'],
                'customer_id' => $customers[$r['customer']],
                'vehicle_id' => $vehicles[$r['vehicle']],
                'employee_id' => $r['emp'] ? $employees[$r['emp']] : null,
                'pickup_location_id' => $r['pickup'],
                'return_location_id' => $r['return'],
                'promo_id' => ($r['promo'] ?? null) ? ($promos[$r['promo']] ?? null) : null,
                'rental_start_date' => $r['start'],
                'rental_end_date' => $r['end'],
                'rental_days' => $days,
                'is_with_driver' => $r['wd'],
                'driver_id' => $r['driver'] ? ($drivers[$r['driver']] ?? null) : null,
                'base_rate_per_day' => $r['rate'],
                'total_base_price' => $baseTotal,
                'insurance_fee' => $r['ins'],
                'driver_fee' => $driverTotal,
                'discount_amount' => $r['disc'],
                'tax_amount' => $tax,
                'deposit_amount' => round($baseTotal * 0.5, 2),
                'total_amount' => $total,
                'status' => $r['status'],
                'payment_status' => $r['pay'],
                'notes' => null,
            ]);

            $createdRentals[$r['code']] = $rental;
        }

        // Sync vehicle status for consistency
        foreach ($createdRentals as $rental) {
            $vehicle = $rental->vehicle;
            if ($rental->status === 'ongoing' && $vehicle->status !== 'rented') {
                $vehicle->update(['status' => 'rented']);
            } elseif (in_array($rental->status, ['completed', 'cancelled'])) {
                // Only set to available if no other ongoing rental uses this vehicle
                $hasOngoing = Rental::where('vehicle_id', $vehicle->vehicle_id)
                    ->whereIn('status', ['ongoing', 'reserved'])
                    ->where('rental_id', '!=', $rental->rental_id)
                    ->exists();
                if (!$hasOngoing) {
                    $vehicle->update(['status' => 'available']);
                }
            }
        }

        // ===========================================
        // RENTAL DETAILS â€” biaya tambahan
        // ===========================================
        $details = [
            ['rental' => 'RNT-2026-0002', 'type' => 'gps', 'name' => 'GPS Navigation', 'qty' => 1, 'price' => 50000],
            ['rental' => 'RNT-2026-0004', 'type' => 'child_seat', 'name' => 'Baby Seat', 'qty' => 2, 'price' => 75000],
            ['rental' => 'RNT-2026-0007', 'type' => 'toll', 'name' => 'Tol Jagorawi', 'qty' => 2, 'price' => 45000],
        ];

        foreach ($details as $d) {
            RentalDetail::create([
                'rental_id' => $createdRentals[$d['rental']]->rental_id,
                'item_type' => $d['type'],
                'item_name' => $d['name'],
                'quantity' => $d['qty'],
                'unit_price' => $d['price'],
                'total_price' => $d['qty'] * $d['price'],
            ]);
        }

        // ===========================================
        // RENTAL EXTENSIONS â€” perpanjangan
        // ===========================================
        $extensions = [
            ['rental' => 'RNT-2026-0001', 'old' => $now->copy()->addDays(1), 'new' => $now->copy()->addDays(3), 'days' => 2, 'extra' => 1100000, 'status' => 'approved', 'approved_by' => $manager],
        ];

        foreach ($extensions as $e) {
            RentalExtension::create([
                'rental_id' => $createdRentals[$e['rental']]->rental_id,
                'old_end_date' => $e['old'],
                'new_end_date' => $e['new'],
                'extended_days' => $e['days'],
                'additional_base_price' => $e['extra'],
                'additional_tax' => $e['extra'] * 0.11,
                'additional_total' => $e['extra'] * 1.11,
                'status' => $e['status'],
                'approved_by' => $e['approved_by'],
                'approved_at' => $now,
                'notes' => 'Approved by manager',
            ]);
        }

        $this->command->info('RentalErpRentalSeeder selesai: '.count($createdRentals).' rental, '.count($details).' detail, '.count($extensions).' extension.');
    }
}