{{APP_NAME}} â€” BaskaDrive

BaskaDrive adalah aplikasi manajemen berbasis web yang dibangun di atas **Laravel 13** dengan PHP 8.3. Aplikasi ini menyediakan sistem autentikasi multi-level, manajemen pengguna/peran/hak akses (RBAC), Two-Factor Authentication (2FA) via WhatsApp OTP, dashboard interaktif, serta API berbasis token (Sanctum).

---

## Fitur

### Web App (Sneat Template)
- **Autentikasi** â€” Login via username & password (bcrypt), logout, register, forgot/reset password
- **2FA OTP** â€” OTP dikirim via WhatsApp untuk lapisan keamanan tambahan (opsional, via `APP_2FA`)
- **Dashboard** â€” Ringkasan data dengan grafik Chart.js
- **User Setup** â€” Manajemen Permission, Role, dan User (CRUD lengkap dengan DataTables server-side)
- **Log Viewer** â€” Lihat log aplikasi langsung dari browser (route `/debug/log-viewer`)
- **Error Pages** â€” Halaman error 403, 404, 500, 503 dengan tema Sneat

### API (Sanctum Token)
- `POST /api/auth/login` â€” Login API
- `POST /api/auth/me` â€” User info
- `POST /api/auth/logout` â€” Hapus token
- `GET /api/user` â€” User via token (middleware `auth:sanctum`)

### RBAC (Spatie Permission)
| Role | Permission | Keterangan |
|------|-----------|-----------|
| **SUPERADMIN** | 19 (semua) | Super Administrator â€” akses penuh, tidak bisa dihapus |
| **ADMIN** | 18 | Administrator â€” kelola user, role, permission, logs, settings, report |
| **MANAGER** | 9 | Manager Operasional â€” kelola user (terbatas), lihat logs & report |
| **SUPERVISOR** | 5 | Supervisor â€” lihat user, logs & report |
| **STAFF** | 3 | Staff Operasional â€” akses operasional dasar |
| **VIEWER** | 2 | Viewer â€” lihat dashboard & report |

---

## Persyaratan Sistem

- PHP ^8.3
- Composer ^2.8
- MySQL 8.0+ (atau MariaDB 10.6+)
- Ekstensi PHP: `BCMath`, `Ctype`, `Fileinfo`, `JSON`, `Mbstring`, `OpenSSL`, `PDO`, `Tokenizer`, `XML`, `GD` (untuk Intervention Image)

---

## Instalasi

### 1. Clone & Setup

```bash
git clone <repo-url> baskadrive
cd baskadrive
```

### 2. Environment

```bash
cp .env.example .env
# Sesuaikan konfigurasi database di .env:
#   DB_CONNECTION=mysql
#   DB_HOST=127.0.0.1
#   DB_PORT=3306
#   DB_DATABASE=baskadrive
#   DB_USERNAME=root
#   DB_PASSWORD=
```

### 3. Install Dependencies

```bash
composer install --no-interaction
```

### 4. Generate Key & Storage Link

```bash
php artisan key:generate
php artisan storage:link
```

### 5. Migrasi & Seeder

```bash
php artisan migrate --seed
```

Proses seeding akan:
- Membuat **19 permission** untuk 8 modul
- Membuat **6 role** dengan permission masing-masing
- Membuat **40 user** (37 aktif, 3 soft-deleted)
- Membuat **223 log aktivitas** sampel
- Membuat **2 API token** (superadmin & admin) â€” token ditampilkan di console

### 6. Jalankan Aplikasi

```bash
php artisan serve
# http://localhost:8000
```

### 7. (Opsional) Build Frontend

```bash
npm install
npm run build
```

---

## Akun Demo

| Username | Password | Role | Keterangan |
|----------|----------|------|-----------|
| `superadmin` | `superadmin` | SUPERADMIN | Akses penuh, tidak bisa dihapus |
| `admin` | `admin` | ADMIN | Admin sistem |
| `manager` | `manager` | MANAGER | Manager operasional |
| `supervisor` | `password` | SUPERVISOR | Supervisor |
| `staff` | `staff` | STAFF | Staff operasional |
| `viewer` | `password` | VIEWER | Hanya lihat dashboard |

