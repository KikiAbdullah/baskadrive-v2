<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $table = 'm_driver';

    protected $primaryKey = 'driver_id';

    public $timestamps = false;

    protected $fillable = [
        'first_name', 'last_name', 'license_number', 'license_expiry',
        'phone', 'is_active', 'notes', 'commission_percent',
    ];

    protected $casts = [
        'license_expiry' => 'date',
        'is_active' => 'boolean',
        'commission_percent' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class, 'driver_id', 'driver_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLicenseValid($query)
    {
        return $query->where(function ($q) {
            $q->where('license_expiry', '>=', now()->toDateString())
                ->orWhereNull('license_expiry');
        });
    }
}
