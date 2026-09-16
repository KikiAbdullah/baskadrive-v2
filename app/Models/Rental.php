<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Rental extends Model
{
    use SoftDeletes;

    protected $table = 'tr_rental';

    protected $primaryKey = 'rental_id';

    protected $fillable = [
        'rental_code', 'customer_id', 'vehicle_id', 'employee_id',
        'pickup_location_id', 'return_location_id', 'promo_id',
        'rental_start_date', 'rental_end_date', 'actual_return_date',
        'rental_days', 'is_with_driver', 'driver_id',
        'base_rate_per_day', 'total_base_price', 'insurance_fee', 'driver_fee',
        'young_driver_fee', 'discount_amount', 'tax_amount', 'tax_percent', 'deposit_amount', 'total_amount',
        'status', 'payment_status', 'notes',
    ];

    protected $casts = [
        'rental_start_date' => 'datetime',
        'rental_end_date' => 'datetime',
        'actual_return_date' => 'datetime',
        'rental_days' => 'integer',
        'is_with_driver' => 'boolean',
        'base_rate_per_day' => 'decimal:2',
        'total_base_price' => 'decimal:2',
        'insurance_fee' => 'decimal:2',
        'driver_fee' => 'decimal:2',
        'young_driver_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id', 'driver_id');
    }

    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location_id', 'location_id');
    }

    public function returnLocation()
    {
        return $this->belongsTo(Location::class, 'return_location_id', 'location_id');
    }

    public function promo()
    {
        return $this->belongsTo(Promo::class, 'promo_id', 'promo_id');
    }

    public function details()
    {
        return $this->hasMany(RentalDetail::class, 'rental_id', 'rental_id');
    }

    public function extensions()
    {
        return $this->hasMany(RentalExtension::class, 'rental_id', 'rental_id');
    }

    public function returnRecord()
    {
        return $this->hasOne(ReturnCar::class, 'rental_id', 'rental_id');
    }

    public function damageReports()
    {
        return $this->hasMany(DamageReport::class, 'rental_id', 'rental_id');
    }

    public function insuranceClaims()
    {
        return $this->hasMany(InsuranceClaim::class, 'rental_id', 'rental_id');
    }

    public function fines()
    {
        return $this->hasMany(Fine::class, 'rental_id', 'rental_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'rental_id', 'rental_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'rental_id', 'rental_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'rental_id', 'rental_id');
    }

    public function inspections()
    {
        return $this->hasMany(RentalInspection::class, 'rental_id', 'rental_id');
    }

    public function handoverOut()
    {
        return $this->hasOne(RentalInspection::class, 'rental_id', 'rental_id')->where('inspection_type', 'handover_out');
    }

    public function handoverIn()
    {
        return $this->hasOne(RentalInspection::class, 'rental_id', 'rental_id')->where('inspection_type', 'handover_in');
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    public function scopeReserved($query)
    {
        return $query->where('status', 'reserved');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', 'unpaid');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['ongoing', 'reserved', 'overdue']);
    }
}