Semua akun tambahan (20+ lainnya) menggunakan password `password`.

---

## API Authentication

Endpoint API menggunakan **Laravel Sanctum** (token-based). Token ditampilkan saat seeding:

```bash
# Login
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"superadmin","password":"superadmin"}'

# Response: {"status":true,"msg":"Login Berhasil","data":{"access_token":"1|xxxx...","token_type":"Bearer","user":{...}}}

# Akses endpoint
curl -H "Authorization: Bearer 1|xxxx..." http://localhost:8000/api/auth/me
```

---

## Daftar Route Lengkap (52 routes)

### Auth (Web)
| Method | URI | Name | Controller |
|--------|-----|------|-----------|
| GET | `/login` | `login` | `LoginController@showLoginForm` |
| POST | `/login` | â€” | `LoginController@login` |
| POST | `/logout` | `logout` | `LoginController@logout` |
| GET | `/register` | `register` | `RegisterController@showRegistrationForm` |
| POST | `/register` | â€” | `RegisterController@register` |
| GET | `/password/reset` | `password.request` | `ForgotPasswordController@showLinkRequestForm` |
| POST | `/password/email` | `password.email` | `ForgotPasswordController@sendResetLinkEmail` |
| GET | `/password/reset/{token}` | `password.reset` | `ResetPasswordController@showResetForm` |
| POST | `/password/reset` | `password.update` | `ResetPasswordController@reset` |
| GET | `/password/confirm` | `password.confirm` | `ConfirmPasswordController@showConfirmForm` |
| POST | `/password/confirm` | â€” | `ConfirmPasswordController@confirm` |
| GET | `/email/verify` | `verification.notice` | `VerificationController@notice` |
| GET | `/email/verify/{id}/{hash}` | `verification.verify` | `VerificationController@verify` |
| POST | `/email/resend` | `verification.resend` | `VerificationController@resend` |

### Two-Factor Auth
| Method | URI | Name | Controller |
|--------|-----|------|-----------|
| GET | `/2fa` | `2fa.show` | `TwoFactorController@showTwoFactorForm` |
| POST | `/2fa` | `verifyTwoFactor` | `TwoFactorController@verifyTwoFactor` |

### User Setup (Auth + 2FA)
| Method | URI | Name | Controller |
|--------|-----|------|-----------|
| GET | `/` | `siteurl` | `AppController@index` |
| GET/POST/PUT/DELETE | `/user-setup/permission` | `user-setup.permission.*` | `PermissionController` |
| GET/POST/PUT/DELETE | `/user-setup/role` | `user-setup.role.*` | `RoleController` |
| GET/POST/PUT/DELETE | `/user-setup/user` | `user-setup.user.*` | `UserController` |

### API
| Method | URI | Keterangan |
|--------|-----|-----------|
| POST | `/api/auth/login` | Login via username + password |
| POST | `/api/auth/me` | User info (auth:sanctum) |
| POST | `/api/auth/logout` | Hapus token (auth:sanctum) |
| POST | `/api/auth/refresh` | Info (tidak support refresh token) |
| GET | `/api/user` | User data (auth:sanctum) |

### Utility
| Method | URI | Name | Controller |
|--------|-----|------|-----------|
| GET | `/debug/log-viewer` | `debug.log-viewer.index` | `LogViewerController@index` |
| GET | `/get-button-option` | `get.button-option` | `AjaxController@getButtonOption` |

---

## Environment Variables Penting

| Variable | Default | Keterangan |
|----------|---------|-----------|
| `APP_NAME` | `BaskaDrive` | Nama aplikasi |
| `APP_2FA` | `false` | Aktifkan 2FA (true/false) |
| `APP_2FA_NAME` | `token_2fa` | Nama cookie 2FA |
| `WA_NUMBER_TESTING` | `6285155300552` | Nomor WhatsApp testing (local) |
| `APP_WHATSAPP_API` | â€” | Endpoint API WhatsApp |
| `APP_WHATSAPP_API_NAME` | â€” | Session ID WA API |
| `APP_WHATSAPP_API_KEY` | â€” | Session key WA API |
| `SESSION_DRIVER` | `database` | Driver session |
| `QUEUE_CONNECTION` | `database` | Driver queue |
| `CACHE_STORE` | `database` | Driver cache |
| `DB_CONNECTION` | `mysql` | Database driver |

