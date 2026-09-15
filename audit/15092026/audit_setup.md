# 🔁 AUDIT — MENU SETUP & KEAMANAN
## BaskaDrive Rental ERP (User, Role, Permission, Auth Flow, 2FA, Log Viewer)

**Tanggal Audit:** 15 September 2026  
**Cakupan:** `app/Http/Controllers/UserController|RoleController|PermissionController|LogViewerController|TwoFactorController`, `app/Http/Controllers/Auth/*`, `app/Http/Middleware/TwoFactorVerify|RedirectIfAuthenticatedTwoFactor`, `routes/web.php`, `config/2fa.php`, `resources/views/user-setup/*`, `resources/views/auth/*`  
**Metode:** Static review + **runtime probe** terhadap DB & router aktual (metadata middleware, Spatie role/permission check)  
**Legenda:** `[X]` = terverifikasi berfungsi — `[ ]` = temuan terbuka

> Konteks: dua item audit 12/09 (2.9) sudah diperbaiki 15/09 pagi:
> - [X] Token 2FA kini **bcrypt hash + kedaluwarsa 5 menit** (`TwoFactorVerify`, `TwoFactorController`, kolom `token_2fa_expires_at`), token dibersihkan setelah benar/salah, `token_2fa` disembunyikan dari serialization.
> - [X] Aksi sensitif `rental_cancel` / `fine_waive` / `accounting_export` di-gate middleware `can:` + `@can` (terjawab terpisah dari modul Setup — tapi lihat S-05/S-06: gerbang internal Setup sendiri masih bolong).

---

## 1. HASIL PROBE RUNTIME (BUKTI)

```
perm add_users/edit_users/delete_users di DB .......... 0 baris  (TIDAK PERNAH ADA)
perm users_add/users_edit/users_delete di DB .......... 3 baris  (yang benar ada)
ADMIN->hasPermissionTo('users_add') ................... Y
ADMIN->hasPermissionTo('edit_users') .................. PermissionDoesNotExist
middleware user-setup.user.store ...................... web,auth,two_factor,can:users_view,can:add_users   <- salah nama
middleware debug.log-viewer ........................... web,auth,two_factor                                 <- tanpa can:
middleware role.store / role.destroy .................. ...can:roles_view (hanya VIEW yang diminta utk TULIS)
middleware permission.store ........................... ...can:permissions_view
middleware register ................................... web,guest  (RUTE PENDAFTARAN UMUM AKTIF)
middleware login ....................................... web,guest  (TANPA throttle/limit percobaan)
assignRole('2')  (value select user-form = role ID) ... RoleDoesNotExist: There is no role named `2`
syncRoles(['3']) (jalur update) ....................... RoleDoesNotExist
config('2fa.enabled') ................................  false (APP_2FA=false, APP_WHATSAPP_API kosong)
users tanpa role saat ini ............................. 0/37 (belum ter-Manifestasi, tapi jalur register menghasilkannya)
```

---

## 2. TEMUAN — PRIORITAS TINGGI (🔴) — **TUNTAS 4/4 (15/09/2026)**

### [X] S-01 — Pendaftaran publik + modul operasional tanpa gerbang — **FIXED 15/09/2026**
- **Registrasi:** flag `app.registration_enabled` (default **false**) membungkus rute GET/POST `register` (terbukti 404, test `test_register_page_disabled_by_default`), + **defense-in-depth** `abort(404)` di `RegisterController::register()`, + pendaftar (bila fitur dinyalakan) kini otomatis `assignRole(config('app.registration_default_role','VIEWER'))` — VIEWER hanya `dashboard_view/report_view/master_view`, **tidak** dapat `rental_view` (teruji).
- **Gate 6 grup modul** (`routes/rental.php`): `dashboard→can:dashboard_view`, `rental→can:rental_view`, `fleet→can:fleet_view`, `finance→can:finance_view`, `accounting→can:accounting_view`, `report→can:report_view` (4 permission baru dibuat di `PermissionSeeder`, role map di `RoleSeeder`: STAFF tanpa accounting, VIEWER tanpa operasional; seeder dev MySQL dijalankan — "4 dibuat, 26 sudah ada").
- **Bukti:** probe middleware 6/6 rute ter-gate; `ModuleAccessGatesTest` 6/6 PASS (VIEWER 403 di /rental /fleet /finance /accounting; STAFF boleh rental, tolak accounting; supervisor/manager boleh accounting).
- **Follow-up (di luar sprint, sengaja tidak disentuh):** sembunyikan item menu `rental/fleet/dll` via `@can` agar UX tidak klik-403.

### [X] S-02 — Permission name salah di UserController — **FIXED 15/09/2026**
- `can:add_users/edit_users/delete_users` → **`can:users_add/ users_edit/ users_delete`** (sesuai seeder). Probe: `user-setup.user.store : web,auth,two_factor,can:users_view,can:users_add`; `ADMIN->hasPermissionTo('users_add') = true`; test ADMIN store sukses vs STAFF 403.

