<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $table = 'tr_fine';

    protected $primaryKey = 'fine_id';

    public $timestamps = false;

    protected $fillable = [
        'rental_id', 'return_id', 'fine_type', 'description', 'amount',
        'status', 'issued_date', 'paid_date', 'issued_by', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_date' => 'datetime',
        'paid_date' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function returnRecord()
    {
        return $this->belongsTo(ReturnCar::class, 'return_id', 'return_id');
    }

    public function issuer()
    {
        return $this->belongsTo(Employee::class, 'issued_by', 'employee_id');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'unpaid');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('fine_type', $type);
    }
}