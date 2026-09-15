<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTempPdf extends Command
{
    protected $signature = 'app:cleanup-temp {--hours=24 : Umur minimum file (jam) yang akan dihapus}';

    protected $description = 'Membersihkan file HTML/PDF sementara hasil cetak yang berusia lebih dari N jam (audit 2.8)';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $threshold = now()->subHours($hours)->getTimestamp();
        $deleted = 0;
        $freed = 0;

        $dirs = [];
        foreach (['pdf-tmp', 'pdf'] as $rel) {
            $abs = Storage::disk('local')->path($rel);
            if (is_dir($abs)) {
                $dirs[] = $abs;
            }
        }

        foreach ($dirs as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getMTime() < $threshold) {
                    $freed += $file->getSize();
                    @unlink($file->getPathname());
                    $deleted++;
                }
            }
        }

        $this->info("Pembersihan selesai: {$deleted} file dihapus (".round($freed / 1024, 1).' KB dibebaskan).');

        return self::SUCCESS;
    }
}
