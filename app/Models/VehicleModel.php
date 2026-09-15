<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleModel extends Model
{
    protected $table = 'm_vehicle_model';

    protected $primaryKey = 'model_id';

    public $timestamps = false;

    protected $fillable = [
        'brand_id', 'model_name', 'category', 'fuel_type', 'transmission',
        'seat_capacity', 'base_price_per_day', 'base_price_per_km',
        'insurance_rate', 'deposit_amount', 'photo', 'is_active',
    ];

    protected $casts = [
        'seat_capacity' => 'integer',
        'base_price_per_day' => 'decimal:2',
        'base_price_per_km' => 'decimal:2',
        'insurance_rate' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'brand_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'model_id', 'model_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}