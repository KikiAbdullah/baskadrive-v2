<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageReport extends Model
{
    protected $table = 'tr_damage_report';

    protected $primaryKey = 'damage_id';

    protected $fillable = [
        'rental_id', 'vehicle_id', 'return_id', 'reported_date',
        'damage_type', 'severity', 'location', 'description',
        'repair_cost_estimate', 'actual_repair_cost', 'status',
        'inspected_by', 'inspected_at', 'notes',
    ];

    /**
     * FLE-02: satu kontrak status untuk enum DB, validasi controller, dropdown UI,
     * badge, dan laporan. Menyatukan set skema awal dengan status alur kerja yang
     * sebelumnya hanya ada di UI (inspected/approved/in_repair/rejected/closed).
     */
    public const STATUSES = [
        'reported', 'inspected', 'assessment', 'approved', 'in_repair',
        'repair_in_progress', 'repaired', 'rejected', 'claimed_insurance',
        'written_off', 'closed',
    ];

    /** FLE-11: enum damage_type sesuai skema database (bukan body/engine versi UI). */
    public const DAMAGE_TYPES = [
        'exterior', 'interior', 'mechanical', 'electrical', 'glass', 'tire', 'other',
    ];

    /** FLE-11: severity lengkap termasuk total_loss. */
    public const SEVERITIES = ['minor', 'moderate', 'severe', 'total_loss'];

    protected $casts = [
        'reported_date' => 'datetime',
        'inspected_at' => 'datetime',
        'repair_cost_estimate' => 'decimal:2',
        'actual_repair_cost' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function returnRecord()
    {
        return $this->belongsTo(ReturnCar::class, 'return_id', 'return_id');
    }

    public function inspector()
    {
        return $this->belongsTo(Employee::class, 'inspected_by', 'employee_id');
    }

    public function photos()
    {
        return $this->hasMany(DamagePhoto::class, 'damage_id', 'damage_id');
    }

    public function insuranceClaim()
    {
        return $this->hasOne(InsuranceClaim::class, 'damage_id', 'damage_id');
    }

    /** FLE-07: tagihan denda yang lahir dari laporan kerusakan ini. */
    public function fines()
    {
        return $this->hasMany(Fine::class, 'damage_id', 'damage_id');
    }

    public function scopeReported($query)
    {
        return $query->where('status', 'reported');
    }
}
