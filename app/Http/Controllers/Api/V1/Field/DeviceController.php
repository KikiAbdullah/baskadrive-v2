<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Api\V1\Field\Concerns\InteractsWithFieldApi;
use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * POST /devices — daftarkan FCM token perangkat (upsert, konsep B.6).
 *
 * Tabel `m_device` belum ada pada MVP (konsep §11 fase 4) — token disimpan
 * sementara di cache 60 hari per device_id; migrasi tabel penuh menyusul
 * tanpa mengubah kontrak endpoint.
 */
class DeviceController extends ApiController
{
    use InteractsWithFieldApi;

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'device_id' => 'required|string|max:191',
            'fcm_token' => 'nullable|string|max:500',
            'app_version' => 'nullable|string|max:20',
            'os_version' => 'nullable|string|max:50',
        ]);

        $deviceId = $request->input('device_id');

        Cache::put(
            'field_device:'.$deviceId,
            [
                'user_id' => $request->user()?->id,
                'fcm_token' => $request->input('fcm_token'),
                'app_version' => $request->input('app_version'),
                'os_version' => $request->input('os_version'),
                'registered_at' => now()->toIso8601String(),
            ],
            now()->addDays(60),
        );

        return $this->ok([
            'device_id' => $deviceId,
            'registered' => true,
        ], 'Perangkat terdaftar.');
    }
}
