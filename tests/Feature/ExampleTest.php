<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_login_page_returns_200(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login(): void
    {
        $response = $this->post('/login', [
            'username' => 'superadmin',
            'password' => 'superadmin',
        ]);
        $response->assertStatus(302);
        $response->assertRedirect('/');
    }

    public function test_authenticated_user_can_visit_dashboard(): void
    {
        $user = \App\Models\User::where('username', 'superadmin')->first();
        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200);
    }
}