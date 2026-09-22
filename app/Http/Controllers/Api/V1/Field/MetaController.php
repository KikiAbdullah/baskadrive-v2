<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Http\Controllers\Api\ApiController;
use App\Models\DamageReport;
use App\Support\AppSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /meta — master kecil untuk cache mobile (konsep B.6).
 * Mobile me-refresh harian; `min_app_version` memicu 426 pembaruan paksa.
 */
class MetaController extends ApiController
{
    use InteractsWithFieldApi;

    public function index(Request $request): JsonResponse
    {
        $checklist = [
            ['key' => 'stnk', 'label' => 'STNK', 'present' => true],
            ['key' => 'sim_driver', 'label' => 'SIM Pengemudi', 'present' => true],
            ['key' => 'spare_tire', 'label' => 'Ban Serep', 'present' => true],
            ['key' => 'jack', 'label' => 'Dongkrak', 'present' => true],
            ['key' => 'toolkit', 'label' => 'Perkakas', 'present' => true],
            ['key' => 'body_fluid', 'label' => 'Cairan Ranjing', 'present' => true],
            ['key' => 'helm_2', 'label' => 'Dua Helm (motor)', 'present' => false],
            ['key' => 'phone_holder', 'label' => 'Holder HP', 'present' => false],
        ];

        $fineRates = [
            'late_per_day' => 'base_rate_per_day', // keterlambatan dihitung dari tarif harian sewa
            'missing_item' => (float) config('field.missing_item_rate', 250000),
            'fuel_refill_per_level' => (float) config('field.fuel_refill_rate', 150000),
        ];

        return response()->json(responseSuccess([
            'min_app_version' => config('field.min_app_version', '1.0.0'),
            'checklist_template' => $checklist,
            'fine_rates' => $fineRates,
            'fuel_policy' => [
                'levels' => ['full', 'three_quarter', 'half', 'quarter', 'empty'],
                'refill_per_level' => (float) config('field.fuel_refill_rate', 150000),
            ],
            'operator_payment_limit' => (float) config('field.operator_payment_limit', 5000000),
            'damage_types' => DamageReport::DAMAGE_TYPES,
            'severities' => DamageReport::SEVERITIES,
            'server_time' => now()->toIso8601String(),
        ], 'Meta referensi'));
    }
}
