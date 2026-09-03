<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $table = 'm_brand';

    protected $primaryKey = 'brand_id';

    public $timestamps = false;

    protected $fillable = [
        'brand_name',
        'logo_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function vehicleModels()
    {
        return $this->hasMany(VehicleModel::class, 'brand_id', 'brand_id');
    }

    public function scopeActive($query)
    {
        return $query->whereHas('vehicleModels', fn ($q) => $q->where('is_active', true));
    }
}