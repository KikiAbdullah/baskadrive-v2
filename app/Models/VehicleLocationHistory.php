<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleLocationHistory extends Model
{
    protected $table = 'vehicle_location_histories';

    protected $fillable = [
        'vehicle_id', 'from_location_id', 'to_location_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function fromLocation()
    {
        return $this->belongsTo(Location::class, 'from_location_id', 'location_id');
    }

    public function toLocation()
    {
        return $this->belongsTo(Location::class, 'to_location_id', 'location_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
