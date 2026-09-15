<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAccessGatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $username): \App\Models\User
    {
        return \App\Models\User::where('username', $username)->firstOrFail();
    }

    public function test_viewer_cannot_access_operational_modules(): void
    {
        $viewer = $this->user('viewer');

        foreach (['/rental', '/fleet/damage', '/finance', '/accounting/journal'] as $url) {
            $this->actingAs($viewer)->get($url)->assertStatus(403);
        }
    }

    public function test_viewer_can_access_dashboard_and_report(): void
    {
        $this->actingAs($this->user('viewer'))->get('/')->assertStatus(200);
        $this->actingAs($this->user('viewer'))->get('/report')->assertStatus(200);
    }

    public function test_staff_can_access_rental_but_not_accounting(): void
    {
        $staff = $this->user('staff');
        $this->actingAs($staff)->get('/rental')->assertStatus(200);
        $this->actingAs($staff)->get('/accounting/journal')->assertStatus(403);
    }

    public function test_supervisor_and_manager_can_access_accounting(): void
    {
        $this->actingAs($this->user('supervisor'))->get('/accounting/journal')->assertStatus(200);
        $this->actingAs($this->user('manager'))->get('/accounting/journal')->assertStatus(200);
    }

    public function test_public_registration_assigns_viewer_role(): void
    {
        // Rute hanya terdaftar saat boot dgn flag ON; uji logic controller langsung
        // (sekalian membuktikan jalur defense-in-depth abort(404) TIDAK terpicu saat ON).
        config(['app.registration_enabled' => true]);

        $req = \Illuminate\Http\Request::create('/register', 'POST', [
            'name' => 'Pendatang Baru',
            'email' => 'baru@contoh.test',
            'password' => 'PasswordKuat88',
            'password_confirmation' => 'PasswordKuat88',
        ]);
        $session = app('session.store');
        $session->start();
        $req->setSession(new \Illuminate\Session\SymfonySessionDecorator($session));
        app()->instance('request', $req);

        (new \App\Http\Controllers\Auth\RegisterController)->register($req);

        $baru = \App\Models\User::where('email', 'baru@contoh.test')->first();
        $this->assertNotNull($baru);
        $this->assertTrue($baru->hasRole('VIEWER'), 'registrasi publik otomatis role VIEWER');
        $this->assertTrue($baru->can('dashboard_view'));
        $this->assertFalse($baru->can('rental_view'), 'VIEWER tidak boleh dapat akses operasional');
    }

    public function test_registration_controller_aborts_when_flag_off(): void
    {
        config(['app.registration_enabled' => false]);

        $req = \Illuminate\Http\Request::create('/register', 'POST', [
            'name' => 'X', 'email' => 'x@contoh.test',
            'password' => 'PasswordKuat88', 'password_confirmation' => 'PasswordKuat88',
        ]);
        app()->instance('request', $req);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Auth\RegisterController)->register($req);
    }
}
