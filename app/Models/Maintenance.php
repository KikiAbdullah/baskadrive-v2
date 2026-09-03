<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Maintenance extends Model
{
    protected $table = 'tr_maintenance';

    protected $primaryKey = 'maintenance_id';

    protected $fillable = [
        'vehicle_id', 'workshop_id', 'maintenance_type_id', 'scheduled_date',
        'actual_date', 'current_mileage', 'cost', 'description', 'status',
        'next_maintenance_km', 'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'actual_date' => 'datetime',
        'current_mileage' => 'integer',
        'next_maintenance_km' => 'integer',
        'cost' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function workshop()
    {
        return $this->belongsTo(Workshop::class, 'workshop_id', 'workshop_id');
    }

    public function maintenanceType()
    {
        return $this->belongsTo(MaintenanceType::class, 'maintenance_type_id', 'type_id');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeDueSoon($query)
    {
        return $query->whereIn('status', ['scheduled', 'overdue'])
            ->where('scheduled_date', '<=', now());
    }
}