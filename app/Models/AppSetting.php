<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Cast nilai berdasarkan default yang dikenal (bool/numeric).
     */
    public function getTypedValueAttribute(): mixed
    {
        $raw = $this->value;

        if ($raw === null) {
            return null;
        }

        return match (true) {
            $raw === 'true' || $raw === 'false' => $raw === 'true',
            is_numeric($raw) && ! in_array($this->key, ['company_phone', 'currency_symbol']) => $raw + 0,
            default => $raw,
        };
    }
}
