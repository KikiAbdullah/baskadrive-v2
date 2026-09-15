# 🔁 AUDIT ULANG — MENU MASTER DATA
## BaskaDrive Rental ERP (Post-Fix Regression & Defect Analysis)

**Tanggal Audit:** 15 September 2026  
**Cakupan:** Seluruh modul Master Data (`app/Http/Controllers/Master/*`, `resources/views/master/*`, `CrudTrait`/`CrudHelperTrait`, tabel `m_*`)  
**Metode:** Static review kode + runtime verification (Eloquent sync test terhadap DB aktual)  
**Legenda:** `[X]` = sudah diperbaiki & terverifikasi berfungsi — `[ ]` = belum diperbaiki / ditemukan bug

> Catatan: audit ini melanjutkan `audit_12092026.md` bagian 2.2. Banyak item lama sudah diimplementasi (12–13 Sep), **namun temuan baru menunjukkan beberapa implementasi cacat karena pola hook di `CrudTrait`**.

---

## 1. HASIL REGRESI ITEM AUDIT LAMA (2.2)

| Item Lama | Status | Hasil Verifikasi Ulang |
|---|---|---|
| 2.2.1 Foto model kendaraan | [X] | Kolom + form upload (enctype multipart) + preview ada. ~~Tapi lihat M-02~~ **M-02 FIXED 15/09/2026** — edit + ganti foto kini aman (file lama dihapus, baru utuh). |
| 2.2.2a Barcode thermal 50x30 | [X] | `barcodePdf` pakai `paperSize(50,30,'mm')` + fallback dompdf, layout mm-based benar. |
| 2.2.2b Riwayat mutasi cabang unit | [X] | ~~Tabel & hook ada, tetapi history TIDAK PERNAH TERCATAT~~ **FIXED 15/09/2026 via M-01** — terverifikasi runtime: mutasi NULL→1 dan 2→1 kini menghasilkan baris `vehicle_location_histories` dengan from/to benar. |
| 2.2.3 Blacklist + NIK 16 digit | [X] | Kolom, badge, filter `where('is_blacklisted', false)` di wizard, regex NIK berfungsi. Catatan baru: M-04, M-09. |
| 2.2.4 Karyawan → akun login | [X] | Checkbox create akun + role ada. ~~Belum one-to-one~~ **M-12 FIXED 15/09/2026:** `users.employee_id` dibuat + backfill + auto-link saat create akun. |
| 2.2.5a Badge SIM hampir habis | [X] | Badge danger/warning/success per <30 hari bekerja. |
| 2.2.5b Upah sopir ke jurnal | [ ] | Tetap belum dikerjakan (sama seperti audit lama). |
| 2.2.6 GPS & jam operasional cabang | [X] | Kolom + form ada; fallback koordinat di peta armada bekerja. Catatan: tanpa validasi range (M-22). |
| 2.2.7 Rating & spesialisasi bengkel | [X] | Select rating + badge bintang di tabel. |
| 2.2.8 Auto-trigger jadwal servis | [X] | `fleet:check-maintenance` kini terjadwal harian 06:30 (bootstrap/app.php). |
| 2.2.9 Promo per kategori | [X] | JSON `applicable_categories` + enforcement `isPromoApplicable()`. Catatan baru: M-08. |
| 2.2.10 Kunci akun induk COA | [X] | Disabled di dropdown + tolakan server-side `children_count`. Catatan baru: M-16. |

---

## 2. TEMUAN BARU — PRIORITAS TINGGI (🔴)

### [X] M-01 — Riwayat mutasi lokasi kendaraan tidak pernah terekam (BUG FATAL) — **FIXED 15/09/2026**
- **Lokasi:** `VehicleController::customUpdate()` (L71-84), dipanggil dari `CrudTrait::update()` (L193-199).
- **Akar masalah:** `customUpdate()` dieksekusi **setelah** `$model->save()`. Setelah save, Eloquent memanggil `syncChanges()` sehingga `getOriginal('location_id')` sudah bernilai sama dengan nilai baru → kondisi `$originalLocation != $newLocation` **selalu false** → `VehicleLocationHistory::create()` tak pernah jalan.
- **Bukti runtime:** uji fill+save pada DB aktual → `getOriginal(location_id) after save == new value`, jumlah history = 0.
- **Dampak:** fitur audit-trail cabang (2.2.2) mati total tanpa error.
- **✅ Perbaikan:** `CrudTrait::update()` kini mengambil snapshot `$model->getOriginal()` **sebelum** `fill()/save()` dan meneruskannya sebagai argumen ketiga `customUpdate($data, $model, $originalAttributes)`. `VehicleController::customUpdate` membaca `from` dari snapshot (pakai `array_key_exists` agar kasus nilai `NULL` awal tetap benar).
- **Verifikasi:** history terbentuk pada dua skenario — `NULL → 1` dan `2 → 1`, `from`/`to` akurat; jejak test dibersihkan.

