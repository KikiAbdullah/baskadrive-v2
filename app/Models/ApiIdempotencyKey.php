<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiIdempotencyKey extends Model
{
    public $timestamps = false;

    protected $table = 'api_idempotency_keys';

    protected $primaryKey = 'client_uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'client_uuid', 'user_id', 'endpoint', 'http_status', 'result', 'created_at',
    ];

    protected $casts = [
        'result' => 'array',
        'created_at' => 'datetime',
    ];
}
