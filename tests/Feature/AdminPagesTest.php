<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function superadmin(): User
    {
        return User::where('username', 'superadmin')->first();
    }

    public function test_dashboard_renders(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
    }

    public function test_permission_page_renders(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/user-setup/permission');
        $response->assertStatus(200);
    }

    public function test_role_page_renders(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/user-setup/role');
        $response->assertStatus(200);
    }

    public function test_user_page_renders(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/user-setup/user');
        $response->assertStatus(200);
    }

    public function test_log_viewer_renders(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/debug/log-viewer');
        $response->assertStatus(200);
    }

    public function test_permission_get_data_returns_json(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/user-setup/permission/get-data');
        $response->assertStatus(200);
        $response->assertJsonStructure(['draw', 'recordsTotal']);
    }

    public function test_user_get_data_returns_json(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/user-setup/user/get-data');
        $response->assertStatus(200);
        $response->assertJsonStructure(['draw', 'recordsTotal']);
    }
}