### [X] M-02 — Upload ulang foto model kendaraan menghapus file yang baru diupload (BUG) — **FIXED 15/09/2026**
- **Lokasi:** `VehicleModelController::customUpdate()` (L65-71) + urutan `CrudTrait::update()`.
- **Akar masalah:** sama dengan M-01 — setelah save, `getOriginal('photo')` = **filename baru**, sehingga `delImage($model->getOriginal('photo'))` justru **menghapus file yang baru diupload** (record menunjuk ke file hilang → 404), sementara file lama jadi yatim (orphan).
- **Dampak:** edit model + ganti foto = katalog kehilangan visual; storage bocor file yatim.
- **✅ Perbaikan:** ikut memanfaatkan snapshot `$originalAttributes` dari `CrudTrait::update()` — `customUpdate` kini menghapus file **lama** (dari snapshot) hanya bila filename berbeda; file baru tetap utuh.
- **Verifikasi:** test upload fake image saat edit → file baru ADA di disk, file lama TERHAPUS; jejak test dibersihkan.

### [X] M-03 — Mayoritas CRUD master tanpa validasi input apa pun — **FIXED 15/09/2026**
- **Lokasi:** `CrudHelperTrait::getRequest()` — fallback `$request->all()` untuk Brand, Location, Workshop, MaintenanceType, Promo, Coa, Driver, Vehicle (hanya Customer & field foto VehicleModel yang punya aturan).
- **Dampak konkret (sebelum fix):**
  - Nama kosong / spasi doang tersimpan sebagai data sah.
  - Angka negatif / absurd lolos: `base_price_per_day = -500000`, `latitude = 999`, `rating = 50`, `interval_km` negatif, `insurance_rate` > 100.
  - Promo: `valid_to < valid_from` diterima; `discount_value` persentase bisa > 100.
  - Kendaraan: `year` bebas (mis. 1800/2099), `mileage` negatif.
- **✅ Perbaikan (15/09/2026):**
  - `customRequest()` + `$request->validate()` ditambahkan ke **9 controller master**: Brand, Location, Workshop, MaintenanceType, Promo, Coa, Driver, Vehicle, VehicleModel (melengkapi Customer).
  - Aturan: `required/max/regex` nama & kode; `unique` dengan `ignore(record)` untuk `brand_name`, `type_name`, `promo_code`, `account_code`, `license_number`, `license_plate`, `vin`; range `latitude -90..90` / `longitude -180..180`; `rating 0..5`; `insurance_rate 0..100`; `year 1980..sekarang+1`; `mileage 0..2jt`; promo `valid_to after_or_equal valid_from` + diskon persen maks 100 (closure kondisional).
  - Helper `blanksToNull()` di CrudHelperTrait menormalkan `'' → null` untuk field numerik/date/nullable (menghilangkan sumber SQL error tersembunyi).
  - `CrudTrait::store()/update()` kini **me-rethrow `ValidationException`** (sebelumnya tertelan `catch (Exception)` → pesan generik), sehingga pesan error per-field + old input kembali ke form.
  - Bonus inkonsistensi data: form `fuel_type` mengirim `bensin/listrik` sedangkan enum DB `Petrol/Electric` (dan `cvt` tak ada di enum) → normalisasi kanonik di controller + `required` di form + migrasi enum `transmission` menambah `CVT`.