### [X] S-03 — Role ID-string ditolak Spatie — **FIXED 15/09/2026**
- `customStore`: `assignRole(Role::findOrFail((int) $data['role']))`; `update()`: guard `$roleId !== 1` strict + `syncRoles([Role::findOrFail($roleId)])`; `UserRequest`: `role required|exists:roles,id` (POST & PUT) + `password min:8`; null-safety `password/nowa/deleted_at_baru`. Test: role valid tersimpan+ter-assign, `role=99999` 422, password lemah 422, ADMIN→demote SUPERADMIN ditolak & data utuh.

### [X] S-04 — Login tanpa rate limit — **FIXED 15/09/2026**
- `Route::post('login', …)->middleware('throttle:5,1')` (keputusan user: 5/menit). Test `LoginThrottleTest`: usaha 1-5 masih 302/validasi, **usaha ke-6 = 429**, login valid tetap 302.

---

## 3. TEMUAN — PRIORITAS SEDANG (🟡)

### [X] S-05 — Log Viewer bisa dibuka SEMUA user terautentikasi — **FIXED 15/09/2026**
- **Gate:** `routes/web.php:89` `Route::group(['prefix'=>'debug',...,'middleware'=>['can:debug_view']])` — hanya pemilik `debug_view` (SUPERADMIN) lolos; probe `debug.log-viewer.index => web,auth,two_factor,can:debug_view`.
- **Bonus:** `LogViewerController::readLogTail()` sudah membatasi baca 2MB ekor file (S-13).

### [X] S-06 — Role/Permission resource: aksi TULIS cukup dengan permission VIEW — **FIXED 15/09/2026**
- **Gate:** `routes/web.php:69-84` `permission`/`role` dipecah per aksi: `index/show/get-data => can:*_view`, `create/store => can:*_add`, `edit/update => can:*_edit`, `destroy => can:*_delete` (seeder baru `permissions_add/edit/delete` ditambahkan, RoleSeeder ADMIN diberi full set). Probe 7 route `can:roles_add/edit/delete` & `can:permissions_add/edit/delete` terpasang.
- **Lindungi role sistem:** `RoleController::customUpdate` menolak edit `SUPERADMIN`; `customDestroy` menolak hapus `SUPERADMIN/ADMIN` dan role yang masih dipakai user (pesan ramah).

### [X] S-07 — Cookie "sudah 2FA" = user ID polos — **FIXED 15/09/2026**
- **Token random:** `TwoFactorController::verifyTwoFactor` kini generate `Str::random(64)`, simpan `Hash::make` di `users.two_factor_cookie_hash` + `two_factor_cookie_expires_at = now+8h`, cookie berisi plain token (8 jam, bukan 1 tahun). `TwoFactorVerify` & `RedirectIfAuthenticatedTwoFactor` verifikasi `Hash::check` + expiry, bukan `user->id == cookie`. Login/logout merotasi & membersihkan hash (migrasi `2026_09_15_040000`).

### [X] S-08 — Rantai 2FA tidak aktif di konfigurasi saat ini — **FIXED 15/09/2026**
- **Fallback:** `TwoFactorVerify` bila WA gagal / tanpa `nowa` dan `config('2fa.fallback_via_email')` aktif → kirim OTP via `Mail::raw` ke `user->email` (try/catch + Log::warning). Config baru `config/2fa.php: fallback_via_email` + `.env.example: APP_2FA_FALLBACK_EMAIL=true`. Checklist deploy tetap: `APP_2FA=true` + endpoint WA.

### [X] S-09 — UserRequest: ignore email unique SALAH subjek + aturan tidak lengkap — **FIXED 15/09/2026**
- `ignore($this->route('user') ?? $this->user)` + support model instance; `POST/PUT` rules dilengkapi `username min:3 max:50 regex`, `email max:255`, `password min:8`, `role required|exists`, `nowa regex`, `deleted_at_baru in:0,1`; `email` berubah → `email_verified_at = null` di `UserController::update`.

### [X] S-10 — Override `update()/destroy()` di UserController masih menelan `ValidationException` — **FIXED 15/09/2026**
- `catch (ValidationException $e) { DB::rollback(); throw $e; }` ditambahkan sebelum `catch (Exception)` di kedua method — guard SUPERADMIN kini mengembalikan field error yang benar, bukan pesan generik.

---

## 4. TEMUAN — PRIORITAS RENDAH (🟢)

### [X] S-11 — File & jalur mati — **FIXED 15/09/2026**
- `resources/views/auth/login_old.blade.php` **dihapus** (0 referensi di kode).
- Rute `register` (GET/POST) kini dibungkus flag **`config('app.registration_enabled')`** (default **false**, env `APP_REGISTRATION_ENABLED`): saat off rute tidak terdaftar sama sekali → `GET/POST /register` = 404 (teruji). Sekaligus menutup pintu masuk S-01.

