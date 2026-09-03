<?php

namespace App\Helpers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class KirimWAHelper
{
    public function kirim($nohp, $subject, $teks, $tekstengah)
    {
        try {
            $client = new Client;
            $endpoint = config('whatsapp.endpoint');

            $tujuan = $this->normalizeNumber($nohp);

            if (config('app.env') == 'local') {
                $tujuan = config('whatsapp.number_testing');
            }

            $response = $client->request('GET', $endpoint, ['query' => [
                'number' => $tujuan,
                'subject' => $subject,
                'text' => $teks,
                'text_tengah' => $tekstengah,
                'session_id' => config('whatsapp.session_name'),
                'session_key' => config('whatsapp.session_key'),
            ]]);

            return true;
        } catch (RequestException $e) {
            return false;
        }
    }

    public function kirimWithFooter($nohp, $subject, $teks, $tekstengah, $teksfooter)
    {
        $client = new Client;
        $endpoint = config('whatsapp.endpoint');

        $tujuan = $this->normalizeNumber($nohp);

        if (config('app.env') == 'local') {
            $tujuan = config('whatsapp.number_testing');
        }

        $response = $client->request('GET', $endpoint, ['query' => [
            'number' => $tujuan,
            'subject' => $subject,
            'text' => $teks,
            'text_tengah' => $tekstengah,
            'text_footer' => $teksfooter,
            'session_id' => config('whatsapp.session_name'),
            'session_key' => config('whatsapp.session_key'),
        ]]);

        return $response->getStatusCode();
    }

    public function kirimAttachment($nohp, $subject, $teks, $tekstengah, $fileurl)
    {
        try {
            $client = new Client;
            $endpoint = config('whatsapp.endpoint');

            $tujuan = $this->normalizeNumber($nohp);

            $response = $client->request('GET', $endpoint, ['query' => [
                'number' => $tujuan,
                'subject' => $subject,
                'text' => $teks,
                'text_tengah' => $tekstengah,
                'session_id' => config('whatsapp.session_name'),
                'session_key' => config('whatsapp.session_key'),
                'file_url' => $fileurl,
                'media' => 'isTrue',
            ]]);

            return true;
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