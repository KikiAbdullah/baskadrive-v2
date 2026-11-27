<?php

namespace App\Http\Controllers\Rental;

use App\Models\UserLog;
use App\Support\AppSettings;
use App\Support\WhatsAppGateway;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * Halaman admin Gateway WhatsApp (Sistem → WhatsApp Gateway).
 *
 * Mengelola gateway Node internal: saklar on/off per provider (Baileys
 * utama, whatsapp-web.js cadangan), scan QR pairing multi-device, kontrol
 * sesi (mulai/stop/logout), dan uji pengiriman.
 */
class WhatsAppGatewayController extends Controller
{
    // Base Illuminate\Routing\Controller: method index() bebas dipakai
    // (base App\Http\Controllers\Controller memakai CrudTrait dengan
    // signature index/show/store abstrak untuk CRUD web).
    public function __construct()
    {
        $this->middleware(['auth', 'can:settings_edit']);
    }

    public function index()
    {
        return view('system.whatsapp')->with([
            'title' => 'Gateway WhatsApp',
            'subtitle' => 'Baileys (utama) & WhatsApp Web JS (cadangan) — multi-device',
            'providers' => WhatsAppGateway::status(),
            'activeProvider' => AppSettings::get('whatsapp_provider', config('whatsapp.default_provider', 'baileys')),
            'gatewayConfigured' => trim((string) config('whatsapp.gateway_url')) !== '',
            'legacyConfigured' => trim((string) config('whatsapp.endpoint')) !== '',
        ]);
    }

    /** Status live semua provider (dipakai UI untuk polling tiap beberapa detik). */
    public function status()
    {
        return response()->json([
            'status' => true,
            'data' => WhatsAppGateway::status(),
        ]);
    }

    /** Saklar on/off per provider (dipanggil via AJAX). */
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|in:baileys,wwebjs',
            'enabled' => 'required|boolean',
        ]);

        $provider = $data['provider'];

        // Simpan saklar di app_settings lalu sinkronkan ke gateway — gateway
        // memulai/menghentikan sesi dan mengingat state saat direstart.
        WhatsAppGateway::setEnabled($provider, $data['enabled']);
        WhatsAppGateway::syncEnabledOnGateway($provider, $data['enabled']);

        UserLog::create([
            'user_id' => auth()->id(),
            'action' => 'update',
            'menu' => 'system.whatsapp',
            'message' => sprintf(
                '%s provider WhatsApp %s',
                $data['enabled'] ? 'Menyalakan' : 'Mematikan',
                WhatsAppGateway::LABELS[$provider]
            ),
        ]);

        return response()->json([
            'status' => true,
            'msg' => sprintf(
                '%s %s.',
                WhatsAppGateway::LABELS[$provider],
                $data['enabled'] ? 'dinyalakan' : 'dimatikan'
            ),
        ]);
    }

    /** Mulai sesi provider (inisialisasi + tampilkan QR bila perlu pairing). */
    public function start(Request $request, string $provider)
    {
        if (! in_array($provider, WhatsAppGateway::PROVIDERS, true)) {
            abort(404);
        }

        if (! WhatsAppGateway::isEnabled($provider)) {
            return response()->json(['status' => false, 'msg' => 'Provider masih dimatikan. Nyalakan dahulu.']);
        }

        WhatsAppGateway::start($provider);

        return response()->json(['status' => true, 'msg' => 'Sesi '.WhatsAppGateway::LABELS[$provider].' dimulai.']);
    }

    /** Hentikan sesi provider (auth state tetap tersimpan). */
    public function stop(Request $request, string $provider)
    {
        if (! in_array($provider, WhatsAppGateway::PROVIDERS, true)) {
            abort(404);
        }

        WhatsAppGateway::stop($provider);

        return response()->json(['status' => true, 'msg' => 'Sesi '.WhatsAppGateway::LABELS[$provider].' dihentikan.']);
    }

    /** Logout device WhatsApp & hapus auth state tersimpan. */
    public function logout(Request $request, string $provider)
    {
        if (! in_array($provider, WhatsAppGateway::PROVIDERS, true)) {
            abort(404);
        }

        WhatsAppGateway::logout($provider);

        UserLog::create([
            'user_id' => auth()->id(),
            'action' => 'delete',
            'menu' => 'system.whatsapp',
            'message' => 'Logout sesi WhatsApp '.WhatsAppGateway::LABELS[$provider],
        ]);

        return response()->json(['status' => true, 'msg' => 'Sesi '.WhatsAppGateway::LABELS[$provider].' dikeluarkan dari perangkat.']);
    }

    /** Uji kirim pesan ke nomor yang diisi. */
    public function testSend(Request $request)
    {
        $data = $request->validate([
            'phone' => 'required|string|max:25',
            'message' => 'required|string|max:1000',
        ]);

        $result = WhatsAppGateway::sendText($data['phone'], $data['message']);

        if ($result['ok']) {
            $label = WhatsAppGateway::LABELS[$result['provider']] ?? ($result['provider'] ?? '-');

            UserLog::create([
                'user_id' => auth()->id(),
                'action' => 'create',
                'menu' => 'system.whatsapp',
                'message' => 'Uji kirim WhatsApp ke '.$data['phone'].' via '.$label,
            ]);
        }

        $label = WhatsAppGateway::LABELS[$result['provider'] ?? ''] ?? ($result['provider'] ?? '-');

        return response()->json([
            'status' => $result['ok'],
            'msg' => $result['ok']
                ? 'Pesan terkirim via '.$label.'.'
                : ($result['error'] ?? 'Gagal mengirim.'),
        ]);
    }
}
