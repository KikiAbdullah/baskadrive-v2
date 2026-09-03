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

    public function test_register_page_renders(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(200);
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