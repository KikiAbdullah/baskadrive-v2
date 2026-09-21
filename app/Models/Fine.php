<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fine extends Model
{
    protected $table = 'tr_fine';

    protected $primaryKey = 'fine_id';

    public $timestamps = false;

    protected $fillable = [
        'rental_id', 'return_id', 'damage_id', 'fine_type', 'description', 'amount',
        'status', 'issued_date', 'paid_date', 'paid_by', 'issued_by',
        'waived_by', 'waived_at', 'notes',
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

    /** Laporan kerusakan sumber bila denda ini tagihan biaya perbaikan (FLE-07). */
    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class, 'damage_id', 'damage_id');
    }

    /** Denda kerusakan yang sudah pernah ditagihkan untuk satu laporan kerusakan. */
    public function scopeForDamage($query, int $damageId)
    {
        return $query->where('damage_id', $damageId);
    }

    public function issuer()
    {
        return $this->belongsTo(Employee::class, 'issued_by', 'employee_id');
    }

    /** Petugas yang mencatat pembayaran — terpisah dari penerbit denda (FIN-02). */
    public function payer()
    {
        return $this->belongsTo(Employee::class, 'paid_by', 'employee_id');
    }

    /** Petugas yang membebaskan denda. */
    public function waivor()
    {
        return $this->belongsTo(Employee::class, 'waived_by', 'employee_id');
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
