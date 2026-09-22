<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Klien gateway WhatsApp (audit keamanan — kredensial tidak lagi lewat URL).
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
     * Kirim payload via POST + header kredensial; fallback GET hanya bila
     * secara eksplisit diizinkan (transisi gateway lama).
     */
    private function dispatch($nohp, array $payload)
    {
        try {
            $client = new Client;
            $endpoint = config('whatsapp.endpoint');

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
