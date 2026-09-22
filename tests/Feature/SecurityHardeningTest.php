<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Regresi audit keamanan (5 temuan):
 * - OTP 2FA: batal otomatis setelah 5 percobaan salah (di luar throttle IP).
 * - Password seeder: produksi default aman, mode demo eksplisit via env.
 * - Kontrak lama (login superadmin/superadmin di test) tetap terjaga via phpunit.xml.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->user = User::whereHas('roles', fn ($q) => $q->where('name', 'SUPERADMIN'))->first();

        // 2FA diaktifkan agar alur OTP teruji penuh (di produksi bila APP_2FA=false,
        // middleware two_factor_success me-redirect seluruh alur ke beranda — tidak relevan).
        config(['2fa.enabled' => true]);
    }

    // ------------------------------------------------------------------
    // OTP 2FA — brute force OTP tidak mungkin
    // ------------------------------------------------------------------

    /**
     * Siapkan user seolah memegang OTP aktif: token_2fa diisi hash kode dan
     * belum kedaluwarsa (kontrak verifikasi: Hash::check + isFuture).
     */
    private function withActiveOtp(string $code): void
    {
        $this->user->forceFill([
            'token_2fa' => \Hash::make($code),
            'token_2fa_expires_at' => now()->addMinutes(5),
        ])->save();

        $this->actingAs($this->user);
    }

    public function test_otp_disabled_after_max_failed_attempts(): void
    {
        // Throttle IP diuji terpisah (LoginThrottleTest) — di sini lapisan kedua:
        // pembatalan OTP setelah MAX percobaan. Throttle IP dimatikan agar POST
        // ke-6 (kode benar, uji guard attempts) tidak tertahan 429.
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->withActiveOtp('12345');
        $route = route('verifyTwoFactor');

        // 5 percobaan salah → OTP dibatalkan (cache attempts mencapai MAX)
        for ($i = 0; $i < 5; $i++) {
            $this->post($route, ['otp' => '00000']);
        }

        // OTP yang BENAR pun ditolak — guard attempts dieksekusi SEBELUM
        // perbandingan kode, jadi kode benar pun tidak menyelamatkan sesi.
        $this->withActiveOtp('12345');
        $response = $this->post($route, ['otp' => '12345']);

        $attemptsKey = 'otp_attempts:'.$this->user->id;
        $this->assertGreaterThanOrEqual(5, Cache::get($attemptsKey, 0), 'Percobaan salah terekam di cache');
        $this->assertNull($this->user->fresh()->token_2fa, 'OTP aktif dibatalkan setelah batas percobaan');
        $this->assertTrue($response->isRedirection(), 'Dikembalikan ke form 2FA dengan error');
    }

    public function test_otp_attempts_reset_on_success(): void
    {
        $this->withActiveOtp('54321');

        // 1 salah (OTP one-shot ikut dibatalkan) lalu kode baru, benar
        $this->post(route('verifyTwoFactor'), ['otp' => '11111']);
        $this->withActiveOtp('54321');
        $response = $this->post(route('verifyTwoFactor'), ['otp' => '54321']);

        $attemptsKey = 'otp_attempts:'.$this->user->id;
        $this->assertSame(0, (int) Cache::get($attemptsKey, 0), 'Percobaan direset setelah sukses');
        $this->assertNull($this->user->fresh()->token_2fa, 'OTP terkonsumsi setelah sukses');
        $this->assertTrue($response->isRedirection(), 'Sukses → redirect ke tujuan dengan cookie 2FA');
    }

    public function test_expired_otp_rejected(): void
    {
        $this->user->forceFill([
            'token_2fa' => \Hash::make('99999'),
            'token_2fa_expires_at' => now()->subMinute(),
        ])->save();
        $this->actingAs($this->user);

        $response = $this->post(route('verifyTwoFactor'), ['otp' => '99999']);

        $this->assertNull($this->user->fresh()->token_2fa, 'OTP kedaluwarsa dibatalkan, tidak bisa dipakai lagi');
        $this->assertTrue($response->isRedirection());
    }

    // ------------------------------------------------------------------
    // Password seeder — guard produksi
    // ------------------------------------------------------------------

    public function test_seeder_demo_password_mode_is_env_guarded(): void
    {
        $seeder = new UserSeeder;

        $ref = new \ReflectionProperty($seeder, 'demoPasswords');
        $ref->setAccessible(true);

        // Di lingkungan test env APP_SEED_DEMO_PASSWORDS=true (kontrak seluruh suite)
        $this->assertTrue($ref->getValue($seeder), 'Mode demo aktif di test via phpunit.xml');

        // Secara default (tanpa env) produksi TIDAK memakai password demo
        $code = file_get_contents(database_path('seeders/UserSeeder.php'));
        $this->assertStringContainsString("env('APP_SEED_DEMO_PASSWORDS', false)", $code, 'Default env harus false (aman di produksi)');
    }

    public function test_api_login_endpoint_responds(): void
    {
        // API login dibatasi lajunya (throttle:10,1) — endpoint tidak boleh
        // terbuka tanpa batas. Kredensial salah harus ditolak dengan rapi.
        $response = $this->postJson('/api/auth/login', [
            'username' => 'superadmin',
            'password' => 'password-salah',
        ]);

        // 401 (kredensial) / 422 (validasi) / 429 (throttle) — bukan 500
        $this->assertContains($response->status(), [401, 422, 429]);
    }

    public function test_wa_helper_uses_post_header_by_default(): void
    {
        $code = file_get_contents(app_path('Helpers/KirimWAHelper.php'));

        // Kredensial dikirim via header (bukan query string), POST sebagai jalur
        // utama; GET hanya fallback transisi yang di-guard config eksplisit.
        $this->assertStringContainsString("'X-Session-Key'", $code, 'Kredensial dikirim via header');
        $this->assertStringContainsString("request('POST'", $code, 'Jalur utama POST');
        $this->assertStringContainsString('allow_get_fallback', $code, 'GET fallback di-guard config');
    }
}