- **Bukti runtime:** 16/16 skenario uji lolos (nama kosong, duplikat brand/SIM, latitude 999, rating 50, interval negatif, persen>100, tanggal terbalik, siklus parent COA, format kode akun, plat & tahun kendaraan absurd, kanonisasi bensin→Petrol & cvt→CVT, kasus valid tersimpan). Sisa data uji dibersihkan (0 baris).
- **Catatan:** M-04 (bocornya pesan SQL mentah) kini **selesai terpisah** — lihat bagian M-04 (sudah `[X]`).

### [X] M-04 — Pelanggaran constraint DB membocorkan pesan SQL mentah ke user (dan data tak bisa disimpan) — **FIXED 15/09/2026**
- **Lokasi:** `CrudTrait::store()/update()/destroy()` → `withErrors($e->getMessage())`.
- **Pemicu nyata (sebelum fix):**
  - `m_customer.driver_license_number` = **NOT NULL + UNIQUE**, form melabeli "No. SIM (opsional)" → submit kosong mengirim `''` → pelanggan **kedua tanpa SIM** gagal dengan `SQLSTATE[23000] Duplicate entry ''`.
  - `m_employee.username` NOT NULL UNIQUE (field tidak required), `m_driver.license_number` UNIQUE, `m_vehicle.license_plate/vin` UNIQUE, `m_coa.account_code` UNIQUE, `m_brand.brand_name` UNIQUE.
- **✅ Perbaikan (15/09/2026):**
  1. **Migrasi** `2026_09_15_020000_make_customer_driver_license_nullable.php` — `driver_license_number` menjadi NULL-able (index UNIQUE dipertahankan); data lama `''` dinormalisasi → `NULL` (MySQL mengizinkan banyak NULL pada unique). Terverifikasi `SHOW COLUMNS`: Null=YES, Key=UNI.
  2. **Normalisasi `'' → null`** via `blanksToNull()` pada `CustomerController` (email, NIK, SIM, company_name) — sumber Duplicate-entry `''` hilang.
  3. **Pra-validasi `unique` + required** ditambahkan pada `CustomerController` (email/NIK/SIM dengan `ignore(record)`) dan `EmployeeController` (username min:4 + regex + unique, email unique, hire_date/position/role required) — ke unikan lainnya sudah tercakup perbaikan M-03.
  4. **Penangkap `QueryException` khusus** di `CrudTrait::store()/update()/destroy()` → helper baru `CrudHelperTrait::friendlyDbError()`: kode 1062 → "nilai unik sudah dipakai", 1451/1452 → "data masih terkait transaksi lain", default → pesan teknis umum. Pesan SQL mentah tidak lagi sampai ke UI; error asli dicatat ke `Log::error`.
- **Bukti runtime:** 7/7 skenario PASS — dua pelanggan tanpa SIM tersimpan berurutan (pemicu asli), email duplikat → pesan validasi bahasa Indonesia, hapus brand terpakai → pesan FK ramah (tanpa string SQLSTATE), username karyawan kosong/duplikat → tolakan ramah. Jejak data uji = 0.
- **Bonus (terbongkar saat pengujian suite):** `RentalErpCoaSeeder` kini idempoten (lewati akun existing — sebelumnya menabrak unique 4-3000 hasil migration saat `migrate + db:seed`), `RentalErpCompleteSeeder` menambah `4-3000` ke daftar COA-nya & membungkus `SET FOREIGN_KEY_CHECKS` khusus driver MySQL (mengembalikan kompatibilitas SQLite untuk test suite), sehingga `php artisan test` **16/16 PASS** (sebelumnya 15 error).
- **Catatan:** M-05 (password karyawan required saat create) masih terbuka — saat ini create tanpa password tertangkap sebagai error teknis umum oleh penjaga baru, bukan lagi SQL mentah.

