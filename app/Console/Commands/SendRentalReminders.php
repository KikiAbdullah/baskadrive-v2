<?php

namespace App\Console\Commands;

use App\Models\Rental;
use Illuminate\Console\Command;

class SendRentalReminders extends Command
{
    protected $signature = 'rental:send-reminders';

    protected $description = 'Kirim pengingat WhatsApp H-1 jadwal pengembalian kendaraan ke pelanggan (audit 2.3)';

    public function handle(): int
    {
        if (! function_exists('kirimWA')) {
            require_once app_path('Helpers/fungsi-helper.php');
        }

        $tomorrow = now()->addDay();

        $rentals = Rental::with(['customer', 'vehicle'])
            ->where('status', 'ongoing')
            ->whereDate('rental_end_date', $tomorrow->toDateString())
            ->get();

        $sent = 0;
        foreach ($rentals as $rental) {
            $phone = $rental->customer?->phone;
            if (! $phone) {
                continue;
            }

            $text = 'Halo '.$rental->customer?->full_name.', kami mengingatkan bahwa sewa *'.$rental->rental_code.'* '
                .'dengan kendaraan *'.($rental->vehicle?->license_plate ?? '-').'* '
                .'akan jatuh tempo pengembalian pada '.optional($rental->rental_end_date)->format('d M Y H:i').'.'
                ."\nMohon siapkan kendaraan dalam kondisi baik. Terima kasih!";

            try {
                kirimWA($phone, 'Pengingat Pengembalian Kendaraan', $text);
                $sent++;
            } catch (\Throwable $e) {
                $this->warn('Gagal kirim WA ke '.$phone.': '.$e->getMessage());
            }
        }

        $this->info('Reminder H-1: '.$sent.'/'.$rentals->count().' pengembalian dijadwalkan besok.');

        return self::SUCCESS;
    }
}
