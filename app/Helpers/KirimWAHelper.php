<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Klien gateway WhatsApp (audit keamanan — kredensial tidak lagi lewat URL).
 *
 * Jalur pengiriman berurutan:
 *  1. Gateway internal Node.js (`whatsapp-gateway/` — Baileys utama +
 *     whatsapp-web.js cadangan, masing-masing punya saklar on/off sendiri)
 *     bila WHATSAPP_GATEWAY_URL terisi di .env.
 *  2. Gateway eksternal lama (APP_WHATSAPP_API) sebagai cadangan bila
 *     gateway internal gagal / tidak dikonfigurasi.
 *
 * PERBAIKAN: `session_key` dulu dikirim sebagai query string GET — bocor ke
 * access log server, log proxy, dan histori browser. Kini seluruh permintaan
 * memakai POST dengan kredensial di header `X-Session-Key`; payload dikirim
 * sebagai JSON body. Kompatibel dengan gateway lama yang hanya menerima GET
 * melalui `whatsapp.allow_get_fallback` (default false — aman secara default).
 */
class KirimWAHelper
{
    public function kirim($nohp, $subject, $teks, $tekstengah)
    {
        return $this->dispatch($nohp, [
            'subject' => $subject,
            'text' => $teks,
            'text_tengah' => $tekstengah,
        ]);
    }

    public function kirimWithFooter($nohp, $subject, $teks, $tekstengah, $teksfooter)
    {
        return $this->dispatch($nohp, [
            'subject' => $subject,
            'text' => $teks,
            'text_tengah' => $tekstengah,
            'text_footer' => $teksfooter,
        ]);
    }

    public function kirimAttachment($nohp, $subject, $teks, $tekstengah, $fileurl)
    {
        return $this->dispatch($nohp, [
            'subject' => $subject,
            'text' => $teks,
            'text_tengah' => $tekstengah,
            'file_url' => $fileurl,
            'media' => 'isTrue',
        ]);
    }

    /**
     * Susun teks WhatsApp bergaya notifikasi ERP dari komponen payload.
     */
    private function composeText(array $payload): string
    {
        $parts = array_filter([
            $payload['subject'] ?? '',
            $payload['text'] ?? '',
            $payload['text_tengah'] ?? '',
            $payload['text_footer'] ?? '',
        ], fn ($v) => $v !== null && $v !== '');

        return implode("\n\n", array_map(fn ($v) => trim((string) $v), $parts));
    }

    /**
     * Coba kirim lewat gateway internal (Baileys/wwebjs). Kembalikan true
     * hanya bila pesan benar-benar terkirim.
     */
    private function tryInternalGateway($nohp, array $payload): bool
    {
        $base = trim((string) config('whatsapp.gateway_url'));
        if ($base === '') {
            return false; // gateway internal tidak dikonfigurasi
        }

        $tujuan = $this->normalizeNumber($nohp);

        if (config('app.env') == 'local') {
            $tujuan = config('whatsapp.number_testing');
        }

        $fileUrl = $payload['file_url'] ?? null;

        $result = $fileUrl
            ? \App\Support\WhatsAppGateway::sendFile($tujuan, (string) $fileUrl, (string) ($payload['text_tengah'] ?? ''))
            : \App\Support\WhatsAppGateway::sendText($tujuan, $this->composeText($payload));

        if (! $result['ok']) {
            \Illuminate\Support\Facades\Log::warning('Gateway WhatsApp internal gagal mengirim: '.($result['error'] ?? '?'));
        }

        return (bool) $result['ok'];
    }

    /**
     * Kirim payload via POST + header kredensial; fallback GET hanya bila
     * secara eksplisit diizinkan (transisi gateway lama).
     */
    private function dispatch($nohp, array $payload)
    {
        // Jalur 1: gateway internal Baileys/wwebjs (bila dikonfigurasi).
        if ($this->tryInternalGateway($nohp, $payload)) {
            return true;
        }

        // Jalur 2: gateway eksternal lama.
        $endpoint = config('whatsapp.endpoint');
        if (empty($endpoint)) {
            \Illuminate\Support\Facades\Log::warning('Pengiriman WhatsApp gagal: gateway internal & gateway lama tidak tersedia.');

            return false;
        }

        try {
            $client = new Client;

            $tujuan = $this->normalizeNumber($nohp);

            if (config('app.env') == 'local') {
                $tujuan = config('whatsapp.number_testing');
            }

            $payload['number'] = $tujuan;
            $payload['session_id'] = config('whatsapp.session_name');

            if (config('whatsapp.allow_get_fallback')) {
                // Mode transisi: gateway belum mendukung POST (kredensial tetap di URL — hindari di produksi).
                $response = $client->request('GET', $endpoint, ['query' => $payload + [
                    'session_key' => config('whatsapp.session_key'),
                ]]);
            } else {
                $response = $client->request('POST', $endpoint, [
                    'json' => $payload,
                    'headers' => [
                        'X-Session-Key' => config('whatsapp.session_key'),
                        'Accept' => 'application/json',
                    ],
                    'http_errors' => false,
                ]);
            }

            return in_array($response->getStatusCode(), [200, 201, 202], true);
        } catch (RequestException $e) {
            return false;
        }
    }

    private function normalizeNumber($nohp)
    {
        $tujuan = str_replace('-', '', $nohp);
        $tujuan = str_replace(' ', '', $tujuan);
        $tujuan = str_replace('+', '', $tujuan);
        $temp = substr($tujuan, 0, 1);
        if ($temp == '0') {
            $tujuan = '62'.substr($tujuan, 1);
        }

        return $tujuan;
    }
}
