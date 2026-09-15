<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'm_location';

    protected $primaryKey = 'location_id';

    public $timestamps = false;

    protected $fillable = [
        'location_name', 'address', 'city', 'province', 'contact_phone',
        'opening_hours', 'latitude', 'longitude', 'is_active',
    ];

    protected $casts = [
        'opening_hours' => 'string',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function pickupRentals()
    {
        return $this->hasMany(Rental::class, 'pickup_location_id', 'location_id');
    }

    public function returnRentals()
    {
        return $this->hasMany(Rental::class, 'return_location_id', 'location_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}