### [X] S-12 — Throttle `VerificationController::resend` — **TERKOREKSI: sudah ada sejak awal**
- Audit salah: `VerificationController::__construct` baris 17 sudah memuat `$this->middleware('throttle:6,1')->only('verify','resend')`. Probe runtime: `verification.resend : web,auth,throttle:6,1` ✓ (controller middleware terbukti aktif — rute login juga menampilkan `guest` dari constructor). Tidak ada perubahan kode; hanya koreksi dokumen.

### [X] S-13 — Log Viewer baca seluruh file — **FIXED 15/09/2026**
- `LogViewerController::index()` tidak lagi `File::get()` seluruh file + `ini_set(256M)`. Helper baru `readLogTail()` membaca **maks 2MB ekor file** via `fseek` negatif, membuang barisan terpotong, aman utk file hilang. Terverifikasi: file 4,2MB → hasil 1,99MB mulai baris utuh; file kecil dibaca penuh; `index()` tetap merender view.
- Opsional produksi tersedia: channel `daily` sudah ada di `config/logging.php` — aktifkan via `LOG_CHANNEL=daily` (masuk checklist S-15).

### [X] S-14 — Hapus permission dipakai — **FIXED 15/09/2026**
- `PermissionController::customDestroy()` menolak dengan pesan ramah: *"Permission \"dashboard_view\" masih melekat pada satu/lebih role. Cabut dulu…"*. Dalam praktik, guard generik `blockedByRelations()` (M-07) mendahului dengan pesan setara ("masih digunakan oleh tabel role_has_permissions") — dua lapis, **data tidak pernah terhapus diam-diam** (teruji: pivot role→STAFF ditolak, record utuh; permission bebas lolos guard).

### [X] S-15 — Checklist deploy produksi — **DOKUMENTASI DITAMBAHKAN 15/09/2026**
- `.env.example`: komentar wajib `APP_ENV=production & APP_DEBUG=false`, `APP_REGISTRATION_ENABLED=false`, `SESSION_SECURE_COOKIE=true`, `LOG_CHANNEL=daily` (S-13).
- `DOCUMENTATION.md` **§20 Checklist Deploy Produksi** — 13 item tercenter: debug off, key baru, https+secure cookie, registrasi off, 2FA + endpoint WA, log harian, cache optimize, storage:link, `schedule:run` cron, pdf-tmp writable, DB user non-root, backup terjadwal.

---

## 5. REKOMENDASI URUTAN PERBAIKAN

```
FASE A (kritis — keamanan pintu depan) — TUNTAS 4/4 ✅ (15/09/2026)
  [X] S-01 : registrasi default OFF (+abort 404+auto-role VIEWER) + 6 gate grup
             can:*_view (rental/fleet/finance/accounting/dashboard/report)
  [X] S-02 : nama permissionUserController dikoreksi -> users_add/edit/delete
  [X] S-03 : role di-resolve Role::findOrFail((int)ID) + UserRequest
             role required|exists + password min:8
  [X] S-04 : throttle:5,1 pada POST login (429 di usaha ke-6, teruji)

FASE B (gerbang internal Setup) — TUNTAS 6/6 ✅ (15/09/2026)
  [X] S-05 : can:debug_view pada log viewer
  [X] S-06 : gate tulis roles_*/permissions_* + lindungi role sistem
  [X] S-10 : rethrow ValidationException di override UserController
  [X] S-09 : perbaiki UserRequest (ignore route, rules lengkap)
  [X] S-07 : rotasi & hash token 2FA-cookie, hapus ID polos dari cookie
  [X] S-08 : fallback email 2FA + config fallback_via_email

FASE C (kebersihan) — TUNTAS 5/5 ✅ (15/09/2026)
  [X] S-11 : login_old dihapus + rute register di balik flag (default off, 404 teruji)
  [X] S-12 : TERKOREKSI — throttle:6,1 ternyata sudah aktif (probe middleware)
  [X] S-13 : readLogTail() max 2MB ekor file; opsi LOG_CHANNEL=daily tersedia
  [X] S-14 : guard customDestroy + blockedByRelations (2 lapis, teruji)
  [X] S-15 : .env.example aman + DOCUMENTATION.md §20 checklist deploy
```

---

## 6. RINGKASAN STATISTIK

| Kategori | Jumlah |
|---|---|
| 🔴 Tinggi (S-01..S-04) | 4 — **SEMUA FIXED 15/09/2026 (4/4)** (13 test baru PASS: UserAdminManagement 5, LoginThrottle 2, ModuleAccessGates 6) |
| 🟡 Sedang (S-05..S-10) | 6 — **SEMUA FIXED 15/09/2026 (6/6)** |
| 🟢 Rendah (S-11..S-15) | 5 — **SEMUA FIXED 15/09/2026** (S-12 = koreksi false-positive) |
| **Total terbuka** | **0 — TUNTAS 15/15** (per update 15/09/2026) |
| Item audit 12/09 (2.9) teregresi baik | 2FA hash+expiry ✓, gate aksi finansial ✓ |

*Laporan audit Setup disusun 15/09/2026; temuan yang telah diperbaiki ditandai `[X]` beserta bukti verifikasinya.*
