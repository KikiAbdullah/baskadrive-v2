<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Workshop extends Model
{
    protected $table = 'm_workshop';

    protected $primaryKey = 'workshop_id';

    public $timestamps = false;

    protected $fillable = [
        'name', 'address', 'phone', 'contact_person', 'rating', 'specialization', 'is_active',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function maintenanceRecords()
    {
        return $this->hasMany(Maintenance::class, 'workshop_id', 'workshop_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}