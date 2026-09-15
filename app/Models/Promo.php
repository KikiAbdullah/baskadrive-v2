<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promo extends Model
{
    protected $table = 'm_promo';

    protected $primaryKey = 'promo_id';

    public $timestamps = false;

    protected $fillable = [
        'promo_code', 'description', 'discount_type', 'discount_value',
        'min_rental_days', 'valid_from', 'valid_to', 'max_usage', 'usage_count',
        'applicable_categories', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_rental_days' => 'integer',
        'valid_from' => 'date',
        'valid_to' => 'date',
        'max_usage' => 'integer',
        'usage_count' => 'integer',
        'applicable_categories' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function rentals()
    {
        return $this->hasMany(Rental::class, 'promo_id', 'promo_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('valid_from', '<=', now()->toDateString())
            ->where('valid_to', '>=', now()->toDateString())
            ->whereRaw('usage_count < max_usage');
    }
}