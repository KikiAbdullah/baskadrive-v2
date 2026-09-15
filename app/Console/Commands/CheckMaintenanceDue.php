<?php

namespace App\Console\Commands;

use App\Models\Maintenance;
use App\Models\MaintenanceType;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckMaintenanceDue extends Command
{
    protected $signature = 'fleet:check-maintenance {--notify : Kirim notifikasi jika ada yang due}';
    protected $description = 'Periksa odometer kendaraan terhadap jadwal servis terakhir dan tandai yang due soon';

    public function handle(): int
    {
        $this->info('Memeriksa jadwal maintenance...');

        $vehicles = Vehicle::with(['maintenanceRecords' => function ($q) {
            $q->where('status', 'completed')->latest('actual_date')->limit(1);
        }, 'model'])->get();

        $dueCount = 0;
        $types = MaintenanceType::active()->get()->keyBy('type_id');

        foreach ($vehicles as $vehicle) {
            $last = $vehicle->maintenanceRecords->first();
            $lastMileage = $last?->current_mileage ?? $vehicle->mileage;
            $lastDate = $last?->actual_date ? Carbon::parse($last->actual_date) : ($vehicle->purchase_date ? Carbon::parse($vehicle->purchase_date) : now()->subYear());

            foreach ($types as $type) {
                $dueKm = $lastMileage + $type->interval_km;
                $dueDate = $lastDate->copy()->addMonths($type->interval_months);
                $kmRemaining = $dueKm - $vehicle->mileage;
                $daysRemaining = now()->diffInDays($dueDate, false);

                $isDueSoon = $kmRemaining <= 500 || $daysRemaining <= 7;
                $isOverdue = $kmRemaining <= 0 || $daysRemaining < 0;

                if ($isOverdue || $isDueSoon) {
                    $status = $isOverdue ? 'OVERDUE' : 'DUE SOON';
                    $this->warn("Vehicle {$vehicle->license_plate} ({$vehicle->model->model_name}) - {$type->type_name}: {$status} (km: {$kmRemaining}, hari: {$daysRemaining})");
                    $dueCount++;

                    // Optional: auto-create scheduled maintenance if not exists and overdue
                    $exists = Maintenance::where('vehicle_id', $vehicle->vehicle_id)
                        ->where('maintenance_type_id', $type->type_id)
                        ->whereIn('status', ['scheduled', 'overdue'])
                        ->exists();
                    if ($isOverdue && ! $exists) {
                        $defaultWorkshop = \App\Models\Workshop::active()->first();
                        Maintenance::create([
                            'vehicle_id' => $vehicle->vehicle_id,
                            'maintenance_type_id' => $type->type_id,
                            'workshop_id' => $defaultWorkshop?->workshop_id ?? 1,
                            'scheduled_date' => now()->toDateString(),
                            'status' => 'overdue',
                            'description' => 'Auto-generated: melebihi interval '.$type->type_name,
                            'current_mileage' => $vehicle->mileage,
                        ]);
                        $this->info("  -> Auto-created overdue maintenance for {$vehicle->license_plate}");
                    }
                }
            }
        }

        $this->info("Selesai. Ditemukan {$dueCount} jadwal due soon/overdue.");
        return self::SUCCESS;
    }
}
