<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Support\AppSettings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Isi key pengaturan yang BELUM ada dengan defaults kode (SYS-07).
     * Idempoten: tidak menimpa nilai yang sudah diubah admin.
     */
    public function run(): void
    {
        $created = 0;

        foreach (AppSettings::defaults() as $key => $default) {
            $exists = AppSetting::where('key', $key)->exists();
            if (! $exists) {
                AppSetting::create([
                    'key' => $key,
                    'value' => $default === null ? null : (is_bool($default) ? ($default ? 'true' : 'false') : (string) $default),
                ]);
                $created++;
            }
        }

        $this->command->info("SettingsSeeder selesai: {$created} key baru ditambahkan.");
    }
}
