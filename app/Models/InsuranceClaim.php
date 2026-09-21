<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InsuranceClaim extends Model
{
    protected $table = 'tr_insurance_claim';

    protected $primaryKey = 'claim_id';

    public $timestamps = false;

    protected $fillable = [
        'rental_id', 'damage_id', 'claim_number', 'insurance_provider',
        'policy_number', 'claim_date', 'claim_amount', 'approved_amount',
        'status', 'approved_date', 'paid_date', 'paid_at', 'notes',
    ];

    protected $casts = [
        'claim_date' => 'datetime',
        'approved_date' => 'datetime',
        'paid_date' => 'date',
        'paid_at' => 'datetime',
        'claim_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class, 'damage_id', 'damage_id');
    }
}
