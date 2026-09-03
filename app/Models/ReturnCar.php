<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnCar extends Model
{
    protected $table = 'tr_return';

    protected $primaryKey = 'return_id';

    public $timestamps = false;

    protected $fillable = [
        'rental_id', 'return_date', 'return_mileage', 'fuel_level',
        'vehicle_condition', 'damage_description', 'repair_cost_estimate',
        'extra_charge', 'deposit_refund',
    ];

    protected $casts = [
        'return_date' => 'datetime',
        'return_mileage' => 'integer',
        'repair_cost_estimate' => 'decimal:2',
        'extra_charge' => 'decimal:2',
        'deposit_refund' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function damageReports()
    {
        return $this->hasMany(DamageReport::class, 'return_id', 'return_id');
    }

    public function fines()
    {
        return $this->hasMany(Fine::class, 'return_id', 'return_id');
    }
}