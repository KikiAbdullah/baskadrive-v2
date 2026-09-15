<?php

namespace App\Http\Controllers\Rental;

use App\Http\Controllers\Controller;
use App\Models\UserLog;
use App\Support\AppSettings;
use Exception;
use Illuminate\Http\Request;

class SystemController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ============================================================
    // PENGATURAN UMUM
    // ============================================================

    public function settings()
    {
        // Migrasi one-time dari settings.json (metode lama) ke database
        $this->migrateLegacySettingsFile();

        return view('system.settings')->with([
            'title' => 'Pengaturan Umum',
            'subtitle' => 'Konfigurasi Aplikasi',
            'settings' => AppSettings::all(),
        ]);
    }

    public function settingsUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                // Profil
                'company_name' => 'required|string|max:255',
                'company_tagline' => 'nullable|string|max:255',
                'company_phone' => 'nullable|string|max:50',
                'company_email' => 'nullable|email|max:255',
                'company_address' => 'nullable|string|max:500',
                // Dokumen
                'invoice_footer_note' => 'nullable|string|max:500',
                'contract_terms' => 'nullable|string|max:2000',
                // Keuangan
                'tax_percent' => 'required|numeric|min:0|max:100',
                'tax_label' => 'required|string|max:20',
                'deposit_default' => 'required|numeric|min:0',
                'late_hour_charge' => 'required|numeric|min:0',
                'overdue_grace_minutes' => 'required|integer|min:0',
                'invoice_due_days' => 'required|integer|min:1',
                'young_driver_age' => 'required|integer|min:17|max:30',
                'young_driver_fee_default' => 'required|numeric|min:0',
                'driver_fee_default' => 'required|numeric|min:0',
                // Akuntansi
                'accounting_closing_date' => 'nullable|date',
                // Aplikasi
                'currency' => 'required|string|max:10',
                'currency_symbol' => 'required|string|max:5',
                'timezone' => 'required|string|max:50',
                // Logo
                'company_logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
            ]);

            // Checkbox ON/OFF (hanya terkirim saat checked)
            $validated['tax_enabled'] = $request->boolean('tax_enabled');
            $validated['deposit_enabled'] = $request->boolean('deposit_enabled');
            $validated['deposit_required'] = $request->boolean('deposit_required');

            // Logo upload (hapus lama)
            if ($request->hasFile('company_logo')) {
                AppSettings::deleteLogo();
                $validated['company_logo'] = $request->file('company_logo')->store('branding', 'public');
            } else {
                unset($validated['company_logo']); // jangan overwrite bila tidak upload baru
            }

            // Hapus logo bila diminta
            if ($request->boolean('remove_logo')) {
                AppSettings::deleteLogo();
                $validated['company_logo'] = null;
            }

            // Simpan ke DATABASE
            AppSettings::setMany($validated);

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

    /**
     * Migrasi settings.json (metode lama) ke tabel app_settings.
     * Berjalan sekali: file dihapus setelah berhasil dimigrasi.
     */
    protected function migrateLegacySettingsFile(): void
    {
        if (! \Illuminate\Support\Facades\Storage::exists('settings.json')) {
            return;
        }

        $legacy = json_decode(\Illuminate\Support\Facades\Storage::get('settings.json'), true);

        if (is_array($legacy) && count($legacy)) {
            // Pertahankan hanya key yang dikenal
            $known = array_intersect_key($legacy, AppSettings::defaults());
            AppSettings::setMany($known);
        }

        \Illuminate\Support\Facades\Storage::delete('settings.json');
    }

    // ============================================================
    // LOG AKTIVITAS
    // ============================================================

    public function activityLog()
    {
        return view('system.activity-log')->with([
            'title' => 'Log Aktivitas',
            'subtitle' => 'Riwayat Aktivitas Pengguna',
            'users' => \App\Models\User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function activityLogData(Request $request)
    {
        $query = UserLog::query()->with('user');

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return datatables()->of($query)
            ->addColumn('user', fn($l) => $l->user?->name ?? '-')
            ->addColumn('created_at', fn($l) => $l->created_at?->format('d/m/Y H:i:s') ?? '-')
            ->addColumn('action', fn($l) => ucfirst($l->action ?? '-'))
            ->toJson();
    }

    // ============================================================
    // BACKUP DATABASE DARI UI (audit 2.8)
    // ============================================================

    public function backup()
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $baseTables = collect(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"))
            ->map(fn($t) => current(array_values((array) $t)))
            ->values()
            ->all();

        $filename = 'baskadrive_backup_' . now()->format('Ymd_His') . '.sql';

        UserLog::create([
            'user_id' => auth()->id(),
            'action' => 'export',
            'menu' => 'system.backup',
            'message' => 'Mencadangkan database dari UI (' . count($baseTables) . ' tabel)',
        ]);

        $callback = function () use ($baseTables) {
            $handle = fopen('php://output', 'w');
            $pdo = DB::connection()->getPdo();

            fwrite($handle, "-- BaskaDrive Database Backup\n-- Waktu: " . now()->toDateTimeString() . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            foreach ($baseTables as $table) {
                $create = DB::select("SHOW CREATE TABLE `{$table}`");
                $createSql = $create[0]->{'Create Table'} ?? '';

                fwrite($handle, "-- Struktur tabel {$table}\nDROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n\n");

                $columnNames = null;
                DB::table($table)->chunk(200, function ($rows) use ($handle, $pdo, $table, &$columnNames) {
                    foreach ($rows as $row) {
                        $row = (array) $row;
                        if ($columnNames === null) {
                            $columnNames = array_keys($row);
                        }
                        $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
                        fwrite($handle, 'INSERT INTO `' . $table . '` (`' . implode('`,`', $columnNames) . '`) VALUES (' . implode(',', $vals) . ");\n");
                    }
                });

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'application/sql',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
