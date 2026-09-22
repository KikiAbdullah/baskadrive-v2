<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Token API kini diterbitkan oleh tymon/jwt-auth melalui endpoint login
 * (`POST /api/auth/login`) — tidak lagi dibuat via seeder (Sanctum).
 * Seeder ini hanya mencetak panduan cara mendapatkan token JWT.
 */
class JwtDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('--- API TOKENS (JWT) ---');
        $this->command->line('');
        $this->command->line('Token JWT didapat langsung dari endpoint login:');
        $this->command->line('');
        $this->command->line('  curl -X POST ' . config('app.url', 'http://localhost:8000') . '/api/auth/login \\');
        $this->command->line("    -H 'Accept: application/json' -H 'Content-Type: application/json' \\");
        $this->command->line("    -d '{\"username\": \"superadmin\", \"password\": \"...\"}'");
        $this->command->line('');
        $this->command->line('Respons sukses (envelope {status,msg,data}):');
        $this->command->line('  {"status":true,"msg":"Login Berhasil","data":{"access_token":"<JWT>","token_type":"Bearer","expires_in":3600,"user":{...}}}');
        $this->command->line('');
        $this->command->line('Gunakan token pada request berikutnya:');
        $this->command->line("  curl -X POST " . config('app.url', 'http://localhost:8000') . "/api/auth/me -H 'Authorization: Bearer <JWT>'");
        $this->command->info('-------------------------');
    }
}
