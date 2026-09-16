<?php

namespace App\Console\Commands;

use App\Models\Rental;
use App\Support\AppSettings;
use Illuminate\Console\Command;

/**
 * FASE 3 audit sewa: mengaktifkan status 'overdue' yang selama ini mati —
 * rental ongoing yang melewati rental_end_date + grace period ditandai overdue.
 * Trigger DB membiarkan status overdue (kendaraan tetap rented) ✓.
 */
class MarkOverdueRentals extends Command
{
    protected $signature = 'rentals:mark-overdue';

    protected $description = 'Tandai sewa ongoing yang lewat jatuh tempo (+grace) menjadi overdue';

    public function handle(): int
    {
        $grace = (int) (AppSettings::get('overdue_grace_minutes') ?? 0);
        $cutoff = now()->subMinutes(max(0, $grace));

        $count = Rental::where('status', 'ongoing')
            ->where('rental_end_date', '<', $cutoff)
            ->update(['status' => 'overdue', 'updated_at' => now()]);

        $this->info("{$count} sewa ditandai overdue (grace {$grace} menit, cutoff {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
