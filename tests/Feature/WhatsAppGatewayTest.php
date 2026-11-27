<?php

namespace Tests\Feature;

use App\Support\AppSettings;
use App\Support\WhatsAppGateway;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integrasi gateway WhatsApp internal (Baileys utama + whatsapp-web.js
 * cadangan): saklar on/off per provider, halaman admin, dan pengiriman
 * dengan failover. Gateway Node tidak perlu berjalan — HTTP di-fake.
 */
class WhatsAppGatewayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['2fa.enabled' => false]);
        config(['whatsapp.gateway_url' => 'http://127.0.0.1:3001']);
        config(['whatsapp.gateway_token' => 'test-token']);
        $this->seed(DatabaseSeeder::class);
        $this->travelTo(now()->parse('2026-09-17 12:00:00'));
    }

    protected function admin()
    {
        return \App\Models\User::where('username', 'admin')->firstOrFail();
    }

    public function test_provider_toggles_are_independent_and_persisted(): void
    {
        // Default: Baileys on, wwebjs off
        $this->assertTrue(WhatsAppGateway::isEnabled('baileys'));
        $this->assertFalse(WhatsAppGateway::isEnabled('wwebjs'));

        WhatsAppGateway::setEnabled('wwebjs', true);
        $this->assertTrue(AppSettings::get('whatsapp_wwebjs_enabled'));
        $this->assertTrue(WhatsAppGateway::isEnabled('wwebjs'));
        $this->assertTrue(WhatsAppGateway::isEnabled('baileys'), 'Baileys tidak boleh ikut berubah.');

        WhatsAppGateway::setEnabled('baileys', false);
        $this->assertFalse(WhatsAppGateway::isEnabled('baileys'));
        $this->assertTrue(WhatsAppGateway::isEnabled('wwebjs'), 'wwebjs tidak boleh ikut berubah.');
    }

    public function test_active_providers_follow_priority_and_switches(): void
    {
        // Utama baileys
        $this->assertSame(['baileys'], WhatsAppGateway::activeProviders());

        // Keduanya menyala → utama dulu
        WhatsAppGateway::setEnabled('wwebjs', true);
        $this->assertSame(['baileys', 'wwebjs'], WhatsAppGateway::activeProviders());

        // Prioritas pindah ke wwebjs
        AppSettings::set('whatsapp_provider', 'wwebjs');
        $this->assertSame(['wwebjs', 'baileys'], WhatsAppGateway::activeProviders());

        // Utama mati → hanya cadangan
        WhatsAppGateway::setEnabled('wwebjs', false);
        AppSettings::set('whatsapp_provider', 'baileys');
        WhatsAppGateway::setEnabled('baileys', false);
        $this->assertSame([], WhatsAppGateway::activeProviders());
    }

    public function test_send_text_succeeds_via_primary_provider(): void
    {
        Http::fake([
            '127.0.0.1:3001/send' => Http::response(['ok' => true], 200),
        ]);

        $result = WhatsAppGateway::sendText('628123456789', 'Halo');

        $this->assertTrue($result['ok']);
        $this->assertSame('baileys', $result['provider']);

        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:3001/send'
                && $request->header('X-Gateway-Token') === ['test-token']
                && $request['provider'] === 'baileys'
                && $request['number'] === '628123456789'
                && $request['message'] === 'Halo';
        });
    }

    public function test_send_text_fails_over_to_backup_when_primary_not_connected(): void
    {
        WhatsAppGateway::setEnabled('wwebjs', true);

        Http::fake([
            '127.0.0.1:3001/send' => Http::sequence()
                ->push(['ok' => false, 'code' => 'PROVIDER_NOT_CONNECTED'], 409) // baileys
                ->push(['ok' => true], 200), // wwebjs
        ]);

        $result = WhatsAppGateway::sendText('08123456789', 'Tes failover');

        $this->assertTrue($result['ok']);
        $this->assertSame('wwebjs', $result['provider']);
    }

    public function test_send_text_reports_error_when_gateway_down(): void
    {
        Http::fake(fn () => throw new \Exception('connection refused'));

        $result = WhatsAppGateway::sendText('628123456789', 'Tes');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('tidak tersedia', $result['error']);
    }

    public function test_status_maps_gateway_response_per_provider(): void
    {
        Http::fake([
            '127.0.0.1:3001/status' => Http::response([
                'baileys' => ['enabled' => true, 'connected' => true, 'user' => ['id' => '628123@s.whatsapp.net'], 'messages_sent' => 7],
                'wwebjs' => ['enabled' => true, 'connected' => false, 'qr_ready' => true, 'qr' => 'data:image/png;base64,AAA'],
            ], 200),
        ]);

        $status = WhatsAppGateway::status();

        $this->assertTrue($status['baileys']['connected']);
        $this->assertSame('628123@s.whatsapp.net', $status['baileys']['user']['id']);
        $this->assertSame(7, $status['baileys']['messages_sent']);
        $this->assertTrue($status['wwebjs']['qr_ready']);
        $this->assertFalse($status['wwebjs']['connected']);
    }

    public function test_admin_page_requires_settings_edit_permission(): void
    {
        $this->actingAs($this->admin())->get(route('system.whatsapp'))->assertOk();

        $staff = \App\Models\User::where('username', 'staff')->firstOrFail();
        $this->actingAs($staff)->get(route('system.whatsapp'))->assertForbidden();
    }

    public function test_admin_can_toggle_provider_and_gateway_state_is_synced(): void
    {
        Http::fake();

        $this->actingAs($this->admin())
            ->postJson(route('system.whatsapp.toggle'), ['provider' => 'wwebjs', 'enabled' => true])
            ->assertOk()
            ->assertJson(['status' => true]);

        $this->assertTrue(WhatsAppGateway::isEnabled('wwebjs'));

        // Saklar disinkronkan ke gateway (/enable) agar sesi dimulai dan
        // state bertahan saat gateway direstart.
        Http::assertSent(fn ($request) => str_contains($request->url(), '/enable')
            && $request['provider'] === 'wwebjs'
            && $request['on'] === true);

        $this->actingAs($this->admin())
            ->postJson(route('system.whatsapp.toggle'), ['provider' => 'wwebjs', 'enabled' => false])
            ->assertOk();

        $this->assertFalse(WhatsAppGateway::isEnabled('wwebjs'));

        Http::assertSent(fn ($request) => str_contains($request->url(), '/enable')
            && $request['provider'] === 'wwebjs'
            && $request['on'] === false);
    }

    public function test_toggle_validates_provider_and_enabled_types(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('system.whatsapp.toggle'), ['provider' => 'telegram', 'enabled' => true])
            ->assertStatus(422);

        $this->actingAs($this->admin())
            ->postJson(route('system.whatsapp.toggle'), ['provider' => 'baileys', 'enabled' => 'yes'])
            ->assertStatus(422);
    }

    public function test_test_send_endpoint_sends_via_gateway(): void
    {
        Http::fake([
            '127.0.0.1:3001/send' => Http::response(['ok' => true], 200),
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('system.whatsapp.test-send'), [
                'phone' => '08123456789',
                'message' => 'Tes dari ERP',
            ])
            ->assertOk()
            ->assertJson(['status' => true]);
    }
}
