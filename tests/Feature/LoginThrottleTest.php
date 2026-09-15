<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/login', [
                'username' => 'superadmin',
                'password' => 'password-salah',
            ]);
            // usaha ke-1..5: ditolak kredensial (422/419 bukan 429)
            $this->assertLessThan(429, $response->getStatusCode(), "usaha ke-{$i} belum boleh di-throttle");
        }

        $response = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'password-salah',
        ]);
        $response->assertStatus(429);
    }

    public function test_valid_login_still_works_within_limit(): void
    {
        $response = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'superadmin',
        ]);
        $response->assertStatus(302);
        $response->assertRedirect('/');
    }
}
