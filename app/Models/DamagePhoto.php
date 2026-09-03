<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamagePhoto extends Model
{
    protected $table = 'tr_damage_photo';

    protected $primaryKey = 'photo_id';

    public $timestamps = false;

    protected $fillable = [
        'damage_id', 'photo_url', 'caption',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function damageReport()
    {
        return $this->belongsTo(DamageReport::class, 'damage_id', 'damage_id');
    }
}