<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    /** Alokasi pembayaran pokok sewa (default). */
    public const ALLOCATION_RENTAL = 'rental';

    /** Alokasi pembayaran denda — terpisah dari pelunasan pokok (FIN-03). */
    public const ALLOCATION_FINE = 'fine';

    /** Alokasi penerimaan deposit jaminan — kewajiban terpisah (FIN-08). */
    public const ALLOCATION_DEPOSIT = 'deposit';

    protected $table = 'tr_payment';

    protected $primaryKey = 'payment_id';

    public $timestamps = false;

    protected $fillable = [
        'invoice_id', 'rental_id', 'payment_date', 'amount',
        'payment_method', 'reference_number', 'status', 'allocation', 'notes',
    ];

    protected $attributes = [
        'allocation' => self::ALLOCATION_RENTAL,
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

    /** Pembayaran pokok sewa saja (FIN-03): denda tidak dihitung sebagai pelunasan. */
    public function scopeRentalPrincipal($query)
    {
        return $query->where('allocation', self::ALLOCATION_RENTAL);
    }

    /** Pembayaran denda (FIN-03). */
    public function scopeFineAllocation($query)
    {
        return $query->where('allocation', self::ALLOCATION_FINE);
    }
}
