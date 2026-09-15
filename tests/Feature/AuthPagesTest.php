<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    /**
     * Audit Setup S-11/S-01: pendaftaran publik dimatikan secara default
     * (config app.registration_enabled = false) => rute tidak terdaftar.
     */
    public function test_register_page_disabled_by_default(): void
    {
        $this->assertFalse(config('app.registration_enabled'));
        $this->get('/register')->assertStatus(404);
        $this->post('/register', [
            'name' => 'Probe', 'email' => 'probe@contoh.test',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertStatus(404);
    }

    public function test_password_request_page_renders(): void
    {
        $response = $this->get('/password/reset');
        $response->assertStatus(200);
    }

    public function test_password_confirm_page_renders(): void
    {
        $user = \App\Models\User::where('username', 'superadmin')->first();
        $response = $this->actingAs($user)->get('/password/confirm');
        $response->assertStatus(200);
    }

    public function test_email_verify_page_redirects_when_verified(): void
    {
        $user = \App\Models\User::where('username', 'superadmin')->first();
        $response = $this->actingAs($user)->get('/email/verify');
        $response->assertStatus(302);
        $response->assertRedirect('/');
    }
}