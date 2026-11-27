<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien Laravel untuk gateway Node.js internal (whatsapp-gateway/server.js).
 *
 * Konsep: Baileys (utama) + whatsapp-web.js (cadangan), masing-masing punya
 * saklar on/off tersendiri (app_settings: whatsapp_baileys_enabled /
 * whatsapp_wwebjs_enabled) dan status live diambil langsung dari gateway.
 */
class WhatsAppGateway
{
    public const PROVIDERS = ['baileys', 'wwebjs'];

    public const LABELS = [
        'baileys' => 'Baileys',
        'wwebjs' => 'WhatsApp Web JS',
    ];

    /**
     * Panggilan HTTP ke gateway. Mengembalikan array body atau null bila
     * gateway tidak dapat dihubungi.
     */
    public static function call(string $method, string $path, array $options = [], ?int $timeout = null): ?array
    {
        $base = rtrim((string) config('whatsapp.gateway_url'), '/');
        if ($base === '' || $base === null) {
            return null;
        }

        $headers = ['Accept' => 'application/json'];

        $token = (string) config('whatsapp.gateway_token');
        if ($token !== '') {
            $headers['X-Gateway-Token'] = $token;
        }

        try {
            $http = Http::withHeaders($headers)->timeout($timeout ?? (int) config('whatsapp.timeout', 10));

            $response = strtoupper($method) === 'GET'
                ? $http->get($base.$path)
                : $http->post($base.$path, $options['json'] ?? []);

            $decoded = $response->json();

            return [
                'http' => $response->status(),
                'ok' => $response->successful(),
                'body' => is_array($decoded) ? $decoded : ['raw' => $response->body()],
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp gateway tidak terjangkau: '.$e->getMessage());

            return null;
        }
    }

    /** Apakah provider diizinkan menyala dari UI (app_settings). */
    public static function isEnabled(string $provider): bool
    {
        return match ($provider) {
            'baileys' => (bool) AppSettings::get('whatsapp_baileys_enabled', true),
            'wwebjs' => (bool) AppSettings::get('whatsapp_wwebjs_enabled', false),
            default => false,
        };
    }

    /** Atur saklar provider di app_settings. */
    public static function setEnabled(string $provider, bool $on): void
    {
        $key = $provider === 'baileys' ? 'whatsapp_baileys_enabled' : 'whatsapp_wwebjs_enabled';
        AppSettings::set($key, $on);
    }

    /**
     * Sinkronkan saklar ke gateway (sessions/enabled.json) — gateway sekaligus
     * memulai sesi saat dinyalakan / menghentikannya saat dimatikan, dan
     * mengingat state terakhir saat proses gateway direstart.
     */
    public static function syncEnabledOnGateway(string $provider, bool $on): void
    {
        self::call('POST', '/enable', ['json' => ['provider' => $provider, 'on' => $on]]);
    }

    /** Provider aktif sesuai urutan prioritas (utama dulu, lalu cadangan). */
    public static function activeProviders(): array
    {
        $order = self::PROVIDERS;
        if (AppSettings::get('whatsapp_provider', config('whatsapp.default_provider')) === 'wwebjs') {
            $order = array_reverse($order);
        }

        return array_values(array_filter($order, fn ($p) => self::isEnabled($p)));
    }

    /**
     * Status lengkap tiap provider: saklar UI + kondisi live dari gateway.
     * Dipakai halaman admin; selalu aman bila gateway mati.
     */
    public static function status(): array
    {
        $live = self::call('GET', '/status', timeout: 4)['body'] ?? [];

        return collect(self::PROVIDERS)
            ->mapWithKeys(function (string $provider) use ($live) {
                $info = $live[$provider] ?? [];

                return [$provider => [
                    'enabled' => self::isEnabled($provider),
                    'connected' => (bool) ($info['connected'] ?? false),
                    'qr_ready' => (bool) ($info['qr_ready'] ?? false),
                    'qr' => $info['qr'] ?? null,
                    'user' => $info['user'] ?? null,
                    'error' => $info['error'] ?? null,
                    'messages_sent' => (int) ($info['messages_sent'] ?? 0),
                ]];
            })
            ->all();
    }

    /**
     * Kirim pesan teks lewat provider aktif pertama; failover ke berikutnya
     * bila provider tidak connected atau gateway menolak.
     */
    public static function sendText(string $phone, string $message, string $provider = null): array
    {
        $providers = $provider !== null ? [$provider] : self::activeProviders();

        foreach ($providers as $candidate) {
            $result = self::call('POST', '/send', [
                'json' => [
                    'provider' => $candidate,
                    'number' => $phone,
                    'message' => $message,
                ],
            ]);

            if ($result === null) {
                continue; // gateway tidak terjangkau → coba provider berikutnya
            }

            if ($result['ok'] && ($result['body']['ok'] ?? false)) {
                return ['ok' => true, 'provider' => $candidate];
            }

            $code = $result['body']['code'] ?? null;
            if ($code === 'PROVIDER_DISABLED' || $code === 'PROVIDER_NOT_CONNECTED') {
                continue; // failover ke provider berikutnya
            }

            return ['ok' => false, 'provider' => $candidate, 'error' => $result['body']['error'] ?? 'Gateway menolak pesan.'];
        }

        return ['ok' => false, 'provider' => $provider, 'error' => 'Gateway WhatsApp tidak tersedia atau tidak ada provider aktif.'];
    }

    /** Kirim file (URL publik) sebagai dokumen/gambar. */
    public static function sendFile(string $phone, string $url, string $caption = '', string $provider = null): array
    {
        $providers = $provider !== null ? [$provider] : self::activeProviders();

        foreach ($providers as $candidate) {
            $result = self::call('POST', '/send-file', [
                'json' => [
                    'provider' => $candidate,
                    'number' => $phone,
                    'url' => $url,
                    'caption' => $caption,
                ],
            ]);

            if ($result === null) {
                continue;
            }

            if ($result['ok'] && ($result['body']['ok'] ?? false)) {
                return ['ok' => true, 'provider' => $candidate];
            }

            $code = $result['body']['code'] ?? null;
            if ($code === 'PROVIDER_DISABLED' || $code === 'PROVIDER_NOT_CONNECTED') {
                continue;
            }

            return ['ok' => false, 'provider' => $candidate, 'error' => $result['body']['error'] ?? 'Gateway menolak file.'];
        }

        return ['ok' => false, 'provider' => $provider, 'error' => 'Gateway WhatsApp tidak tersedia atau tidak ada provider aktif.'];
    }

    /** Mulai sesi (inisialisasi client) pada provider tertentu. */
    public static function start(string $provider): ?array
    {
        if (! self::isEnabled($provider)) {
            return ['ok' => false, 'error' => 'Provider masih dimatikan.'];
        }

        return self::call('POST', "/sessions/{$provider}/start")['body'] ?? null;
    }

    /** Hentikan sesi pada provider tertentu. */
    public static function stop(string $provider): ?array
    {
        return self::call('POST', "/sessions/{$provider}/stop")['body'] ?? null;
    }

    /** Hapus sesi tersimpan (logout device + bersihkan auth state). */
    public static function logout(string $provider): ?array
    {
        return self::call('POST', "/sessions/{$provider}/logout")['body'] ?? null;
    }
}
