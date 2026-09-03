<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coa extends Model
{
    protected $table = 'm_coa';

    protected $primaryKey = 'account_id';

    public $timestamps = false;

    protected $fillable = [
        'account_code', 'account_name', 'account_type', 'parent_id', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'account_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'account_id');
    }

    public function journalDetails()
    {
        return $this->hasMany(JournalDetail::class, 'account_id', 'account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }
}