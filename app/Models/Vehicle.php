<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = 'm_vehicle';

    protected $primaryKey = 'vehicle_id';

    protected $fillable = [
        'license_plate', 'vin', 'model_id', 'color', 'year', 'mileage',
        'status', 'purchase_date', 'purchase_price', 'current_value',
        'engine_number', 'photo_url', 'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'mileage' => 'integer',
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function model()
    {
        return $this->belongsTo(VehicleModel::class, 'model_id', 'model_id');
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class, 'vehicle_id', 'vehicle_id');
    }

    public function activeRental()
    {
        return $this->hasOne(Rental::class, 'vehicle_id', 'vehicle_id')
            ->whereIn('status', ['ongoing', 'reserved']);
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(Maintenance::class, 'vehicle_id', 'vehicle_id');
    }

    public function damageReports()
    {
        return $this->hasMany(DamageReport::class, 'vehicle_id', 'vehicle_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['available', 'rented', 'reserved', 'maintenance']);
    }
}