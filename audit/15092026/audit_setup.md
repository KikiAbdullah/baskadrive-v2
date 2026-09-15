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

### [ ] S-05 — Log Viewer bisa dibuka SEMUA user terautentikasi
- **Bukti:** rute `debug/log-viewer` (routes/web.php:81-83) tanpa `can:debug_view` padahal permission-nya ada. Log Laravel memuat stack trace, absolute path, query, dan apa pun yang pernah di-`Log::` (termasuk pesan error transaksi).
- **Solusi:** `->middleware('can:debug_view')` + pertimbangkan rotasi/exccerpt agar viewer tidak membac seluruh file 256M sekali request.

### [ ] S-06 — Role/Permission resource: aksi TULIS cukup dengan permission VIEW; role sistem tak terlindungi
- **Bukti:** routes/web.php:63-75 — `Route::resource('role', ...)->middleware('can:roles_view')` dan `permission` sama: `store/update/destroy` hanya butuh `*_view` (permission `roles_add/edit/delete` ada di seeder tapi tak terpasang). Role **SUPERADMIN/ADMIN** dapat dihapus/diubah permission-nya oleh siapa pun ber-`roles_view` (termasuk VIEWER-MANAGER level) → lockout/privilege escalation massal. `RoleController::customUpdate` revoke-semua-beri-uang tanpa guard.
- **Solusi:** pisahkan gate per aksi (`can:roles_add` dst. + `permission` equivalents); lindungi role sistem (SUPERADMIN tak boleh diedit/dihapus; tolak hapus role yang masih dipakai user dengan pesan ramah — pola `blockedByRelations`).

### [ ] S-07 — Cookie "sudah 2FA" = user ID polos, umur 1 tahun, tak terikat sesi
- **Bukti:** `TwoFactorController::verifyTwoFactor` — `Cookie::queue(config('2fa.cookie_name'), $user->id, 1 tahun)`; `TwoFactorVerify` lolos bila `user->id == cookie`. Login/logout memang meng-`forget` cookie ✓, tetapi cookie yang tersalin (XSS/backup browser/shared PC) **membypass 2FA selamanya** sampai user logout manual, dan tidak pernah dirotasi saat OTP baru.
- **Solusi:** token random per-verifikasi disimpan hash di DB (kolom `two_factor_token`) + expiry pendek (mis. 8 jam) + rotasi setiap login; jangan simpan identitas langsung di cookie.

### [ ] S-08 — Rantai 2FA tidak aktif di konfigurasi saat ini
- **Bukti:** `.env`: `APP_2FA=false`, `APP_WHATSAPP_API` kosong → middleware langsung `return $next`. Hash OTP hasil perbaikan kemarin tidak terpakai sampai endpoint WA di-setup.
- **Solusi:** checklist deploy: aktifkan `APP_2FA=true`, isi endpoint kredensial; tambahkan fallback driver (email/database) supaya tidak self-lock-out user tanpa `nowa`; pastikan `config('2fa.cookie_name')` ≠ nama kolom yang di-mask.

### [ ] S-09 — UserRequest: ignore email unique SALAH subjek + aturan tidak lengkap
- **Bukti:** PUT `Rule::unique('users')->ignore($this->user)` → meng-ignore **admin yang sedang login**, bukan user yang diedit (`$this->route('user')`) → menyimpan user lain tanpa mengubah email = "sudah dipakai"; POST tidak ada `min:8` untuk password, tidak ada max/regex username, field `role` & `deleted_at_baru` tak divalidasi (undefined-key trap di update).
- **Solusi:** `->ignore($this->route('user') ?? $this->user)`; lengkapi rules; `email` pada update juga sebaiknya me-reset `email_verified_at` bila alamat berubah (model implements MustVerifyEmail tapi status lama ikut).

### [ ] S-10 — Override `update()/destroy()` di UserController masih menelan `ValidationException`
- **Bukti:** UserController.copy custom `update()` (baris ~66+) dan `destroy()` punya `catch (Exception $e)` lama — guard "SUPERADMIN tidak bisa diubah" dilempar sebagai ValidationException lalu tertangkap & diubah jadi pesan generik `"The given data was invalid."` tanpa detail field. (CrudTrait induk sudah di-rethrow benar.)
- **Solusi:** samakan pola: `catch (ValidationException $e) { rollback; throw $e; }` sebelum `catch (Exception)`.

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

FASE B (gerbang internal Setup):
  [ ] S-05 : can:debug_view pada log viewer
  [ ] S-06 : gate tulis roles_*/permissions_* + lindungi role sistem
  [ ] S-10 : rethrow ValidationException di override UserController
  [ ] S-09 : perbaiki UserRequest (ignore route, rules lengkap)
  [ ] S-07 : rotasi & hash token 2FA-cookie, hapus ID polos dari cookie
  [ ] S-08 : checklist enable 2FA + fallback kanal OTP

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
| 🟡 Sedang (S-05..S-10) | 6 — **terbuka** (S-05 log-viewer, S-06 tulis roles/permission, S-07 cookie 2FA, S-08 enable 2FA, S-09 UserRequest ignore, S-10 rethrow) |
| 🟢 Rendah (S-11..S-15) | 5 — **SEMUA FIXED 15/09/2026** (S-12 = koreksi false-positive) |
| **Total terbuka** | **6** (S-05..S-10 — per update 15/09/2026) |
| Item audit 12/09 (2.9) teregresi baik | 2FA hash+expiry ✓, gate aksi finansial ✓ |

*Laporan audit Setup disusun 15/09/2026; temuan yang telah diperbaiki ditandai `[X]` beserta bukti verifikasinya.*
