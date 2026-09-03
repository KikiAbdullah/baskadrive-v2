<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model
{
    protected $table = 'tr_journal_detail';

    protected $primaryKey = 'detail_id';

    public $timestamps = false;

    protected $fillable = [
        'journal_id', 'account_id', 'debit', 'credit', 'description',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function journal()
    {
        return $this->belongsTo(Journal::class, 'journal_id', 'journal_id');
    }

    public function account()
    {
        return $this->belongsTo(Coa::class, 'account_id', 'account_id');
    }
}