### [X] M-05 — Gagal create karyawan tanpa password (NOT NULL `password_hash`) — **FIXED 15/09/2026**
- **Lokasi:** `EmployeeController::customRequest()` unset `password_hash` saat kosong; migration `m_employee.password_hash` **tidak nullable**.
- **Dampak (sebelum fix):** input karyawan baru tanpa password → QueryException mentah (bagian dari M-04) → form tampak "error aneh".
- **✅ Perbaikan (15/09/2026):**
  - Rule kondisional di `customRequest()`: `password_hash` = **`required|min:8` saat create**, `nullable|min:8` saat update (field route `id` jadi pembeda); hash bcrypt hanya bila terisi — update tanpa password tetap mempertahankan hash lama.
  - Form employee: label + `required` + `minlength=8` + `autocomplete="new-password"` + petunjuk pada mode create; placeholder update "Kosongkan jika tidak diubah".
  - Bonus inkonsistensi yang terbongkar: field `role` di form dulunya **teks bebas** dengan placeholder menyesatkan (`staff`, `driver` — tidak ada di enum DB) padahal rule M-04 menolak nilai di luar enum → kini jadi **select** dengan 6 nilai enum sistem (`admin/manager/cashier/mechanic/accountant/director`); field `email`, `position`, `username`, `hire_date` dilengkapi `required` di sisi klien agar konsisten dengan rule server.
- **Bukti runtime:** 7/7 skenario PASS — create tanpa password / password pendek / role 'staff' ditolak dengan pesan jelas; create valid tersimpan ter-bcrypt; update tanpa password tidak merusak hash; password baru pendek ditolak; password baru valid terverifikasi `Hash::check`. Jejak data uji = 0. Test suite tetap 16/16 hijau.

---

## 3. TEMUAN BARU — PRIORITAS SEDANG (🟡)

### [X] M-06 — Tidak ada permission gate untuk seluruh menu Master Data — **FIXED 15/09/2026**
- **Lokasi:** `routes/rental.php` kelompok `master.*` — hanya `auth`, nol `can:`; view master tanpa `@can`.
- **Dampak:** role VIEWER/STAFF dapat menambah/menghapus merek, unit, pelanggan, COA (termasuk COA yang dipakai jurnal!). Perbaikan 2.9 kemarin hanya mengunci cancel sewa & waive denda.
- **✅ Perbaikan (15/09/2026):** permission `master_view/add/edit/delete` dibuat (PermissionSeeder+RoleSeeder dijalankan); grup route master `can:master_view`; store/create `can:master_add`; edit/update/verify/toggle `can:master_edit`; destroy `can:master_delete`. UI: menu Master dibungkus `@can('master_view')`, tombol baris (vedit/destroy/verify/dll) dibangun melalui array `$btnList` bersyarat `auth()->user()->can(...)` + `@can('master_add')` pada inline create. Matriks terverifikasi: STAFF=view only, SUPERVISOR=tanpa delete, MANAGER/ADMIN=penuh.

### [X] M-07 — Helper `validationRelation()` tidak pernah dipakai → hapus data referensial = error SQL — **FIXED 15/09/2026**
- **✅ Perbaikan:** helper baru `CrudHelperTrait::blockedByRelations()` membaca metadata `information_schema.KEY_COLUMN_USAGE` (generic, tanpa konfigurasi relasi) dan dipanggil di `CrudTrait::destroy()` sebelum delete → pesan "Data tidak dapat dihapus karena masih digunakan oleh tabel tr_rental (kolom customer_id)". Terverifikasi pada customer dengan riwayat sewa (data tidak terhapus).
- **Lokasi:** `CrudHelperTrait::validationRelation()` (defined, never called); FK `tr_rental` memakai `restrictOnDelete` untuk customer/vehicle/location.
- **Solusi:** panggil sebelum `$model->delete()` di `CrudTrait::destroy()`, kembalikan flash "Data tidak dapat dihapus karena digunakan di X" alih-alih pesan SQL.

### [X] M-08 — `max_usage` promo tidak pernah berfungsi — **FIXED 15/09/2026**
- **✅ Perbaikan:** `RentalController::store()` — re-check kuota dengan `lockForUpdate()` (promo gugur otomatis bila habis), `increment('usage_count')` dalam transaction yang sama; `update()` — decrement old / increment new + tolakan "Kuota promo habis" (sekaligus memperbaiki bug laten: discount lama tetap terpasang padahal promo_id ter-null); `cancelReservation()` — decrement. Kolom **Pemakaian** (progress bar x/y) ditambahkan di tabel promo.
- **Bukti:** `usage_count` hanya ada di model/cast/scope `active()`; **tidak ada satu pun increment** di `RentalController` (grep `usage_count` = 0 hasil di app kecuali model). Nilai promo tak pernah menaikkan `usage_count`, scope `usage_count < max_usage` selalu lolos.
- **Solusi:** increment `usage_count` saat sewa disimpan (dalam DB transaction yang sama, dengan re-check kuota), decrement saat sewa dibatalkan; tampilkan progress pemakaian di tabel promo.

