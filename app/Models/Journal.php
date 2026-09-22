<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Journal extends Model
{
    protected $table = 'tr_journal';

    protected $primaryKey = 'journal_id';

    public $timestamps = false;

    /**
     * AKN-06: kontrak label tipe jurnal selaras enum kolom `journal_type` —
     * sumber tunggal untuk filter UI, validasi, dan penyajian.
     */
    public const TYPES = [
        'rental' => 'Rental',
        'payment' => 'Pembayaran',
        'refund' => 'Refund',
        'maintenance' => 'Maintenance',
        'fine' => 'Denda',
        'adjustment' => 'Penyesuaian',
        'manual' => 'Manual',
        'insurance' => 'Asuransi',
    ];

    protected $fillable = [
        'transaction_date', 'reference_number', 'description',
        'journal_type', 'created_by',
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function details()
    {
        return $this->hasMany(JournalDetail::class, 'journal_id', 'journal_id');
    }

    public function creator()
    {
        return $this->belongsTo(Employee::class, 'created_by', 'employee_id');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('journal_type', $type);
    }
}
