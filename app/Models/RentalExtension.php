<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalExtension extends Model
{
    protected $table = 'tr_rental_extension';

    protected $primaryKey = 'extension_id';

    public $timestamps = false;

    protected $fillable = [
        'rental_id', 'old_end_date', 'new_end_date', 'extended_days',
        'additional_base_price', 'additional_tax', 'additional_total',
        'status', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'old_end_date' => 'datetime',
        'new_end_date' => 'datetime',
        'extended_days' => 'integer',
        'additional_base_price' => 'decimal:2',
        'additional_tax' => 'decimal:2',
        'additional_total' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function approver()
    {
        return $this->belongsTo(Employee::class, 'approved_by', 'employee_id');
    }
}