<?php

namespace Database\Seeders;

use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\Fine;
use App\Models\InsuranceClaim;
use App\Models\Maintenance;
use App\Models\ReturnCar;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RentalErpOperationalSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $rentals = \App\Models\Rental::pluck('rental_id', 'rental_code')->toArray();
        $vehicles = Vehicle::pluck('vehicle_id', 'license_plate')->toArray();
        $employees = \App\Models\Employee::pluck('employee_id', 'username')->toArray();
        $workshops = \App\Models\Workshop::pluck('workshop_id', 'name')->toArray();
        $mtypes = \App\Models\MaintenanceType::pluck('type_id', 'type_name')->toArray();
        $mechanic = $employees['mekanik'];
        $cashier = $employees['kasir'];

        // ===========================================
        // 1. RETURN — pengembalian kendaraan (1:1)
        // ===========================================
        $returns = [
            // Return utk sewa 4 (Fortuner, selesai) — kondisi baik, deposit dikembalikan
            ['rental' => 'RNT-2026-0004', 'return_date' => $now->copy()->subDays(5), 'mileage' => 75000, 'fuel' => 'full', 'cond' => 'good', 'desc' => null, 'repair' => 0, 'extra' => 0, 'refund' => 3000000],
            // Return utk sewa 5 (Ertiga, selesai) — ada kerusakan kecil
            ['rental' => 'RNT-2026-0005', 'return_date' => $now->copy()->subDays(12), 'mileage' => 42000, 'fuel' => 'three_quarter', 'cond' => 'fair', 'desc' => 'Goresan kecil di bumper belakang', 'repair' => 500000, 'extra' => 200000, 'refund' => 1000000],
            // Return utk sewa 8 (Xenia, selesai)
            ['rental' => 'RNT-2026-0008', 'return_date' => $now->copy()->subDays(20), 'mileage' => 46000, 'fuel' => 'full', 'cond' => 'excellent', 'desc' => null, 'repair' => 0, 'extra' => 0, 'refund' => 1500000],
        ];

        $returnMap = [];
        foreach ($returns as $r) {
            $ret = ReturnCar::create([
                'rental_id' => $rentals[$r['rental']],
                'return_date' => $r['return_date'],
                'return_mileage' => $r['mileage'],
                'fuel_level' => $r['fuel'],
                'vehicle_condition' => $r['cond'],
                'damage_description' => $r['desc'],
                'repair_cost_estimate' => $r['repair'],
                'extra_charge' => $r['extra'],
                'deposit_refund' => $r['refund'],
            ]);
            $returnMap[$r['rental']] = $ret->return_id;
        }

        // ===========================================
        // 2. DAMAGE REPORT — laporan kerusakan
        // ===========================================
        $damages = [
            ['rental' => 'RNT-2026-0005', 'vehicle' => 'B 7890 MNO', 'return' => 'RNT-2026-0005',
             'type' => 'exterior', 'severity' => 'minor', 'location' => 'Bumper Belakang',
             'desc' => 'Goresan kecil pada bumper belakang akibat parkir',
             'repair' => 500000, 'actual' => 450000, 'status' => 'repaired', 'inspected_by' => $mechanic],
        ];

        $damageMap = [];
        foreach ($damages as $d) {
            $damage = DamageReport::create([
                'rental_id' => $rentals[$d['rental']],
                'vehicle_id' => $vehicles[$d['vehicle']],
                'return_id' => $returnMap[$d['return']] ?? null,
                'reported_date' => $now->copy()->subDays(10),
                'damage_type' => $d['type'],
                'severity' => $d['severity'],
                'location' => $d['location'],
                'description' => $d['desc'],
                'repair_cost_estimate' => $d['repair'],
                'actual_repair_cost' => $d['actual'],
                'status' => $d['status'],
                'inspected_by' => $d['inspected_by'],
                'inspected_at' => $now->copy()->subDays(9),
                'notes' => null,
            ]);
            $damageMap[$d['rental']] = $damage->damage_id;
        }

        // ===========================================
        // 3. DAMAGE PHOTO — foto dokumentasi
        // ===========================================
        if (!empty($damageMap)) {
            foreach ($damageMap as $damageId) {
                DamagePhoto::create(['damage_id' => $damageId, 'photo_url' => '/storage/damages/bumper-scratch.jpg', 'caption' => 'Goresan bumper belakang']);
            }
        }

        // ===========================================
        // 4. INSURANCE CLAIM — klaim asuransi (1:1 damage)
        // ===========================================
        if (!empty($damageMap)) {
            InsuranceClaim::create([
                'rental_id' => $rentals['RNT-2026-0005'],
                'damage_id' => reset($damageMap),
                'claim_number' => 'CLM-2026-0001',
                'insurance_provider' => 'Asuransi XYZ',
                'policy_number' => 'POL-2025-8899',
                'claim_date' => $now->copy()->subDays(8),
                'claim_amount' => 500000,
                'approved_amount' => 450000,
                'status' => 'paid',
                'approved_date' => $now->copy()->subDays(3),
                'notes' => null,
            ]);
        }

        // ===========================================
        // 5. MAINTENANCE — perawatan armada
        // ===========================================
        $maintenances = [
            ['vehicle' => 'B 7890 MNO', 'workshop' => 'Bengkel Auto Prima', 'type' => 'Ganti Oli',
             'sched' => $now->copy()->subDays(20), 'actual' => $now->copy()->subDays(19), 'mileage' => 40000, 'cost' => 550000, 'status' => 'completed', 'desc' => 'Ganti oli rutin 40.000 km'],
            ['vehicle' => 'B 1357 STU', 'workshop' => 'Bengkel Sentral Motor', 'type' => 'Ganti Ban',
             'sched' => $now->copy()->subDays(15), 'actual' => $now->copy()->subDays(14), 'mileage' => 45000, 'cost' => 2400000, 'status' => 'completed', 'desc' => 'Ganti 4 ban baru'],
            ['vehicle' => 'B 5678 DEF', 'workshop' => 'Bengkel Auto Prima', 'type' => 'Tune Up',
             'sched' => $now->copy()->addDays(5), 'actual' => null, 'mileage' => 20000, 'cost' => 850000, 'status' => 'scheduled', 'desc' => 'Tune up berkala'],
            ['vehicle' => 'B 1234 ABC', 'workshop' => 'Bengkel Anugerah Jaya', 'type' => 'Ganti Oli',
             'sched' => $now->copy()->addDays(12), 'actual' => null, 'mileage' => 30000, 'cost' => 600000, 'status' => 'scheduled', 'desc' => 'Ganti oli rutin'],
        ];

        foreach ($maintenances as $m) {
            Maintenance::create([
                'vehicle_id' => $vehicles[$m['vehicle']],
                'workshop_id' => $workshops[$m['workshop']],
                'maintenance_type_id' => $mtypes[$m['type']],
                'scheduled_date' => $m['sched'],
                'actual_date' => $m['actual'],
                'current_mileage' => $m['mileage'],
                'cost' => $m['cost'],
                'description' => $m['desc'],
                'status' => $m['status'],
                'next_maintenance_km' => $m['mileage'] + 5000,
                'notes' => null,
            ]);
        }

        // ===========================================
        // 6. FINE — denda
        // ===========================================
        $fines = [
            // Sewa 5: telat 1 hari + ada kerusakan minor → denda telat & denda kerusakan
            ['rental' => 'RNT-2026-0005', 'return' => 'RNT-2026-0005', 'type' => 'late_return', 'desc' => 'Telat pengembalian 1 hari', 'amount' => 350000, 'status' => 'paid', 'issued' => $now->copy()->subDays(12), 'paid' => $now->copy()->subDays(10), 'issued_by' => $cashier],
            ['rental' => 'RNT-2026-0005', 'return' => 'RNT-2026-0005', 'type' => 'damage', 'desc' => 'Kerusakan bumper (beban pelanggan)', 'amount' => 50000, 'status' => 'paid', 'issued' => $now->copy()->subDays(12), 'paid' => $now->copy()->subDays(10), 'issued_by' => $cashier],
            // Sewa 7 (overdue): belum dikembalikan → denda telat belum lunas
            ['rental' => 'RNT-2026-0007', 'return' => null, 'type' => 'late_return', 'desc' => 'Kendaraan belum dikembalikan (overdue)', 'amount' => 500000, 'status' => 'unpaid', 'issued' => $now->copy()->subDays(3), 'paid' => null, 'issued_by' => $cashier],
            // Sewa 2 (ongoing): bensin kurang saat inspeksi (contoh)
            ['rental' => 'RNT-2026-0008', 'return' => 'RNT-2026-0008', 'type' => 'cleaning', 'desc' => 'Biaya cleaning interior', 'amount' => 75000, 'status' => 'paid', 'issued' => $now->copy()->subDays(20), 'paid' => $now->copy()->subDays(19), 'issued_by' => $cashier],
        ];

        foreach ($fines as $f) {
            Fine::create([
                'rental_id' => $rentals[$f['rental']],
                'return_id' => $f['return'] ? ($returnMap[$f['return']] ?? null) : null,
                'fine_type' => $f['type'],
                'description' => $f['desc'],
                'amount' => $f['amount'],
                'status' => $f['status'],
                'issued_date' => $f['issued'],
                'paid_date' => $f['paid'],
                'issued_by' => $f['issued_by'],
                'notes' => null,
            ]);
        }

        $this->command->info('RentalErpOperationalSeeder selesai: '.count($returns).' return, '.count($damages).' damage report, '.count($maintenances).' maintenance, '.count($fines).' fine.');
    }
}