### [X] M-09 — Aturan blacklist tidak lengkap — **FIXED 15/09/2026**
- **✅ Perbaikan:** `Rule::requiredIf(fn () => $request->boolean('is_blacklisted'))` untuk `blacklist_reason`; `customUpdate()` mencatat jejak blacklist/un-blacklist ke `user_logs` (siapa + alasan). Terverifikasi runtime: tanpa reason ditolak; dengan reason terekam di log; un-blacklist via form bekerja.
- `blacklist_reason` tidak divalidasi wajib saat `is_blacklisted` tercentang (klaim UI "wajib jika dicentang" — server tidak menegakkan).
- Tidak ada aksi "hapus blacklist" terbalik (hanya centang manual via form; verify hanya bisa true).
- **Solusi:** `Rule::requiredIf(fn() => $request->boolean('is_blacklisted'))` untuk reason; audit trail siapa mem-blacklist.

### [X] M-10 — Rute & view yatim (fitur ada, tombol tidak ada) — **FIXED 15/09/2026**
- **✅ Perbaikan:** `layouts/button_option.blade.php` mendapat aksi baru (form inline `onclick submit`, kompatibel konten AJAX-inserted): `VERIFIKASI` (customer, gate master_edit), `SEWA SEKARANG` (customer), `TOGGLE AKTIF` (promo & employee, gate master_edit); tombol SHOW customer & HISTORY sopir ditambahkan ke `` index. Keenam endpoint yatim kini terjangkau UI.
| Endpoint | View/Route | Masalah |
|---|---|---|
| `master.customer.show` | view show.blade.php ada | tidak ada tombol SHOW di index (hanya vedit) |
| `master.customer.verify` | — | tidak pernah dipanggil UI (grep 0) |
| `master.customer.rental-now` | — | tidak pernah dipanggil UI |
| `master.employee.toggle-active` | — | tidak ada tombol toggle di index |
| `master.promo.toggle` | — | tidak ada tombol toggle di index |
| `master.driver.history` | view ada | index driver hanya mengirim buttons vedit+destroy → riwayat sopir tak bisa dibuka |
- **Solusi:** tambahkan tombol aksi pada `buttons` JSON tiap index (mengikuti pola vehicle yang menyertakan barcode/history), atau hapus route mati.

### [X] M-11 — Pembuatan akun login karyawan gagal secara senyap — **FIXED 15/09/2026**
- **✅ Perbaikan:** `EmployeeController::customStore()` tidak lagi diam: tabrakan username/email → `Log::warning` + flash **warning** ("akun login tidak dibuat…") yang tampil via varian `alert-warning` baru di `layouts/alert.blade.php`; kegagalan `assignRole` → `Log::error` + flash warning, bukan `catch {}` kosong.
- `EmployeeController::customStore()` bila username/email sudah dipakai → hanya `if (!$exists)` dilewati tanpa pesan; kegagalan `assignRole` ditelan `try/catch` kosong.
- **Solusi:** lempar warning flash ("akun tidak dibuat karena username ganda") dan log error, jangan silent.

### [X] M-12 — Relasi `users` ↔ `m_employee` tetap tidak ada (dampak 2.2.4) — **FIXED 15/09/2026**
- **✅ Perbaikan:** migrasi `2026_09_15_030000` menambah `users.employee_id` (nullable, UNIQUE, FK nullOnDelete) + backfill via kecocokan username/email; akun dibuat lewat form karyawan otomatis tertaut `employee_id`; relasi `User::employee()` & `Employee::user()` ditambahkan → `created_by` jurnal / `issued_by` denda / approver kini terisi. Terverifikasi runtime: user baru tertaut employee_id benar.
- **Bukti:** kolom `employee_id` tidak ada di migrasi users; namun kode memakai `auth()->user()->employee_id ?? null` untuk `created_by` jurnal (`AccountingService::post`), `issued_by` denda, approver extension, `inspected_by` kerusakan.
- **Dampak:** `created_by`/`issued_by` selalu NULL di semua transaksi → jejak akuntan tidak terbaca; laporan per-karyawan mustahil.
- **Solusi:** migration `users.employee_id` (nullable, FK nullOnDelete) + isi saat create-account (M-11) + backfill untuk akun existing; setter fallback di service.

