# 🖥️ AUDIT — MENU SISTEM & ADMINISTRASI
## BaskaDrive Rental ERP (Pengaturan Umum, Log Aktivitas, Backup Database)

**Tanggal Audit:** 16 September 2026  
**Cakupan:** `app/Http/Controllers/Rental/SystemController.php` (214 baris), `app/Support/AppSettings.php` (158 baris), `app/Models/AppSetting.php`, `app/Models/UserLog.php`, `routes/rental.php:353-362`, `resources/views/system/settings.blade.php` (564 baris), `resources/views/system/activity-log.blade.php` (116 baris), migrasi `2026_09_03_023615` (user_logs) & `2026_09_04_141852` (app_settings), `bootstrap/app.php:33-42` (scheduler)  
**Metode:** Static review + probe runtime (tinker: isi tabel lokasi/settings) + verifikasi middleware/route + uji render Blade  
**Legenda:** `[X]` = sudah diperbaiki — `[ ]` = temuan terbuka (belum diperbaiki)

> Audit lanjutan setelah M-01..M-20 (master), S-01..S-10 (setup/keamanan), dan FASE 1–3 + polish wizard sewa 16/09/2026. **Tuntas 100% — tersinkron dengan kode 16/09/2026** (`php artisan test` → 34/34 PASS, migrasi FK terterapkan, `system:backup` teruji 44 tabel).

---

