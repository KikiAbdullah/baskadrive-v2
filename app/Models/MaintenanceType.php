<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaintenanceType extends Model
{
    protected $table = 'm_maintenance_type';

    protected $primaryKey = 'type_id';

    public $timestamps = false;

    protected $fillable = [
        'type_name', 'interval_km', 'interval_months', 'description', 'is_active',
    ];

    protected $casts = [
        'interval_km' => 'integer',
        'interval_months' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function maintenanceRecords()
    {
        return $this->hasMany(Maintenance::class, 'maintenance_type_id', 'type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}