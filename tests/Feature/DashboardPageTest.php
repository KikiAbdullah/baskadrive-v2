<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi halaman Dashboard Operasional (/dashboard, DashboardController).
 *
 * Laten bug bersejarah: direktif @endcan yang menempel pada kata sebelumnya
 * ("Sewa@endcan.") tidak dikenali parser Blade sehingga view gagal dikompilasi
 * (ParseError: unexpected end of file) — dan tidak ada test yang me-render
 * halaman ini sehingga 500 tidak terdeteksi. Test ini menjamin halaman
 * ter-render untuk setiap peran.
 */
class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    protected function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    public function test_renders_for_privileged_roles(): void
    {
        foreach (['superadmin', 'manager', 'staff'] as $username) {
            $response = $this->actingAs($this->user($username))->get('/dashboard');

            $response->assertOk()
                ->assertViewIs('dashboard.index')
                ->assertViewHas('stats')
                ->assertSee('Peta Armada')
                ->assertSee('Kalender Sewa')
                ->assertSee('Sewa Berjalan')
                ->assertSee('Pengembalian Terdekat')
                ->assertSee('Penjemputan Terdekat');
        }
    }

    public function test_renders_for_viewer_without_rental_sections(): void
    {
        $response = $this->actingAs($this->user('viewer'))->get('/dashboard');

        $response->assertOk()
            ->assertSee('Peta Armada')
            // Seksi berbasis rental_view disembunyikan dari VIEWER.
            ->assertDontSee('Pengembalian Terdekat')
            ->assertDontSee('href="'.e(route('rental.index')).'"', false);
    }

    public function test_period_filter_variants_render(): void
    {
        $this->actingAs($this->user('supervisor'));

        foreach (['today' => 'Hari Ini', 'week' => 'Minggu Ini', 'month' => 'Bulan Ini', 'year' => 'Tahun Ini'] as $period => $label) {
            $this->get('/dashboard?period='.$period)
                ->assertOk()
                ->assertSee($label);
        }
    }
}