### [X] M-13 — Log aktivitas master mencatat referensi kosong — **FIXED 15/09/2026**
- **✅ Perbaikan:** `CrudTrait` (store/update/destroy) kini memakai `logReference($model)` menghasilkan → `"Brand#11"` (class_basename + getKey). Terverifikasi: message log delete brand berisi referensi lengkap.
- `CrudTrait` memakai `$model->no ?? $model->id` untuk field referensi log; model master tidak punya atribut `no`/`id` (PK = `brand_id`, `customer_id`, ...) → `UserLog` tak menyimpan identitas objek.
- **Solusi:** ganti dengan `$model->getKey()` + `$model->getTable()` atau nama model.

---

## 4. TEMUAN BARU — PRIORITAS RENDAH (🟢)

### [X] M-14 — Potensi XSS ringan di kolom DataTable model — **FIXED 15/09/2026**
- ~~`alt="'.$m->model_name.'"` tidak di-escape~~ Kini `alt="'.e($m->model_name).'"` di `VehicleModelController::ajaxData()` — konsisten dengan `CustomerController`.

### [X] M-15 — Dropdown induk COA memungkinkan siklus (parent = diri sendiri / turunan) — **FIXED 15/09/2026**
- ~~`formData()` daftar `list_parent` tidak mengecualikan akun berjalan beserta sub-tre-nya~~ Sudah: `formData()` kini mengecualikan akun berjalan + seluruh turunannya (BFS), dan **validasi server-side** menolak parent = diri sendiri / turunan (teruji runtime dua skenario).

### [X] M-16 — Inkonsistensi format kode akun COA — **FIXED 15/09/2026**
- ~~Placeholder form `"1100"`~~ kini `"1-1100"`; regex `^\d{1,2}-\d{3,4}$` ditegakkan; **kode akun yang sudah memiliki mutasi jurnal dikunci** (perubahan `account_code` ditolak dengan pesan jelas — melindungi pemetaan `AccountingService::resolveCoa`). Terganti: uji runtime PASS.

### [X] M-17 — `CrudHelperTrait::indexData()` fallback memakai `orderBy('id')` — **FIXED 15/09/2026**
- ~~Tabel master tidak memiliki kolom `id`~~ Kini `orderBy($this->model->getKeyName(), 'DESC')`. Terverifikasi runtime: `indexData()` Brand mengembalikan 10 baris tanpa error SQL.

### [X] M-18 — Foto unit mobil masih input teks `photo_url` bebas — **FIXED 15/09/2026**
- ~~Inkonsistensi dengan 2.2.1~~ Kini mengikuti pola VehicleModel: input file `photo` (rule `image|mimes|max:2048`) + pratinjau foto berjalan, `enctype="multipart/form-data"` di wrapper create/edit, file disimpan ke `storage/vehicle/` via `saveFoto()`, file lama dihapus saat diganti (`customUpdate` berbasis snapshot M-01) & saat unit dihapus (`customDestroy`), resolver tampilan menangani nilai lama berupa URL eksternal. Submit AJAX edit vehicle dialihkan ke `FormData` (POST) agar upload bekerja. Terverifikasi runtime: foto baru utuh + lama terhapus; tanpa file = `photo_url` tidak disentuh.

### [X] M-19 — Validasi koordinat lokasi — **FIXED 15/09/2026**
- ~~`latitude`/`longitude` tanpa rentang~~ Sudah tercakup perbaikan M-03: rule `numeric|between:-90,90` & `between:-180,180` di `LocationController` (dan kini di `VehicleController` juga). Format `opening_hours` masih teks bebas — disengaja (bebas dicetak di dokumen/informasi).

### [X] M-20 — Duplikasi `id="dform"` pada partial create/edit — **FIXED 15/09/2026**
- ~~id global rapuh~~ Seluruh wrapper form (22 partial create/edit master + 6 user-setup) memakai class `js-crud-create` / `js-crud-edit`, dan 52 selector JS (`#dform`/`#formupdate`) dikonversi serentak — tidak ada lagi dependensi keunikan id; event delegation class tetap benar bila beberapa partial_termuat satu halaman. Grep global: 0 sisa referensi lama; Blade compile bersih; suite 16/16 hijau.

