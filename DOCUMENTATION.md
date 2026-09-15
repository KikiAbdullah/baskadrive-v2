# 🚗 BaskaDrive — Dokumentasi Aplikasi

> **Sistem Informasi Manajemen Rental Kendaraan (Rental ERP)**
> Laravel 13 · PHP 8.3 · MySQL · Bootstrap 5 (Vuexy) · DataTables · Spatie PDF
> Terakhir diperbarui: September 2026

---

## Daftar Isi

1. [Gambaran Umum](#1-gambaran-umum)
2. [Arsitektur & Struktur Folder](#2-arsitektur--struktur-folder)
3. [Instalasi & Konfigurasi](#3-instalasi--konfigurasi)
4. [Autentikasi & Keamanan](#4-autentikasi--keamanan)
5. [Master Data](#5-master-data)
6. [Modul Sewa (Rental)](#6-modul-sewa-rental)
7. [Modul Fleet](#7-modul-fleet)
8. [Modul Keuangan](#8-modul-keuangan)
9. [Modul Akuntansi](#9-modul-akuntansi)
10. [Modul Laporan](#10-modul-laporan)
11. [Modul Sistem](#11-modul-sistem)
12. [Setup User & Role](#12-setup-user--role)
13. [Dashboard & Monitoring](#13-dashboard--monitoring)
14. [Generator PDF (Spatie Laravel-PDF)](#14-generator-pdf-spatie-laravel-pdf)
15. [Database: Skema, View & Trigger](#15-database-skema-view--trigger)
16. [Helper, Trait & Komponen](#16-helper-trait--komponen)
17. [API (Mobile/External)](#17-api-mobileexternal)
18. [Konvensi Koding](#18-konvensi-koding)
19. [Troubleshooting](#19-troubleshooting)

---

## 1. Gambaran Umum

BaskaDrive adalah aplikasi web ERP untuk bisnis rental mobil yang mencakup **alur bisnis end-to-end**:

```
Pelanggan → Reservasi/Sewa → Kontrak (PDF) → Perpanjangan → Pengembalian
→ Kerusakan/Denda → Klaim Asuransi → Invoice (PDF) → Pembayaran/Kwitansi (PDF)
→ Jurnal Akuntansi → Laporan
```

**Peran pengguna:** Admin & karyawan operasional (login wajib, proteksi 2FA via WhatsApp OTP).

**Stack teknologi:**

| Lapisan | Teknologi |
|---|---|
| Framework | Laravel 13 (PHP 8.3) |
| Database | MySQL |
| UI | Bootstrap 5 (tema Vuexy) + Remix Icon |
| Tabel data | Yajra DataTables (server-side) |
| Autentikasi | Laravel UI Auth + 2FA (OTP WhatsApp) |
| Role/Permission | Spatie Laravel-Permission |
| PDF | Spatie Laravel-PDF (Brave/Chrome headless → Dompdf fallback) |
| Gambar | Intervention Image |
| API Mobile | Laravel Sanctum (JWT-like token) |

---

## 2. Arsitektur & Struktur Folder

```
baskadrive-webapp/
├── app/
│   ├── Helpers/                    # Global helper (WA, log, util)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/                # AuthController (Sanctum)
│   │   │   ├── Auth/               # Login, register, verifikasi, 2FA
│   │   │   ├── Master/             # 11 controller master data (CrudTrait)
│   │   │   ├── Rental/             # 8 controller modul bisnis utama
│   │   │   └── Traits/             # CrudTrait, CrudHelperTrait, ListTrait, NumberTrait
│   │   ├── Middleware/             # Authenticate, TwoFactorVerify, dll.
│   │   └── Requests/               # Form request validation
│   ├── Models/                     # 29 Eloquent model
│   ├── Providers/
│   └── Support/
│       └── PdfDocument.php         # PDF engine dual-driver + fallback
├── config/
│   ├── laravel-pdf.php             # Driver PDF (chrome/dompdf)
│   ├── whatsapp.php                # Endpoint WA gateway (OTP)
│   └── ...
├── database/
│   ├── migrations/                 # m_* = master, tr_* = transaksi
│   └── seeders/                    # RentalErp*Seeder (data demo lengkap)
├── resources/views/
│   ├── layouts/                    # header, menu, navbar, footer, button_option
│   ├── components/                 # 7 komponen badge status
│   ├── rental/                     # Sewa (index/create/edit/show/print)
│   ├── fleet/                      # Maintenance, Kerusakan & Klaim
│   ├── finance/                    # Keuangan (invoice/fine/payment)
│   ├── accounting/                 # Jurnal & Buku Besar
│   ├── report/                     # Laporan (5 tab)
│   ├── system/                     # Pengaturan + Log Aktivitas
│   ├── master/                     # 11 modul master
│   ├── user-setup/                 # Permission/Role/User
│   └── pdf/                        # _fonts.blade.php (font Inter ter-embed)
├── public/assets/
│   ├── fonts/inter/                # Inter 400/500/700/900 (woff2+ttf)
│   └── js/main.js                  # Bootstrap menu (horizontal)
└── routes/
    ├── web.php                     # Auth, dashboard, user-setup, debug
    ├── rental.php                  # SEMUA route modul bisnis (rental/fleet/finance/accounting/report/system)
    └── api.php                     # Sanctum API
```

### Controller Modul Bisnis (app/Http/Controllers/Rental/)

| Controller | Tanggung jawab |
|---|---|
| `RentalController` | Inti sewa: wizard 4 langkah, list tab status, CRUD, detail & semua sub-aksi (perpanjangan, pengembalian, denda, invoice, pembayaran, refund), print kontrak |
| `FleetController` | Maintenance CRUD+complete, Kerusakan (CRUD + foto + status), Klaim Asuransi (create/show/status), button-option |
| `FinanceController` | Keuangan 1 halaman 3 tab (Invoice/Denda/Pembayaran), print invoice & kwitansi (PDF), kirim email invoice, bayar/bebaskan denda |
| `AccountingController` | Jurnal Umum (list/show/export/button-option), Buku Besar (list/detail saldo berjalan), Jurnal Manual (form entri ganda + validasi balance) |
| `ReportController` | 5 laporan dalam 1 halaman tab: Pendapatan, Utilisasi Armada, Top Pelanggan, Klaim & Denda, Laporan Keuangan + export CSV |
| `DashboardController` | Stat cards, calendar JSON (FullCalendar), fleet-map JSON |
| `SystemController` | Pengaturan umum (settings.json) + Log aktivitas |
| `AppController` | Landing Dashboard (`home.blade.php`) |

---

## 3. Instalasi & Konfigurasi

### 3.1 Prasyarat
- PHP 8.3+ (ekstensi: `sockets`, `pdo_mysql`, `gd`, `fileinfo`)
- Composer, Node.js (opsional)
- MySQL 5.7+/8.x

### 3.2 Langkah
```bash
composer install
cp .env.example .env            # sesuaikan DB_*, APP_WHATSAPP_API
php artisan key:generate
php artisan migrate --seed      # seeder data demo lengkap
php artisan storage:link
npm install && npm run build    # jika pakai Vite untuk assets
php artisan serve               # http://127.0.0.1:8000
```

### 3.3 Konfigurasi penting (.env)

| Key | Fungsi |
|---|---|
| `DB_*` | Koneksi MySQL (database `baskadrive`) |
| `APP_WHATSAPP_API` | Endpoint gateway WA untuk OTP 2FA & notifikasi |
| `LARAVEL_PDF_DRIVER` | `chrome` (default) / `dompdf` |
| `LARAVEL_PDF_CHROME_BINARY` | Override path browser (auto-deteksi bila kosong) |

### 3.4 Seeder
`php artisan db:seed` menjalankan RentalErp*Seeder yang mengisi: brand, model kendaraan, unit mobil, pelanggan, karyawan, sopir, lokasi, bengkel, tipe maintenance, promo, COA, sewa aktif/arsip, maintenance, kerusakan, invoice, jurnal — sehingga seluruh modul langsung bisa didemokan.

---

## 4. Autentikasi & Keamanan

### 4.1 Alur Login + 2FA
1. Login username/password → middleware `TwoFactorVerify`.
2. OTP dikirim via WhatsApp (`config/whatsapp.php` → `APP_WHATSAPP_API`).
3. Halaman `/2fa` → verifikasi OTP → sesi dibuka.
4. Endpoint 2FA: `GET/POST /2fa`.

### 4.2 Proteksi Route
- Semua controller modul memasang `$this->middleware('auth')` di constructor.
- Menu Setup (Permission/Role/User/Log Viewer) berpagar `@canany(['permissions_view', ...])`.

### 4.3 Role & Permission (Spatie)
- Tabel `roles`, `permissions`, `model_has_roles`, dll.
- Seeder: `PermissionSeeder`, `RoleSeeder`, `UserSeeder`.

### 4.4 API Token
- Mobile/eksternal login via `POST /api/auth/login` → token Sanctum.
- `POST /api/auth/me` untuk identitas; `refresh`/`logout` tersedia.

---

## 5. Master Data

Semua master memakai **CrudTrait** (CRUD generik) + pola list **row-select → menuoption**. Prefix route `master.*`. Halaman: `resources/views/master/<nama>/index.blade.php` (list + inline form, dua kolom).

| Modul | Route | Fungsi | Tabel |
|---|---|---|---|
| Data Merek & Model | `master.brand.*`, `master.vehicle-model.*` | Brand & model mobil (harga sewa, deposit, asuransi, transmisi, kapasitas) | `m_brand`, `m_vehicle_model` |
| Data Unit Mobil | `master.vehicle.*` | Unit fisik: plat, warna, tahun, status (available/rented/reserved/maintenance), odometer | `m_vehicle` |
| Data Pelanggan | `master.customer.*` | Individu/perusahaan, alamat, NIK, SIM + foto, verifikasi, "Rental Now" langsung ke wizard | `m_customer` |
| Data Karyawan | `master.employee.*` | Data pegawai + role jabatan + toggle aktif | `m_employee` |
| Data Sopir | `master.driver.*` | SIM, status aktif, riwayat sewa | `m_driver` |
| Lokasi/Cabang | `master.location.*` | Cabang penjemputan/pengembalian | `m_location` |
| Bengkel Mitra | `master.workshop.*` | Bengkel untuk maintenance | `m_workshop` |
| Tipe Maintenance | `master.maintenance-type.*` | Jenis servis + interval km/bulan | `m_maintenance_type` |
| Manajemen Promo | `master.promo.*` | Kode promo, diskon %/nominal, min. hari, toggle aktif | `m_promo` |
| Chart of Account | `master.coa.*` | Struktur akun bertingkat (parent/child), tipe (asset/liability/equity/revenue/expense) | `m_coa` |

**Pola interaksi list (seragam di seluruh aplikasi):**
1. Klik baris → ter-select (DataTables `select: single`).
2. AJAX `get.button-option` / `<modul>.button-option` → server render tombol kontekstual → tampil di area `menuoption`.
3. Tombol gaya `action-link-icon-text` (ikon di atas teks UPPERCASE, CSS `demo.css`).
4. Tombol "Tambah ..." juga memakai gaya yang sama.

---

## 6. Modul Sewa (Rental)

> Controller: `RentalController` · Views: `resources/views/rental/` · Prefix: `/rental`

### 6.1 Halaman utama (1 menu, tab status)
`GET /rental` — satu list semua transaksi dengan tab: **Semua / Reservasi / Berjalan / Selesai / Dibatalkan / Terlambat** (dengan counter per tab, filter `?status=`).
- Row-select → menuoption: SHOW, EDIT, KONFIRMASI (reserved), BATALKAN (reserved), PENGEMBALIAN (ongoing), INVOICE (completed), CETAK (reserved/ongoing).
- `GET /rental/get-data` — DataTables server-side per status.
- `GET /rental/get-button-option?id=` — render `rental/button_option.blade.php` berdasarkan status rental.

### 6.2 Wizard Buat Sewa (4 langkah, session-based)
`GET /rental/create` → `create.blade.php` + partial `_step-1..4.blade.php`:

| Step | Isi | AJAX |
|---|---|---|
| 1 | Pilih pelanggan (search + kartu) | — |
| 2 | Tanggal + cek ketersediaan kendaraan (cek tabrakan jadwal di tr_rental) | `create.available-vehicles` |
| 3 | Lokasi, sopir, promo, catatan + kalkulasi harga live | `create.calculate-total` |
| 4 | Konfirmasi + review harga | `create.store` |

- `POST /rental/create` (`saveStep`) menyimpan ke session `rental_wizard_data`.
- **Perhitungan harga** (server-side, `store()`):
  `total_base = base_rate × hari` → `+ asuransi (rate model × total_base)` → `+ biaya sopir` → `− diskon promo (%/nominal, min. hari)` → `× 11% PPN` → `total_amount`.
- Kode sewa auto: `RNT-XXXXX` (`gen_number`).
- Setelah simpan: kendaraan → `reserved`, redirect ke detail.

### 6.3 Detail Transaksi (Command Center)
`GET /rental/{rental}` — `show.blade.php`: info pelanggan/kendaraan/periode/add-on, rincian harga, pembayaran, perpanjangan, denda.

**Sub-aksi** (route `rental.detail.*`):

| Aksi | Route | Perilaku |
|---|---|---|
| Perpanjangan | `POST /{rental}/extension` | Hitung hari tambahan × tarif + PPN → status `pending` |
| Approve/Reject | `PUT /extension/{id}/approve|reject` | Approve: update rental_end_date & total; Reject: status |
| Form Pengembalian | `GET /{rental}/return` | Odometer, bahan bakar, kondisi, biaya ekstra, refund deposit |
| Proses Pengembalian | `POST /{rental}/return` | Rental → `completed`, kendaraan → `available`, catat `tr_return` |
| Tambah Denda | `POST /{rental}/fine` | Status `unpaid`, tercatat juga di Keuangan |
| Bayar/Bebaskan Denda | `PUT /fine/{id}/pay|waive` | Update status |
| Generate Invoice | `GET /{rental}/invoice` | Redirect bila sudah ada; form due date |
| Simpan Invoice | `POST /{rental}/invoice` | Auto `INV-XXXXX`, snapshot nominal sewa |
| Cetak Kontrak | `GET /{rental}/print` | **PDF** (lihat §14) |
| Pembayaran | `POST /{rental}/payment` | Catat payment + update invoice paid_amount/status |
| Refund | `POST /{rental}/refund` | Catat refund dari payment tertentu |

### 6.4 Edit Sewa
`GET/PUT /rental/{rental}/edit` — ubah periode/lokasi/sopir/promo/status → **total dihitung ulang otomatis** server-side.

### 6.5 Status Lifecycle
```
reserved ──konfirmasi──▶ ongoing ──return──▶ completed
    │                      │
    └────────cancel────────┴──▶ cancelled
ongoing + lewat rental_end_date = terlambat (tab Terlambat)
```

---

## 7. Modul Fleet

> Controller: `FleetController` · Views: `resources/views/fleet/`

### 7.1 Jadwal Maintenance (`fleet.maintenance.*`)
- List tab-select + menuoption (EDIT, SELESAI bila ≠ completed).
- Form: kendaraan, jenis, bengkel, tanggal, odometer, biaya, next-km.
- Status: `scheduled → in_progress → completed` (atau `cancelled`/`overdue`).
- **Complete** (AJAX): set actual_date & kembalikan kendaraan ke `available`.
- Reschedule (AJAX) mengubah tanggal & reset ke `scheduled`.

### 7.2 Kerusakan & Klaim (`fleet.damage.*` + `fleet.insurance-claim.*`) — digabung 1 list
Satu tabel laporan kerusakan dengan kolom **Status Klaim** & **Nilai Klaim** (eager `insuranceClaim`).
- CRUD kerusakan: jenis (body/mesin/interior/kaca/ban/listrik), severity (minor/moderate/severe), lokasi, estimasi & biaya aktual.
- Upload/hapus foto (multiple, `storage/damage-photos`).
- Ubah status: reported → inspected → approved → in_repair → repaired → closed.
- Menuoption row: SHOW, **AJUKAN KLAIM** (bila belum ada klaim), **LIHAT KLAIM** (bila ada).
- Klaim: auto-no `CLM-XXXXX`, provider, policy, nilai, approve → approved_amount & tanggal.
- Show klaim menampilkan kerusakan terkait + status.

---

## 8. Modul Keuangan

> Controller: `FinanceController` · Views: `resources/views/finance/` — **1 halaman, 3 tab**

`GET /finance?tab=invoice|fine|payment` (`finance.index`).

### 8.1 Tab Invoice
- Filter status (draft/sent/partially_paid/paid/overdue/cancelled).
- Kolom sisa = total − terbayar.
- Menuoption: SHOW, CETAK (PDF), KIRIM EMAIL (draft→sent).
- Show (`finance.invoice.show`): rincian item + ringkasan + riwayat pembayaran.
- Print: **PDF template modern tema `#666cff`** (§14).

### 8.2 Tab Denda
- Menuoption (unpaid): BAYAR (buat Payment otomatis) / BEBASKAN.
- Badge: unpaid/paid/waived.

### 8.3 Tab Pembayaran
- No. kwitansi `PAY-00001`, metode, referensi.
- Menuoption: KWITANSI (PDF).

---

## 9. Modul Akuntansi

> Controller: `AccountingController` · Prefix `/accounting`

| Halaman | Fungsi |
|---|---|
| Jurnal Umum (`accounting.journal.*`) | List semua jurnal + total debit, show detail entri Debit/Kredit, export CSV per jurnal |
| Posting Jurnal Manual (`accounting.manual-journal.*`) | Form multi-baris (pilih akun, debit/kredit, keterangan); validasi **balance debit=kredit** di client & server; auto type `manual` |
| Buku Besar (`accounting.ledger.*`) | Daftar akun + saldo (Σdebit−Σkredit), detail per akun dengan **saldo berjalan** |

Menu: Jurnal Umum + Buku Besar (form jurnal manual diakses dari tombol header Jurnal Umum).

---

## 10. Modul Laporan

> Controller: `ReportController` · 1 halaman: `GET /report?tab=...`

| Tab | Sumber data | Output |
|---|---|---|
| Pendapatan & Profit | `tr_payment` (completed) per bulan | Cards: total pendapatan, jml invoice, piutang jatuh tempo |
| Utilisasi Armada | Agregat `tr_rental` per kendaraan | % utilisasi = Σhari sewa / 365 |
| Top Pelanggan | Agregat per pelanggan | Jml sewa, total belanja, sewa terakhir |
| Klaim & Denda | `tr_damage_report` + klaim | Biaya perbaikan vs klaim |
| Laporan Keuangan | UNION 4 tabel: payment(+) / maintenance(−) / fine paid(−) / refund(−) | Pendapatan, beban, **laba** per periode |

Setiap tab punya tombol **Export CSV** (`report.export/{type}` — `revenue|fleet-utilization|top-customers|claims|financial`).

---

## 11. Modul Sistem

> Controller: `SystemController`

### 11.1 Pengaturan Umum (`system.settings`)
- Form: profil perusahaan (nama/telepon/email/alamat), pajak, deposit default, biaya telat/jam, toleransi telat, jatuh tempo invoice, mata uang, timezone.
- Disimpan sebagai **JSON** di `storage/app/settings.json` (merge di atas default).
- Dibaca oleh PDF (branding invoice/kwitansi/kontrak) & dicatat ke log aktivitas.

### 11.2 Log Aktivitas (`system.activity-log`)
- Tabel `user_logs` (user, action, menu, message).
- Ditulis otomatis oleh helper `storeLog()` + aksi tertentu (mis. update settings).
- DataTables + filter aksi.

---

## 12. Setup User & Role

Route `user-setup.*` (`PermissionController`, `RoleController`, `UserController` — pola CrudTrait).
- **Permission** dikelompokkan (view/create/edit/delete per modul).
- **Role** memuat banyak permission; user diberi role.
- **Log Viewer** (`debug.log-viewer.index`) membaca file log Laravel.

---

## 13. Dashboard & Monitoring

`GET /dashboard` (`dashboard.index`) — stat cards: pendapatan hari ini, sewa berjalan, reservasi, kendaraan tersedia; daftar pengembalian & penjemputan terdekat (7 hari).

| Endpoint | Fungsi |
|---|---|
| `GET /dashboard/calendar` | JSON event FullCalendar (rental aktif, warna per status, klik → detail) |
| `GET /dashboard/fleet-map` | JSON posisi kendaraan (lat/lng dari m_vehicle) untuk peta armada |

---

## 14. Generator PDF (Spatie Laravel-PDF)

### 14.1 Arsitektur `app/Support/PdfDocument.php`

```
PdfDocument::download($view, $data, $namaFile)
   │
   ├─1─ render view → file .html sementara (storage/app/pdf-tmp)
   │
   ├─2─ CARI BROWSER: Brave → Chrome → Edge → Chromium
   │     (Windows: ProgramFiles/LocalAppData; Unix: /usr/bin...)
   │
   ├─3─ JIKA ADA:  <binary> --headless --print-to-pdf=out.pdf in.html
   │     ── tanpa DevTools protocol → tidak pernah kena "Devtools could not start"
   │     ── timeout 60s + kill process tree bila hang
   │     ── validasi output diawali %PDF
   │
   ├─4─ JIKA GAGAL/TIDAK ADA BROWSER: fallback Spatie PDF driver "dompdf"
   │     (pure PHP — jalan di hosting mana pun tanpa binary)
   │
   └─5─ Response download: Content-Type application/pdf + nama file
```

- Kegagalan browser tercatat ke log Laravel via `report()` — user **tidak pernah** melihat error print.
- Dokumen PDF: **Kontrak Sewa** (`/rental/{id}/print`), **Invoice** (`/finance/invoice/{id}/print`), **Kwitansi** (`/finance/payment/{id}/receipt`).

### 14.2 Template PDF
- Desain seragam tema Vuexy: primary `#666cff`, ink `#3b4055`, muted `#676a7b`, line `#e5e5e8`, bg `#f7f7f9`.
- **Table-based layout** (bukan flex/grid) → hasil identik di Chrome & Dompdf.
- Ukuran kertas dijaga A4 via `@page { size: A4; margin: 0 }` + `.page { width:210mm; height:297mm }` + padding di `.inner` (bukan `.page`) agar tidak meluber ke halaman samping.
- **Font Inter di-embed base64** (`resources/views/pdf/_fonts.blade.php`; file di `public/assets/fonts/inter/`) → tampilan sama di semua OS.
- Detail: kartu pelanggan/kendaraan, chip status, strip periode, tabel item, bar Total & Status berwarna, riwayat pembayaran, S&K, tanda tangan, footer.

---

## 15. Database: Skema, View & Trigger

### 15.1 Konvensi penamaan
- `m_*` = master (m_brand, m_vehicle, m_customer, ...)
- `tr_*` = transaksi (tr_rental, tr_invoice, tr_journal, ...)

### 15.2 Relasi utama
```
m_brand 1─* m_vehicle_model 1─* m_vehicle ──┐
m_customer 1─* tr_rental *─1 m_employee     ├─ tr_rental_detail, tr_rental_extension
m_location (pickup/return)                  │
m_promo ────────────────────────────────────┤
m_driver ───────────────────────────────────┘
tr_rental 1─* tr_invoice 1─* tr_payment 1─* tr_refund
tr_rental 1─* tr_return ─* tr_damage_report ─1 tr_insurance_claim
                                └─* tr_damage_photo
tr_rental 1─* tr_maintenance (via vehicle), tr_fine
tr_journal 1─* tr_journal_detail *─1 m_coa
```

### 15.3 View SQL (migration `create_rental_erp_views`)
`v_vehicle_list`, `v_customer_list`, `v_rental_active_list`, `v_rental_reserved_list`, `v_rental_archive_list`, `v_maintenance_list`, `v_damage_list`, `v_invoice_list`, `v_fine_list`, `v_promo_list`, `v_journal_list`, `v_driver_list` — mempermudah query laporan/tabel.

### 15.4 Function & Trigger
- `fn_calculate_rental_total` — hitung total sewa di sisi DB.
- `trg_rental_before_insert` — set default/kode.
- `trg_rental_after_update_vehicle_status` — sinkron status kendaraan.

### 15.5 Storage
- `storage/app/settings.json` — pengaturan aplikasi.
- `storage/app/public/damage-photos/` — foto kerusakan.

---

## 16. Helper, Trait & Komponen

### 16.1 Traits (app/Http/Controllers/Traits)
| Trait | Fungsi |
|---|---|
| `CrudTrait` | CRUD generik (index/create/store/show/edit/update/destroy) untuk semua Master |
| `CrudHelperTrait` | generateViewName/Url, saveFoto/delImage, redirect helper, indexData |
| `NumberTrait` | `gen_number(model, kolom, 'RNT-$/#####', tanggal, ...)` → nomor dokumen auto (RNT/INV/CLM/PAY) |
| `ListTrait` | Sumber list role/permission untuk form Setup |

### 16.2 Helper global (app/Helpers)
| Fungsi | Fungsi |
|---|---|
| `kirimWA($nohp, $subject, $teks, ...)` | Kirim pesan WhatsApp via gateway (`APP_WHATSAPP_API`) — dipakai OTP |
| `storeLog($type, $nomor, $menu)` | Tulis `user_logs` |
| `uploadImage($file, $path, $width)` | Resize & simpan via Intervention Image |
| `cleanNumber`, `formatDate`, `st_aktif(_badge)`, `responseSuccess/Failed`, `roleName` | Util tampilan & response |

### 16.3 Komponen badge (resources/views/components)
`rental-status-badge`, `invoice-status-badge`, `fine-status-badge`, `payment-status-badge`, `maintenance-status-badge`, `damage-status-badge`, `claim-status-badge` — mapping status → label Indonesia + warna badge, dipakai list & show.

### 16.4 Pola JS list (seragam)
```js
dtable = $('#dtable').DataTable({ select: {style:'single'}, serverSide: true, rowId: '<pk>' , ...});
dtable.on('select', ...  $.get(<modul>.button-option, {id}) → $(".menuoption").html(view));
dtable.on('deselect', ... $(".menuoption").html(''));
```
Aksi AJAX (konfirmasi/batal/bayar/dll.) memakai SweetAlert2 konfirmasi → reload tabel.

---

## 17. API (Mobile/External)

Base: `/api` · Auth: Sanctum token.

| Endpoint | Fungsi |
|---|---|
| `POST /api/auth/login` | Login (username/password) → token |
| `POST /api/auth/refresh` | Perbarui token |
| `POST /api/auth/me` | Profil user aktif |
| `POST /api/auth/logout` | Cabut token |
| `GET /api/user` | User dari token (middleware sanctum) |

---

## 18. Konvensi Koding

1. **Route** didefinisikan di `routes/rental.php`, dikelompokkan per modul, `as => 'modul.'`.
2. **Controller modul** di `App\Http\Controllers\Rental\` — selalu `middleware('auth')`.
3. **Master data** wajib memakai `CrudTrait` (jangan tulis CRUD manual).
4. **List** selalu: DataTables server-side + row-select + button-option; kolom aksi inline dilarang.
5. **Tombol menuoption & tombol tambah**: gaya `action-link-icon-text`.
6. **Perhitungan uang** di server (controller), bukan hanya di JS.
7. **PDF** hanya lewat `App\Support\PdfDocument::download()`; view PDF harus table-based, A4 via `@page`, include `pdf._fonts`, padding di `.inner`.
8. **Nomor dokumen** via `gen_number` (RNT/INV/CLM).
9. **Status** dirender lewat komponen badge, jangan hardcode badge di controller.
10. Transaksi uang penting dibungkus `DB::beginTransaction/commit/rollback`.

---

## 19. Troubleshooting

| Gejala | Penyebab | Solusi |
|---|---|---|
| "Devtools could not start" / timeout saat print | Protokol DevTools chrome-php gagal (auto-update browser) | Sudah diatasi — driver kini CLI `--print-to-pdf`; bila tetap gagal otomatis fallback Dompdf |
| PDF meluber ke halaman samping | Padding di `.page` (content-box) | Pastikan padding di `.inner`, bukan `.page` |
| PDF berantakan di dompdf | Flex/grid/vars tidak didukung | Gunakan table-layout & warna literal |
| Font PDF salah | Font tidak ter-embed | Pastikan `@include('pdf._fonts')` & file `public/assets/fonts/inter/` ada |
| `Route [x] not defined` | Nama route tidak cocok | `php artisan route:list` |
| Menu tidak highlight / dropdown tidak terbuka | JS menu & deteksi route | Lihat `menu.blade.php` (`$is()` helper + dropdown-fix script) |
| DataTables tidak muncul | Syntax JS (mis. `], ,`) | Cek console browser |
| OTP WA tidak terkirim | Gateway mati | Cek `APP_WHATSAPP_API` |
| Gambar tidak tampil | storage link | `php artisan storage:link` |

---

## 20. Checklist Deploy Produksi (audit Setup S-15)

Sebelum go-live, verifikasi baris per baris:

- [ ] `APP_ENV=production` dan **`APP_DEBUG=false`** (stack trace jangan pernah keluar)
- [ ] `APP_KEY` hasil `php artisan key:generate` baru (bukan salinan dari dev)
- [ ] `APP_URL=https://domain-asli` — semua halaman & QR verifikasi ikut https
- [ ] `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER=database|redis`, `SESSION_ENCRYPT=true`
- [ ] `APP_REGISTRATION_ENABLED=false` (default) — akun hanya dibuat lewat Setup > User
- [ ] `APP_2FA=true` + `APP_WHATSAPP_API`/`APP_WHATSAPP_API_NAME`/`APP_WHATSAPP_API_KEY` terisi (kalau 2FA dipakai)
- [ ] `LOG_CHANNEL=daily`, `LOG_LEVEL=warning`, `LOG_DAILY_DAYS=14`
- [ ] `php artisan config:cache route:cache event:cache` + `optimize:clear` saat deploy
- [ ] `php artisan storage:link` (foto model/unit, logo, bukti kerusakan)
- [ ] Cron/ scheduler: `* * * * * php artisan schedule:run` (cleanup-temp 02:00, check-maintenance 06:30, reminder WA 08:00)
- [ ] `storage/app/pdf-tmp` writable; Dompdf OK tanpa Chrome; bila mau Browsershot: pasang Chrome
- [ ] DB user aplikasi: **bukan root**, akses `SELECT/INSERT/UPDATE/DELETE` saja (tanpa DROP/DDL)
- [ ] Backup DB otomatis (UI Setup menawarkan manual; jadwalkan mysqldump/cron juga)

---

*Dokumentasi ini dibuat otomatis dari kondisi kode per September 2026. Kode sumber: `github.com/KikiAbdullah/baskadrive-v2`.*
