<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    protected $table = 'tr_refund';

    protected $primaryKey = 'refund_id';

    public $timestamps = false;

    protected $fillable = [
        'payment_id', 'rental_id', 'refund_date', 'amount',
        'refund_type', 'status', 'reference_number', 'notes',
    ];

    protected $casts = [
        'refund_date' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'payment_id');
    }

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function scopeProcessed($query)
    {
        return $query->where('status', 'processed');
    }
}