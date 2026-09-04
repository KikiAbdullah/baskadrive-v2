<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\UserLog;
use Exception;
use Illuminate\Http\Request;
use Storage;

class SystemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    private const DEFAULT_SETTINGS = [
        'company_name' => 'BaskaDrive',
        'company_phone' => '',
        'company_email' => '',
        'company_address' => '',
        'tax_percent' => 11,
        'deposit_default' => 500000,
        'late_hour_charge' => 50000,
        'overdue_grace_minutes' => 60,
        'invoice_due_days' => 7,
        'currency' => 'IDR',
        'timezone' => 'Asia/Jakarta',
    ];

    private function readSettings(): array
    {
        $defaults = self::DEFAULT_SETTINGS;

        if (Storage::exists('settings.json')) {
            $stored = json_decode(Storage::get('settings.json'), true) ?: [];
            return array_merge($defaults, $stored);
        }

        return $defaults;
    }

    // ============================================================
    // PENGATURAN UMUM
    // ============================================================

    public function settings()
    {
        return view('system.settings')->with([
            'title' => 'Pengaturan Umum',
            'subtitle' => 'Konfigurasi Aplikasi',
            'settings' => $this->readSettings(),
        ]);
    }

    public function settingsUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'company_name' => 'required|string|max:255',
                'company_phone' => 'nullable|string|max:50',
                'company_email' => 'nullable|email|max:255',
                'company_address' => 'nullable|string|max:500',
                'tax_percent' => 'required|numeric|min:0|max:100',
                'deposit_default' => 'required|numeric|min:0',
                'late_hour_charge' => 'required|numeric|min:0',
                'overdue_grace_minutes' => 'required|integer|min:0',
                'invoice_due_days' => 'required|integer|min:1',
                'currency' => 'required|string|max:10',
                'timezone' => 'required|string|max:50',
            ]);

            Storage::put('settings.json', json_encode($validated, JSON_PRETTY_PRINT));

            UserLog::create([
                'user_id' => auth()->id(),
                'action' => 'update',
                'menu' => 'system.settings',
                'message' => 'Memperbarui pengaturan umum',
            ]);

            return redirect()->route('system.settings')->withSuccess('Pengaturan berhasil disimpan.');
        } catch (Exception $e) {
            return redirect()->back()->withInput()->withErrors($e->getMessage());
        }
    }

    // ============================================================
    // LOG AKTIVITAS
    // ============================================================

    public function activityLog()
    {
        return view('system.activity-log')->with([
            'title' => 'Log Aktivitas',
            'subtitle' => 'Riwayat Aktivitas Pengguna',
        ]);
    }

    public function activityLogData(Request $request)
    {
        $query = UserLog::query()->with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        return datatables()->of($query)
            ->addColumn('user', fn($l) => $l->user?->name ?? '-')
            ->addColumn('created_at', fn($l) => $l->created_at?->format('d/m/Y H:i:s') ?? '-')
            ->addColumn('action', fn($l) => ucfirst($l->action ?? '-'))
            ->toJson();
    }
}
