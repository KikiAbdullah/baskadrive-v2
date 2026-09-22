<?php

namespace Tests\Feature;

use App\Models\Rental;
use App\Models\User;
use App\Support\BrandAsset;
use App\Support\QrCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi branding: seluruh logo & favicon aplikasi memakai aset identitas
 * `public/app_local/img/logo.png` (dengan dukungan override logo unggahan
 * perusahaan via Pengaturan Sistem). Favicon adalah turunan persegi 256px
 * dari logo yang sama.
 */
class BrandingRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_default_logo_and_favicon_resolve_to_logo_png(): void
    {
        $this->assertSame('app_local/img/logo.png', BrandAsset::logoPath());
        $this->assertSame('app_local/img/favicon.png', BrandAsset::faviconRelative());
        $this->assertStringContainsString('/app_local/img/logo.png', BrandAsset::logoUrl());
        $this->assertStringContainsString('/app_local/img/favicon.png', BrandAsset::faviconUrl());

        // Aset fisik ada & favicon turunan tersedia (dibuat via GD dari logo.png)
        $this->assertFileExists(public_path(BrandAsset::DEFAULT_LOGO));
        $this->assertFileExists(public_path(BrandAsset::faviconRelative()));

        // Favicon adalah PNG persegi (square)
        $info = getimagesize(public_path(BrandAsset::faviconRelative()));
        $this->assertSame('image/png', $info['mime']);
        $this->assertSame($info[0], $info[1], 'Favicon harus persegi');
    }

    public function test_logo_data_uri_decodes_to_png(): void
    {
        $dataUri = BrandAsset::logoDataUri();
        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);

        $binary = base64_decode(substr($dataUri, strlen('data:image/png;base64,')), true);
        $this->assertNotFalse($binary);
        $info = getimagesizefromstring($binary);
        $this->assertSame('image/png', $info['mime']);
    }

    public function test_favicon_regenerated_when_logo_is_newer(): void
    {
        $favicon = public_path(BrandAsset::faviconRelative());
        $logo = public_path(BrandAsset::DEFAULT_LOGO);

        // Simulasi favicon basi: mundurkan mtime favicon di bawah logo
        touch($favicon, filemtime($logo) - 3600);
        $before = filemtime($favicon);

        BrandAsset::ensureFavicon();

        $this->assertGreaterThan($before, filemtime($favicon), 'Favicon dibuat ulang saat logo lebih baru');
        $this->assertFileExists($favicon);
    }

    public function test_login_page_uses_logo_and_favicon(): void
    {
        $response = $this->get('/login');
        $response->assertOk();
        $response->assertSee('/app_local/img/logo.png', false);
        $response->assertSee('/app_local/img/favicon.png', false);
        // SVG demo template tidak lagi dipakai
        $response->assertDontSee('paint0_linear_2989_100980', false);
    }

    public function test_home_page_navbar_uses_logo_and_favicon(): void
    {
        $user = User::whereHas('roles', fn ($q) => $q->where('name', 'SUPERADMIN'))->first();

        $response = $this->actingAs($user)->get('/');
        $response->assertOk();
        $response->assertSee('/app_local/img/logo.png', false);
        $response->assertSee('/app_local/img/favicon.png', false);
        $response->assertDontSee('paint0_linear_2989_100980', false);
    }

    public function test_public_verification_pages_use_favicon(): void
    {
        $rental = Rental::first();
        $sig = QrCode::contractSignature($rental->rental_id);

        $response = $this->get(route('verify.contract', ['rental' => $rental->rental_id, 't' => $sig]));
        $response->assertOk();
        $response->assertSee('/app_local/img/favicon.png', false);
    }
}
