<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalDetail extends Model
{
    protected $table = 'tr_rental_detail';

    protected $primaryKey = 'detail_id';

    public $timestamps = false;

    protected $fillable = [
        'rental_id', 'item_type', 'item_name', 'quantity', 'unit_price', 'total_price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }
}