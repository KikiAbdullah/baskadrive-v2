<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    protected $table = 'm_employee';

    protected $primaryKey = 'employee_id';

    public $timestamps = false;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'position',
        'hire_date', 'username', 'password_hash', 'role', 'is_active',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function rentalsProcessed()
    {
        return $this->hasMany(Rental::class, 'employee_id', 'employee_id');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'employee_id', 'employee_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}