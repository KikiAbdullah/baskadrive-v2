# 📊 AUDIT — MENU LAPORAN
## BaskaDrive Rental ERP (Pendapatan, Utilisasi Armada, Pelanggan, Klaim, Keuangan + Ekspor CSV/PDF)

**Tanggal Audit:** 16 September 2026  
**Cakupan:** `app/Http/Controllers/Rental/ReportController.php` (384 baris), `routes/rental.php:346-351`, `resources/views/report/index.blade.php` (488 baris), `resources/views/report/pdf.blade.php` (48 baris), `app/Support/PdfDocument.php`, `menu.blade.php:210-220`, `PermissionSeeder:115-119`, `RoleSeeder:25-80`  
**Metode:** Static review + verifikasi middleware/route + pemeriksaan enum & GROUP BY vs `only_full_group_by`  
**Legenda:** `[X]` = ditutup (implementasi selesai; RPT-05 bersih tanpa aksi) — `[ ]` = temuan terbuka. Checkbox dan status mencerminkan 17 Sep 2026, bukan hasil pengujian MySQL strict.

> Audit lanjutan setelah master, setup/keamanan, sewa (FASE 1–3 + polish 16/09), dan sistem (SYS-01..SYS-07 tuntas 16/09). Menu Laporan adalah modul terakhir yang diaudit.
>
> **CATATAN REMEDIASI (17 Sep 2026):** Seluruh temuan RPT-01..RPT-07 telah ditindaklanjuti (RPT-05 tanpa aksi — bukti bersih). Uraian temuan dan usulan **Fix** pada bagian 3–8 dipertahankan sebagai **snapshot historis baseline 16 Sep 2026**, bukan kondisi saat ini. Semua referensi baris/jumlah baris pada cakupan dan bagian 2–8 mengacu ke baseline sebelum perbaikan; checkbox diperbarui dan klaim middleware awal diberi koreksi eksplisit. Verifikasi remediasi ada di [Bagian 10](#10-verifikasi-remediasi-17-sep).

---

## DAFTAR ISI

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Inventaris Rute & View](#2-inventaris-rute--view)
3. [Permission & Keamanan Ekspor](#3-permission--keamanan-ekspor)
4. [Validasi Filter & Ketahanan Input](#4-validasi-filter--ketahanan-input)
5. [Kebenaran Data & Query](#5-kebenaran-data--query)
6. [Format Angka & Locale Ekspor](#6-format-angka--locale-ekspor)
7. [Performa & Batas Beban](#7-performa--batas-beban)
8. [Integrasi Lintas Modul](#8-integrasi-lintas-modul)
9. [Matriks Prioritas Action Plan](#9-matriks-prioritas-action-plan)
10. [Verifikasi Remediasi (17 Sep)](#10-verifikasi-remediasi-17-sep)

---

## 1. RINGKASAN EKSEKUTIF

**Ringkasan awal — baseline 16 Sep:** Lima tab laporan (pendapatan, utilisasi armada, pelanggan teratas, klaim, keuangan) + ekspor CSV/PDF **berfungsi**. Tidak ditemukan SQL injection (semua `selectRaw` statis, satu-satunya binding berparameter). Kelemahan utama: **ekspor tanpa gate** (permission-nya ada tapi tak dipakai) dan **filter tanggal tanpa validasi** (input sampah → 500).

| Kategori | Status |
|---|---|
| 🔴 Tinggi (ekspor tanpa izin / crash input) | **SELESAI — 2 temuan** (RPT-01, RPT-02) diperbaiki 17 Sep |
| 🟡 Sedang (kebenaran query / inkonsistensi) | **DITUTUP — 2 perbaikan diimplementasikan** (RPT-03, RPT-04); RPT-05 bersih tanpa aksi |
| 🟢 Rendah (format / performa) | **SELESAI — 2 temuan** (RPT-06, RPT-07) diperbaiki 17 Sep |

> Tabel awal 16 Sep keliru menghitung RPT-05 sebagai temuan terbuka; RPT-05 sejak awal bersih tanpa aksi. Status 17 Sep: enam perbaikan diimplementasikan, satu verifikasi tanpa aksi; tidak ada pekerjaan implementasi terbuka. Temuan historis dipertahankan di bawah. Keterbatasan verifikasi: eksekusi MySQL strict dan PDF binary riil **belum diuji** (test suite berjalan di SQLite; PDF via `Pdf::fake`/view).

---

## 2. INVENTARIS RUTE & VIEW

**Inventaris historis — baseline 16 Sep.** Grup `routes/rental.php:346` `['prefix' => 'report', 'as' => 'report.', 'middleware' => ['can:report_view']]` semula dicatat mewarisi `web,auth,two_factor`.

> **Koreksi middleware:** `two_factor` pada catatan/tabel awal berikut bukan bukti middleware efektif. Hasil `route:list` yang diamati pada 17 Sep **tidak menampilkan `two_factor`**: rute laporan memakai `web` + `auth` + `can:report_view`; kedua ekspor juga memakai `can:report_export` + `throttle:5,1`. Ini tidak membuktikan bahwa `two_factor` pernah aktif pada baseline.

| HTTP | URI | Action | Name | Catatan middleware awal (historis; dikoreksi di atas) |
|---|---|---|---|---|
| GET | `/report` | `index` L30 | `report.index` | `web,auth,two_factor` + `can:report_view` |
| GET | `/report/get-data` | `data` L77 → 5 `*Data()` | `report.data` | `web,auth,two_factor` + `can:report_view` |
| GET | `/report/export/{type}` | `export` L324 (CSV stream) | `report.export` | `web,auth,two_factor` + `can:report_view` — tanpa `can:report_export` |
| GET | `/report/export-pdf/{type}` | `exportPdf` L353 (Spatie PDF) | `report.export-pdf` | `web,auth,two_factor` + `can:report_view` — tanpa `can:report_export` |

`{type}` ∈ `revenue|fleet-utilization|top-customers|claims|financial`; tipe tak dikenal → `null` → redirect error (`exportRows` L245, `export` L328, `exportPdf` L357).

| View | Fungsi |
|---|---|
| `report/index` (488 baris) | 5 tab + kartu ringkasan (revenue/financial) + ApexCharts (revenue/fleet) + DataTables server-side + link ekspor tersinkron querystring (L427–432) |
| `report/pdf` (48 baris) | Template PDF generik via `PdfDocument::download` (dompdf default, bisa browsershot/chrome/gotenberg) |
| Menu `menu.blade.php:213-220` | Flat item, sudah di-gate `@can('report_view')` |

---

## 3. PERMISSION & KEAMANAN EKSPOR

### [X] RPT-01 — Ekspor CSV/PDF tanpa gate `report_export` — 🔴
- `PermissionSeeder:117-118` mendefinisikan `report_export`/`report_download`; `RoleSeeder:34,47,59` memberi `report_export` ke ADMIN/MANAGER/SUPERVISOR (STAFF/VIEWER hanya `report_view`) — tetapi **kedua permission tak direferensikan di route/controller/view mana pun** (grep hanya kena seeder).
- Akibat: **STAFF/VIEWER (hanya `report_view`) dapat mengunduh seluruh data** via `report.export` & `report.export-pdf`.
- **Fix:** `->middleware('can:report_export')` di kedua rute ekspor; putuskan nasib `report_download` (pakai untuk PDF vs CSV, atau hapus dari seeder agar tak menyesatkan).

---

## 4. VALIDASI FILTER & KETAHANAN INPUT

### [X] RPT-02 — Filter tanggal tanpa validasi → 500 — 🔴
- `dateRange()` (`ReportController:23-28`) langsung `Carbon::parse($request->start_date)` tanpa `validate()`/`try`. `?start_date=foo` melempar exception di jalur `index`/`data` (tanpa catch — hanya `export/exportPdf` yang punya try/catch L326,355).
- Tanpa cek `start<=end`, tanpa batas rentang.
- **Fix:** `$request->validate(['start_date' => 'nullable|date', 'end_date' => 'nullable|date|after_or_equal:start_date'])` di `index/data/export/exportPdf` (pola guard wizard sewa), + batasi rentang wajar bila perlu.

---

## 5. KEBENARAN DATA & QUERY

### [X] RPT-03 — GROUP BY alias vs `only_full_group_by` — 🟡
- `fleetUtilizationData:128` group by alias `vehicle_name` (ekspresi `CONCAT`), `topCustomersData:162` group by alias `customer_name`, `exportRows` revenue L254–255 group by alias `period` sambil select `DATE_FORMAT(...'%M %Y')`. `financialData` sudah diperbaiki dengan query terpisah (komentar L210), tiga titik ini belum — MySQL strict dapat menolak.
- **Fix:** group by ekspresi mentah (`CONCAT(...)`, kolom tanggal), bukan alias.

### [X] RPT-04 — Inkonsistensi kartu & metrik — 🟡
- `outstanding` (`index:62-63`) memakai `due_date < now()` sementara `totalRevenue/totalInvoices` memakai `[$start,$end]` — label kartu ambigu saat filter diubah. Samakan ke `$end` + perjelas label ("Jatuh tempo s.d. …").
- Utilisasi armada (`fleetUtilizationData:117,136-139`): `SUM(rental_days)/rangeDays` di-cap 100% — rental overlap dihitung ganda, penyebut abaikan ukuran armada/tanggal akuisisi; bar `index:384-387` jenuh di 100% untuk mobil sibuk. Dokumentasikan definisi atau hitung hari unik terisi.

### [X] RPT-05 — Tidak ada SQL injection (terverifikasi, tanpa aksi) — 🟡→✅
- Seluruh `selectRaw` statis; satu-satunya binding berparameter (`financialData:212-218`, `DATE_FORMAT(...,?)`); filter `status` via Eloquent bound (`claimsData:186`); `tab` di-whitelist (`index:42`, `data:79`). **Bersih — dicatat sebagai bukti, bukan pekerjaan.**

---

## 6. FORMAT ANGKA & LOCALE EKSPOR

### [X] RPT-06 — Format CSV/PDF tidak konsisten locale id-ID — 🟢
- PDF (`pdf.blade.php:40`): numerik `>=1000` diformat 0 desimal, `<1000` mentah (cacah vs rupiah tak terbedakan; sen terpotong meski cast `decimal:2`).
- CSV (`export:339-345`): float mentah (desimal `.`, delimiter `,`) — rusak dibuka di Excel Indonesia (harap `;` + desimal `,`) dan tanpa BOM UTF-8.
- `revenueData:99` memakai `%M` (nama bulan Inggris) sementara UI berbahasa Indonesia.
- **Fix:** helper format terpusat (Rp vs cacah vs persen), CSV delimiter `;` + BOM, label bulan Indonesia (`Carbon::locale('id')` / mapping).

---

## 7. PERFORMA & BATAS BEBAN

### [X] RPT-07 — Ekspor & grafik tanpa batas — 🟢
- Ekspor GET tanpa `throttle`, tanpa paginasi (`exportRows →get()` L255,268,279,288,311); rentang besar berisiko OOM/timeout. Grafik memuat `length:1000` (`index:438`).
- **Fix:** `throttle` di rute ekspor (+ batas rentang, mis. maks 366 hari, konsisten RPT-02), pertimbangkan chunk/cursor untuk CSV besar.

---

## 8. INTEGRASI LINTAS MODUL

- Angka pendapatan (`Payment status=completed`), klaim (`DamageReport + insuranceClaim`, eager-load benar — N+1 aman), summary finansial (payment/maintenance/fine/refund) konsisten dengan modul asal.
- Branding dokumen memakai `AppSettings::all()` (`exportPdf:376`) — perubahan Pengaturan Umum otomatis tercermin (alasan penguncian SYS-01).
- Tidak ada rute cetak terpisah yang lolos auth — "print" adalah browser-print/`export-pdf`, keduanya di bawah `auth`+`can:report_view`.

---

## 9. MATRIKS PRIORITAS ACTION PLAN

| ID | Temuan | Prioritas | Perkiraan | Status |
|---|---|---|---|---|
| RPT-01 | Gate `can:report_export` di kedua rute ekspor (+ nasib `report_download`) | 🔴 Tinggi | Kecil | [X] Ditutup — diimplementasikan 17 Sep |
| RPT-02 | Validasi filter tanggal + batas rentang | 🔴 Tinggi | Kecil | [X] Ditutup — diimplementasikan 17 Sep |
| RPT-03 | GROUP BY ekspresi periode/kolom sumber (MySQL strict belum dieksekusi) | 🟡 Sedang | Kecil | [X] Ditutup — diimplementasikan 17 Sep |
| RPT-04 | Saldo outstanding saat ini dengan cutoff `$end` + proxy utilisasi terdokumentasi | 🟡 Sedang | Kecil | [X] Ditutup — diimplementasikan 17 Sep |
| RPT-05 | Verifikasi no-SQLi (bukti, tanpa aksi) | — | — | [X] Bersih |
| RPT-06 | Format locale id-ID CSV/PDF + bulan Indonesia | 🟢 Rendah | Kecil–Sedang | [X] Ditutup — diimplementasikan 17 Sep |
| RPT-07 | Throttle + batas rentang ekspor | 🟢 Rendah | Kecil | [X] Ditutup — diimplementasikan 17 Sep |

Status matriks adalah penutupan implementasi, bukan sertifikasi eksekusi MySQL strict atau rendering PDF binary. RPT-03 memakai GROUP BY ekspresi periode/kolom sumber; RPT-04 memilih saldo outstanding saat ini dengan cutoff `$end` dan proxy utilisasi, bukan rekonstruksi historis/okupansi unik.

---

## 10. VERIFIKASI REMEDIASI (17 SEP)

Hasil verifikasi 17 Sep yang dilaporkan (bukan eksekusi ulang saat pembaruan dokumen ini):
- Seluruh suite: **66 tests / 901 assertions** lulus.
- Pengujian targeted, termasuk **Unit**: **39 tests / 825 assertions** lulus; hasil terbaru `ModuleAccessGatesTest`: **16 tests / 765 assertions** (bagian dari cakupan tersebut, bukan tambahan).
- **Pint lulus untuk seluruh file PHP yang berubah**; `route:list` mengonfirmasi `auth` + `can:report_view`, serta `can:report_export` + `throttle:5,1` pada kedua ekspor. `two_factor` tidak muncul pada hasil rute yang diamati.
- **Batas verifikasi:** suite memakai SQLite; eksekusi aktual pada MySQL dengan `only_full_group_by`/strict **belum diuji**. PDF diuji melalui `Pdf::fake()` dan view; rendering/unduhan binary PDF riil **belum diuji**.

| ID | Remediasi yang diimplementasikan |
|---|---|
| RPT-01 | Gate `report_export` pada CSV/PDF dan kontrol ekspor di view. `report_download` dihapus dari seeders saja; entri/assignment pada database yang sudah ada tidak diubah dan tetap inert (tidak dipakai sebagai gate). |
| RPT-02 | Validasi tanggal `Y-m-d`, urutan awal–akhir, status klaim, dan rentang maksimal **366 hari inklusif** pada jalur laporan/ekspor; validasi UI tidak menggantikan validasi server. |
| RPT-03 | Query bersama untuk tabel/ekspor; agregasi periode memakai ekspresi tanggal pada GROUP BY, nama kendaraan/pelanggan memakai kolom sumber lengkap, bukan alias. Implementasi diperbaiki; hasil SQLite tidak membuktikan eksekusi MySQL strict. |
| RPT-04 | Outstanding menjumlahkan **saldo saat ini** (`total_amount - paid_amount`) untuk invoice berstatus sent/partially_paid/overdue dengan tanggal terbit dan jatuh tempo sampai `$end`; bukan snapshot saldo historis pada tanggal akhir. Utilisasi dipilih sebagai **proxy per kendaraan**: jumlah `rental_days` dari sewa yang mulai dalam periode, semua status, dibagi hari kalender inklusif, maksimal 100%. Bukan okupansi hari unik, tidak menghapus hitungan overlap atau menghitung ketersediaan seluruh armada/tanggal akuisisi. Definisi dijelaskan pada UI/PDF. |
| RPT-05 | Ditutup sebagai bukti bersih tanpa aksi; bukan perbaikan SQL injection. |
| RPT-06 | Format terpusat bertipe text/money/integer/percent untuk CSV/PDF; nilai uang mempertahankan dua desimal, cacah tidak diperlakukan sebagai uang, desimal locale Indonesia dan label bulan Indonesia. CSV memakai **BOM UTF-8 + delimiter `;`**. |
| RPT-07 | Ekspor di-throttle **5/menit**; CSV streaming dengan **`lazy(500)`** untuk query detail/agregat (keuangan memakai koleksi agregat bulanan terbatas). PDF dibatasi **2.000 baris data**, kelebihan ditolak dengan pesan validasi. Tabel maksimal **100 baris/request**; grafik pendapatan **13** periode dan armada **15** kendaraan, bukan `length:1000`. |

Filter status klaim dipertahankan pada pilihan UI, navigasi tab, request data dan ekspor. **Terapkan Filter** melakukan submit/reload halaman sehingga kartu ringkasan ikut diperbarui bersama tabel, grafik, dan tautan ekspor sesuai filter yang diterapkan.
