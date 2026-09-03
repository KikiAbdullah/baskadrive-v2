<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $table = 'm_customer';

    protected $primaryKey = 'customer_id';

    protected $fillable = [
        'customer_type', 'first_name', 'last_name', 'company_name',
        'email', 'phone', 'address', 'city', 'province', 'postal_code', 'country',
        'driver_license_number', 'driver_license_expiry', 'driver_license_photo',
        'id_card_number', 'id_card_photo', 'date_of_birth', 'is_verified', 'notes',
    ];

    protected $casts = [
        'customer_type' => 'string',
        'driver_license_expiry' => 'date',
        'date_of_birth' => 'date',
        'is_verified' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function rentals()
    {
        return $this->hasMany(Rental::class, 'customer_id', 'customer_id');
    }

    public function invoices()
    {
        return $this->hasManyThrough(Invoice::class, Rental::class, 'customer_id', 'rental_id', 'customer_id', 'rental_id');
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeIndividual($query)
    {
        return $query->where('customer_type', 'individual');
    }

    public function scopeCorporate($query)
    {
        return $query->where('customer_type', 'corporate');
    }
}