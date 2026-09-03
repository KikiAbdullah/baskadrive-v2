<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SanctumTokenSeeder extends Seeder
{
    /**
     * Generate token API untuk user tertentu.
     * Token ditampilkan di console agar bisa langsung digunakan.
     */
    public function run(): void
    {
        $targets = [
            'superadmin' => 'Super Administrator',
            'admin' => 'Administrator',
        ];

        $this->command->info('--- API TOKENS (Sanctum) ---');

        foreach ($targets as $username => $label) {
            $user = User::where('username', $username)->first();

            if (! $user) {
                continue;
            }

            // Hapus token lama untuk nama yang sama
            $user->tokens()->where('name', 'api-token')->delete();

            $token = $user->createToken('api-token');

            $this->command->line("");
            $this->command->info("{$label} ({$username}):");
            $this->command->line("  Token : {$token->plainTextToken}");
            $this->command->line("  Curl  : curl -H 'Authorization: Bearer {$token->plainTextToken}' {$this->getBaseUrl()}/api/auth/me");
        }

        $this->command->info('-----------------------------');
        $this->command->warn('Simpan token di tempat aman! Token hanya ditampilkan sekali saat seeding.');
    }

    protected function getBaseUrl(): string
    {
        return config('app.url', 'http://localhost:8000');
    }
}