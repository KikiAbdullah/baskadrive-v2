<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldTaskAssignment extends Model
{
    protected $table = 'field_task_assignments';

    protected $primaryKey = 'assignment_id';

    protected $fillable = [
        'rental_id', 'assigned_to', 'scheduled_at', 'status', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function rental()
    {
        return $this->belongsTo(Rental::class, 'rental_id', 'rental_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
