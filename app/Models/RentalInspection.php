<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalInspection extends Model
{
    protected $table = 'rental_inspections';

    protected $fillable = [
        'rental_id', 'vehicle_id', 'inspection_type', 'odometer', 'fuel_level',
        'body_damage_points', 'checklist', 'exterior_notes', 'interior_notes', 'notes', 'created_by',
    ];

    protected $casts = [
        'body_damage_points' => 'array',
        'checklist' => 'array',
        'odometer' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
