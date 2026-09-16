<?php

namespace App\Console\Commands;

use App\Http\Controllers\Rental\SystemController;
use App\Models\UserLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackupDatabase extends Command
{
    protected $signature = 'system:backup {--keep=14 : Jumlah file backup terbaru yang dipertahankan}';

    protected $description = 'Mencadangkan database ke storage/app/backups + prune retensi (SYS-07)';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Backup SQL dump hanya didukung untuk MySQL.');
            return self::FAILURE;
        }

        set_time_limit(0);

        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'baskadrive_backup_' . now()->format('Ymd_His') . '.sql';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error('Tidak dapat menulis ke ' . $path);
            return self::FAILURE;
        }

        $tables = SystemController::backupTableList();
        SystemController::writeBackupDump($handle, $tables);
        fclose($handle);

        UserLog::create([
            'user_id' => null,
            'action' => 'export',
            'menu' => 'system.backup',
            'message' => 'Backup database otomatis terjadwal (' . count($tables) . ' tabel, ' . $filename . ')',
        ]);

        // Retensi: pertahankan N file terbaru
        $keep = max(1, (int) $this->option('keep'));
        $files = glob($dir . DIRECTORY_SEPARATOR . 'baskadrive_backup_*.sql') ?: [];
        rsort($files);
        $pruned = 0;
        foreach (array_slice($files, $keep) as $old) {
            if (@unlink($old)) {
                $pruned++;
            }
        }

        $this->info("Backup tersimpan: {$filename} (" . count($tables) . " tabel, prune {$pruned} lama).");

        return self::SUCCESS;
    }
}