---

## 5. REKOMENDASI URUTAN PERBAIKAN

```
FASE A (bug pembunuh fitur) — TUNTAS 4/4 ✅
  [X] M-01 + M-02 : SELESAI 15/09/2026 — CrudTrait::update kini meng-snapshot getOriginal()
                    sebelum save dan meneruskannya ke customUpdate($data,$model,$original).
                    (Unit test manual runtime lolos: history from/to benar; foto baru utuh,
                    foto lama terhapus.)
  [X] M-03        : SELESAI 15/09/2026 — customRequest+validate di 9 controller master,
                    blanksToNull, rethrow ValidationException, kanonisasi fuel/transmission.
                    (16/16 skenario uji runtime PASS.)
  [X] M-04        : SELESAI 15/09/2026 — driver_license_number customer kini nullable
                    (+ data '' dinormalisasi), blanksToNull + unique/required rule utk
                    Customer & Employee, QueryException catch di CrudTrait store/update/
                    destroy dengan pesan ramah (7/7 uji runtime PASS).
  [X] M-05        : SELESAI 15/09/2026 — password_hash required|min:8 saat create,
                    nullable|min:8 saat update (hash lama utuh jika kosong); form
                    diselaraskan (+ role jadi select enum, field NOT NULL ditandai
                    required). 7/7 uji runtime PASS.

FASE B (keamanan & integritas) — TUNTAS 4/4 ✅
  [X] M-06 : permission gate modul master (+menu & tombol @can)
  [X] M-12 : users.employee_id + backfill + auto-link akun karyawan
  [X] M-07 : guard hapus blockedByRelations() di CrudTrait::destroy
  [X] M-08 : kuota promo increment/re-check/decrement + kolom Pemakaian

FASE C (polish) — TUNTAS 7/7 ✅
  [X] M-09, M-10, M-11, M-13 : SELESAI 15/09/2026 (batch M-06..M-13)
  [X] M-15, M-16, M-19 : ikut selesai bersama M-03/M-04
  [X] M-14, M-17, M-18, M-20 : SELESAI 15/09/2026 — e() di alt foto model;
                    indexData pakai getKeyName(); upload foto unit (file+preview+
                    storage/vehicle+FormData); id form global -> class selector
                    (41 file, 0 sisa referensi #dform/#formupdate)
```

---

## 6. RINGKASAN STATISTIK AUDIT ULANG

| Kategori | Jumlah |
|---|---|
| Item audit lama terverifikasi selesai | 11 dari 11 (2.2.4 one-to-one tuntas via M-12; 2.2.5b upah sopir tetap backlog lintas-modul, bukan master) |
| Temuan baru 🔴 Tinggi | 5 (M-01..M-05) — **SEMUA FIXED 15/09/2026 (5/5)** |
| Temuan baru 🟡 Sedang | 8 (M-06..M-13) — **SEMUA FIXED 15/09/2026 (8/8)** |
| Temuan baru 🟢 Rendah | 7 (M-14..M-20) — **SEMUA FIXED 15/09/2026 (7/7)** |
| **Total terbuka** | **0 — SELURUHNYA TUNTAS** (per update 15/09/2026) |

> **Temuan bonus saat verifikasi batch ini (langsung diperbaiki):** `NumberTrait::gen_number()` selalu memanggil `Model::withTrashed()` sehingga **pembuatan booking sewa/invoice/klaim crash total** pada model non-soft-delete. Kini `genBaseQuery()` hanya memakai `withTrashed()` bila model benar-benar memakai trait `SoftDeletes` (terverifikasi: alur booking + kuota promo jalan end-to-end).

\* M-01/M-02 terkonfirmasi sebagai regressi implementasi 2.2.2/2.2.1 dan telah diperbaiki melalui perbaikan terpusat pada `CrudTrait::update()`.

*Laporan audit ulang disusun 15/09/2026; item yang telah diperbaiki ditandai `[X]` dengan catatan verifikasi runtime.*
