<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * FASE 3 audit sewa: notifikasi WhatsApp via queue dengan retry (3x, backoff
 * 30s/120s) — kegagalan tidak lagi ditelan diam-diam; kegagalan final dicatat.
 * Pada driver 'sync' berjalan inline seperti perilaku lama.
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $phone,
        public string $subject,
        public string $message,
        public string $subtitle = '',
    ) {}

    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $sent = function_exists('kirimWA')
            ? kirimWA($this->phone, $this->subject, $this->message, $this->subtitle)
            : false;

        if (! $sent) {
            throw new \RuntimeException('Pengiriman WhatsApp gagal (cek APP_WHATSAPP_API pada .env).');
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('WA notification failed permanently: '.$exception->getMessage(), [
            'phone' => $this->phone,
            'subject' => $this->subject,
        ]);
    }
}