## DAFTAR ISI

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Inventaris Rute & View](#2-inventaris-rute--view)
3. [Mekanisme Penyimpanan Pengaturan](#3-mekanisme-penyimpanan-pengaturan)
4. [Permission & Keamanan](#4-permission--keamanan)
5. [Backup Database](#5-backup-database)
6. [Validasi & Konsistensi Data](#6-validasi--konsistensi-data)
7. [UI/UX & Tech Debt](#7-uiux--tech-debt)
8. [Integrasi Lintas Modul](#8-integrasi-lintas-modul)
9. [Matriks Prioritas Action Plan](#9-matriks-prioritas-action-plan)

---

## 1. RINGKASAN EKSEKUTIF

Menu Sistem terdiri dari 3 fungsi: **Pengaturan Umum** (24 key operasional), **Log Aktivitas** (UserLog + DataTables), dan **Backup Database** (SQL dump manual). Fondasi penyimpanannya sehat (DB + cache `rememberForever` + koersi tipe). Namun ada **2 temuan 🔴 yang membuat 2 dari 5 endpoint tidak dapat dipakai / tidak aman**:

| Kategori | Status |
|---|---|
| 🔴 Tinggi (akses tanpa izin / fatal error) | **SELESAI — 2/2 (16/09/2026)** |
| 🟡 Sedang (backup GET, validasi) | **SELESAI — 2/2 (16/09/2026)** |
| 🟢 Rendah (UX, konsistensi, tech-debt) | **SELESAI — 3/3 (16/09/2026)** |

---

## 2. INVENTARIS RUTE & VIEW

Grup `routes/rental.php:356` `['prefix' => 'system', 'as' => 'system.']` — inherit `web,auth,two_factor` (via `web.php:62-63,114`); gate `can:` per-route ditambahkan 16/09/2026 (SYS-01):

| HTTP | URI | Action (`SystemController`) | Name | Middleware efektif |
|---|---|---|---|---|
| GET | `/system/settings` | `settings` L22 | `system.settings` | `web,auth,two_factor` + `can:settings_view` |
| PUT | `/system/settings` | `settingsUpdate` L34 | `system.settings.update` | `web,auth,two_factor` + `can:settings_edit` |
| GET | `/system/activity-log` | `activityLog` L127 | `system.activity-log` | `web,auth,two_factor` + `can:logs_view` |
| GET | `/system/activity-log/get-data` | `activityLogData` L136 | `system.activity-log.data` | `web,auth,two_factor` + `can:logs_view` |
| GET | `/system/backup` | `backup` L160 | `system.backup` | `web,auth,two_factor` + `can:settings_edit` |

| View | Controller | Fungsi |
|---|---|---|
| `system/settings` (564 baris) | `settings` L22, `settingsUpdate` L34 | Form 5 tab (Profil / PPN & Deposit / Operasional / Dokumen / Aplikasi), upload logo + checkbox `remove_logo` (L222–230), kartu backup di-gate `@can('settings_edit')` (L496–502) |
| `system/activity-log` (116 baris) | `activityLog` L127, `activityLogData` L136 | DataTables server-side, filter user + aksi (create/update/delete/login/logout) |
| Menu sidebar `layouts/menu.blade.php:225-244` | — | Induk di-gate `@canany settings_view/logs_view`, anak per `@can` (SYS-01, 16/09) |

Scheduler terkait (`bootstrap/app.php:33-42`): `app:cleanup-temp` (02:00), `fleet:check-maintenance` (06:30), `rental:send-reminders` (08:00), `rentals:mark-overdue` (10 menit), `queue:work` (1 menit). **Tidak ada backup otomatis terjadwal** — backup hanya manual via UI.

---

## 3. MEKANISME PENYIMPANAN PENGATURAN

Sehat dan layak dipertahankan. `app/Support/AppSettings.php` (158 baris): DB (`AppSetting`, tabel `app_settings`: `key` unique, `value` text nullable) + `Cache::rememberForever('app_settings_all')` (L71–75), flush di `set/setMany/forget` (L111,123,144), koersi tipe ikut tipe default (L78–92), `stringify()` bool→`'true'/'false'` (L126–133).

**24 key** (`defaults()` L23–61): `company_name, company_tagline, company_phone, company_email, company_address, company_logo, invoice_footer_note, contract_terms, tax_enabled, tax_percent, tax_label, deposit_enabled, deposit_default, deposit_required, late_hour_charge, overdue_grace_minutes, invoice_due_days, young_driver_age, young_driver_fee_default, driver_fee_default, currency, currency_symbol, timezone, date_format`.

Konsumen terverifikasi: `RentalController` (11 titik: tarif sopir, grace overdue, dsb.), `FinanceController:109`, `VehicleController:188`, `ReportController:376`, `AccountingService:91` (`accounting_closing_date`), `MarkOverdueRentals:22`, view cetak (`rental/print`, `finance/invoice/print`, `finance/payment/receipt`, `mail/invoice`, `report/pdf`, `barcode-pdf`), wizard sewa (`_step-3:107-177`, `_step-4:148`, `edit:134-136`).

---

## 4. PERMISSION & KEAMANAN

### [X] SYS-01 — Rute & menu Sistem tanpa gate permission — **FIXED 16/09/2026**
- **Route** (`rental.php:356-362`): `GET settings → can:settings_view`, `PUT settings → can:settings_edit`, `activity-log* → can:logs_view` (catatan: dua rute activity-log ternyata sudah ber-gate sebelumnya; yang ditambahkan adalah kedua rute settings), `backup` tetap `can:settings_edit`.
- **Menu** (`menu.blade.php:225-244`): induk dibungkus `@canany(['settings_view','logs_view'])`, anak `Pengaturan Umum → @can('settings_view')`, `Log Aktivitas → @can('logs_view')` — konsisten dengan 6 modul lain.
- **Bukti probe:** `system.settings => web,auth,can:settings_view`, `system.settings.update => web,auth,can:settings_edit`, `activity-log(*) => web,auth,can:logs_view`, `backup => web,auth,can:settings_edit`; `ADMIN = Y/Y/Y`, `STAFF/VIEWER = -/-/-` → 403 untuk non-ADMIN.

### [X] SYS-02 — Import `DB` hilang → backup & tabel log fatal — **FIXED 16/09/2026**
- Tambah `use Illuminate\Support\Facades\DB;` (`SystemController.php:10`). `datatables()` tidak perlu import (helper global yajra, dipakai 15+ controller lain: `Rental:774`, `Finance:61`, dsb.).
- **Bukti:** `php -l` bersih, `view:cache` sukses; class kini termuat tanpa "Class not found".

---

## 5. BACKUP DATABASE

### [X] SYS-03 — Backup via GET: bisa ter-prefetch, menulis log, memuat rahasia — **FIXED 16/09/2026**
- Rute `POST /backup` + `throttle:3,1` (`rental.php:361`); view memakai form POST + `@csrf` + `confirm()` (`settings.blade.php:497-505`).
- Log ditulis via `app()->terminating()` **setelah** stream terkirim (`SystemController:192-200`) — unduhan batal tak tercatat sukses.
- **Bonus temuan saat verifikasi:** dump tak pernah jalan — `chunk()` wajib `orderBy` (`RuntimeException`) → diperbaiki dengan order kolom pertama tiap tabel. Bukti: `system:backup` → 44 tabel, 8.878 INSERT, 2,8 MB, diakhiri `SET FOREIGN_KEY_CHECKS=1`.
- Keputusan: isi dump tetap penuh (fidelitas restore); izin tetap `settings_edit` (alih-alih SUPERADMIN-only) agar alur ADMIN tak rusak.

---

## 6. VALIDASI & KONSISTENSI DATA

### [X] SYS-04 — Celah validasi `settingsUpdate` — **FIXED 16/09/2026**
- `timezone` kini `in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura` — persis 3 opsi view (`settings.blade.php:467-471`); `currency/max:10` & `currency_symbol/max:5` sudah memadai.
- `catch (ValidationException) { throw $e; }` ditambahkan sebelum `catch (Exception)` — error field kembali ke error-bag standar (pola S-10).
- `accounting_closing_date` tetap `nullable|date` (backdate adalah guna sah tutup buku) + **konfirmasi JS** bila terisi saat simpan (`settings.blade.php` handler `#btnSaveSettings`).
- `date_format`: tak dipakai kode/view mana pun — dipertahankan sebagai cadangan (risiko nol), bukan dihapus.
- `setMany()` tanpa allowlist dipertahankan (dipakai `settingsUpdate` dengan `$validated` hasil validator = allowlist implisit).

---

## 7. UI/UX & TECH DEBT

### [X] SYS-05 — Aksi destruktif tanpa konfirmasi + balap upload-hapus logo — **FIXED 16/09/2026**
- Tombol simpan jadi `type=button` + konfirmasi JS (peringatan khusus bila tutup buku terisi).
- Balap logo: upload **diabaikan** bila `remove_logo` dicentang (`SystemController:73-76`) — tak ada lagi file yatim.

### [X] SYS-06 — Inkonsistensi kecil data & kode mati — **FIXED 16/09/2026**
- `UserLog::normalizeAction()` + mutator `setActionAttribute` (`UserLog.php:20-46`): semua penulisan dinormalisasi ke key lowercase Inggris (`Penambahan→create`, `Login→login`, …); filter memakai `LOWER(action)` + nilai ternormalisasi (`SystemController:146`) sehingga cocok untuk data lama maupun baru.
- Filter rentang tanggal `date_from/date_to` (`whereDate`) + opsi `export` di dropdown; input flatpickr di view.
- `user_logs.user_id`: migrasi `2026_09_16_000001` — null-kan yatim, jadikan nullable + FK `nullOnDelete` (MySQL only, pola guard driver repo ini).
- Dead code `AppSetting::getTypedValueAttribute()` dihapus; `UserLogSeeder` menulis lowercase langsung.
- `migrateLegacySettingsFile()` tiap GET dipertahankan (hanya `Storage::exists`, murah) — keputusan sadar.

### [X] SYS-07 — Tanpa seeder & backup otomatis — **FIXED 16/09/2026**
- `SettingsSeeder` (idempoten: hanya isi key yang belum ada) + terdaftar di `DatabaseSeeder:27`. Bukti: `db:seed --class=SettingsSeeder` OK.
- Command `system:backup --keep=14` memakai ulang `SystemController::backupTableList/writeBackupDump`, simpan ke `storage/app/backups`, prune retensi, catat UserLog; terjadwal harian 01:30 (`bootstrap/app.php`).
- Bukti: 44 tabel, 8.878 INSERT, 2,8 MB; `php artisan test` 34/34 PASS.

---

## 8. INTEGRASI LINTAS MODUL

- Perubahan `tax_percent/tax_label/deposit_*` langsung memengaruhi default wizard sewa (`_step-3:107-177`), kalkulasi preview (`calculate-total`), dan cetakan (`rental/print`, `finance/invoice/print`, `receipt`, `mail/invoice`) — alasan tambahan mengunci `PUT settings` ke `settings_edit` (SYS-01).
- `overdue_grace_minutes` dikonsumsi `rentals:mark-overdue` (10 menit); `accounting_closing_date` mengunci periode di `AccountingService:91`.
- `UserLog` ditulis oleh `settingsUpdate` (L91) dan `backup` (L172); format `action/menu/message` konsisten dengan seeder (`UserLogSeeder`, `RentalErpCompleteSeeder:1368,1384-1385`).

---

## 9. MATRIKS PRIORITAS ACTION PLAN

| ID | Temuan | Prioritas | Perkiraan | Status |
|---|---|---|---|---|
| SYS-01 | Gate `can:` rute + menu Sistem | 🔴 Tinggi | Kecil (4 route + 1 menu) | [X] Selesai 16/09 |
| SYS-02 | Import `DB`/DataTables + uji unduh & tabel log | 🔴 Tinggi | Kecil | [X] Selesai 16/09 |
| SYS-03 | Backup → POST + throttle + konfirmasi (+masking) | 🟡 Sedang | Sedang | [X] Selesai 16/09 |
| SYS-04 | Validasi timezone/currency/closing-date + error-bag | 🟡 Sedang | Kecil–Sedang | [X] Selesai 16/09 |
| SYS-05 | Konfirmasi simpan/hapus + balap logo | 🟢 Rendah | Kecil | [X] Selesai 16/09 |
| SYS-06 | Normalisasi casing log, filter tanggal, FK, dead code | 🟢 Rendah | Kecil | [X] Selesai 16/09 |
| SYS-07 | Seeder defaults + keputusan backup terjadwal/retensi | 🟢 Rendah | Kecil (keputusan) | [X] Selesai 16/09 |