---

## Two-Factor Authentication (2FA)

Untuk mengaktifkan 2FA:

1. Set `APP_2FA=true` di `.env`
2. Konfigurasi endpoint WhatsApp API:
   - `APP_WHATSAPP_API` â€” URL endpoint API
   - `APP_WHATSAPP_API_NAME` â€” Nama session
   - `APP_WHATSAPP_API_KEY` â€” Key session
3. Pastikan user memiliki nomor WhatsApp (`nowa`) yang valid
4. Saat login, OTP akan dikirim via WhatsApp dan user harus memasukkan kode 5 digit

---

## Struktur Direktori

```
â”œâ”€â”€ app/
â”‚   â”œâ”€â”€ Http/
â”‚   â”‚   â”œâ”€â”€ Controllers/       â€” Controllers (Auth, Api, Admin, Traits)
â”‚   â”‚   â”œâ”€â”€ Middleware/         â€” Authenticate, RedirectIfAuthenticated, TwoFactor*, dll
â”‚   â”‚   â””â”€â”€ Requests/           â€” FormAjaxRequest, UserRequest
â”‚   â”œâ”€â”€ Models/                 â€” User, UserLog, Cart, CartAction
â”‚   â”œâ”€â”€ Helpers/                â€” fungsi-helper, LogHelper, KirimWAHelper
â”‚   â””â”€â”€ Providers/              â€” AppServiceProvider, AuthServiceProvider, RouteServiceProvider
â”œâ”€â”€ config/
â”‚   â”œâ”€â”€ 2fa.php                 â€” Konfigurasi Two-Factor Authentication
â”‚   â”œâ”€â”€ whatsapp.php            â€” Konfigurasi WhatsApp API
â”‚   â”œâ”€â”€ permission.php          â€” Spatie Permission config
â”‚   â”œâ”€â”€ datatables.php          â€” Yajra DataTables config
â”‚   â”œâ”€â”€ sanctum.php             â€” Laravel Sanctum config
â”‚   â””â”€â”€ intervention-image.php  â€” Image processing config
â”œâ”€â”€ database/
â”‚   â”œâ”€â”€ migrations/             â€” 6 migrasi (users, permission, cache, jobs, user_logs, personal_access_tokens)
â”‚   â”œâ”€â”€ seeders/                â€” PermissionSeeder, RoleSeeder, UserSeeder, UserLogSeeder, SanctumTokenSeeder
â”‚   â””â”€â”€ factories/              â€” UserFactory
â”œâ”€â”€ resources/
â”‚   â””â”€â”€ views/                  â€” 42 blade templates (Sneat theme)
â””â”€â”€ public/
    â”œâ”€â”€ assets/                 â€” Sneat theme (CSS, JS, vendor, fonts, images)
    â”œâ”€â”€ css/                    â€” Custom CSS
    â””â”€â”€ app_local/              â€” Local JS (settings, theme)
```

---

## Testing

```bash
php artisan test
```

Tests menggunakan **SQLite in-memory** (`phpunit.xml`), independen dari database utama.
16 test mencakup:
- Guest redirect ke halaman login
- Halaman login render 200
- Login berhasil (superadmin)
- Dashboard admin render 200
- Halaman Permission, Role, User render 200
- DataTables endpoints (JSON) Permission, User
- Log Viewer render 200
- Halaman Register, Password Reset, Confirm render 200
- API login & response format

---

## Tech Stack

| Teknologi | Versi |
|-----------|-------|
| Laravel Framework | 13.30.1 |
| PHP | ^8.3 |
| MySQL | ^8.0 |
| Spatie Permission | ^8.3 |
| Yajra DataTables | ^13.3 |
| Intervention Image | ^4.3 |
| Laravel Sanctum | ^4.3 |
| Guzzle HTTP | ^8.1 |
| Sneat Template | Bootstrap 5 |
| Chart.js | via Sneat |
| Tailwind CSS | ^4.0 |

---

## Lisensi

Proyek ini dikembangkan untuk keperluan komersial oleh KunciKarya.#   b a s k a d r i v e - v 2  
 