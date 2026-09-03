<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'tr_payment';

    protected $primaryKey = 'payment_id';

    public $timestamps = false;

    protected $fillable = [
        'invoice_id', 'rental_id', 'payment_date', 'amount',
        'payment_method', 'reference_number', 'status', 'notes',
    ];

    protected $casts = [
        'payment_date' => 'datetime',
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'payment_id', 'payment_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}