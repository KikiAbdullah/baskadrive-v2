# AUDIT — MENU FLEET
## BaskaDrive Rental ERP — Maintenance, Kerusakan, Klaim Asuransi & Penagihan Kerusakan

**Tanggal audit:** 21 September 2026
**Lokasi dokumen:** `audit/15092026/audit_fleet.md`
**Cakupan utama:** menu **Fleet** dengan prefix `/fleet` (dua submenu: Jadwal Maintenance, Kerusakan & Klaim), plus integrasi penagihan kerusakan ke Sewa/Keuangan (`tr_fine`, `fine_pay`).
**Metode:** penelusuran statis rute, middleware efektif (`php artisan route:list -v`), controller, model, skema/migrasi, seeder, view Blade, JavaScript, serta inventaris pengujian. Satu verifikasi runtime dilakukan hanya untuk middleware efektif dan keberadaan rute; tidak ada perubahan data.
**Legenda:** `[ ]` = terbuka; `[X]` = sudah diperbaiki dan diverifikasi. Total temuan: **14** (FLE-01..FLE-14): 6 tinggi, 6 sedang, 2 rendah.

> Audit ini statis pada snapshot kode saat ini. Tidak ada uji konkurensi, replikasi celah, konfigurasi produksi, atau pengiriman notifikasi yang diverifikasi. Referensi baris mengikuti snapshot sumber; perubahan setelah audit keuangan 15092026 (remediasi FIN-01..FIN-16 pada 21 Sep 2026) memengaruhi perilaku jalur denda yang disentuh Fleet dan dicatat pada bagian terkait.

---

## DAFTAR ISI

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Inventaris Rute dan Tampilan](#2-inventaris-rute-dan-tampilan)
3. [Permission dan Batas Akses](#3-permission-dan-batas-akses)
4. [Integritas Data dan Konsistensi Status](#4-integritas-data-dan-konsistensi-status)
5. [Integrasi Keuangan (Penagihan Kerusakan & Klaim)](#5-integrasi-keuangan-penagihan-kerusakan--klaim)
6. [Validasi dan Ketahanan Operasional](#6-validasi-dan-ketahanan-operasional)
7. [Dokumen, Format, dan Pengalaman Pengguna](#7-dokumen-format-dan-pengalaman-pengguna)
8. [Pengujian dan Batas Verifikasi](#8-pengujian-dan-batas-verifikasi)
9. [Proteksi yang Sudah Ada](#9-proteksi-yang-sudah-ada)
10. [Matriks Prioritas Action Plan](#10-matriks-prioritas-action-plan)
11. [Keputusan Bisnis dan Urutan Perbaikan](#11-keputusan-bisnis-dan-urutan-perbaikan)

---

## STATUS REMEDIASI — 21 SEPTEMBER 2026

Seluruh 14 temuan (FLE-01..FLE-14) **telah diremediasi** dan diverifikasi regresi (`tests/Feature/FleetActionsTest.php` — 18 test; full suite 109 test hijau):

- **FLE-02 (+catatan FLE-15):** kontrak status kanonik di `DamageReport::STATUSES` — enum DB diperluas lewat migrasi `2026_09_21_000002_fleet_status_enums_and_damage_fine_link.php`, sinkron dengan controller, dropdown, badge, dan laporan; parameter `id` button-option kini divalidasi skalar.
- **FLE-01:** permission aksi baru `fleet_maintain`, `fleet_damage_add`, `fleet_damage_manage`, `fleet_claim_manage`, `fleet_bill_renter` (PermissionSeeder + RoleSeeder + middleware rute + gate UI). STAFF hanya operasional harian (catat kerusakan & maintenance); tagih/klaim/hapus bukti butuh MANAGER+.
- **FLE-03..06:** semua aksi mutasi berjalan dalam `DB::transaction` + `lockForUpdate`; state machine kendaraan (rented tidak pernah dipaksa `available`; `completed` hanya melepas kendaraan `maintenance` tanpa rental aktif); reschedule menolak jadwal selesai dan bentrok masa sewa aktif.
- **FLE-07/08:** kolom `tr_fine.damage_id` — dedup berbasis identitas (bukan LIKE deskripsi), aman klik ganda; akrual piutang saat ditagih (Dr 1-2100 / Cr 4-2000) via `FineSettlementService::accrueDamageCharge`; pembayaran denda kerusakan melunasi piutang (bukan pendapatan ganda); waive membalik akrual.
- **FLE-09:** jurnal pencairan klaim hard-post dalam transaksi (gagal = rollback status), guard nominal sebelum status `paid`, prioritas status dijaga (`paid` tidak bisa kembali `approved`, klik kedua 409).
- **FLE-10..12:** validasi nominal min/max sesuai DECIMAL(12,2); enum `damage_type`/`severity` (termasuk `total_loss`) sesuai skema + pasangan rental-kendaraan divalidasi; kedua view klaim (`form`, `show`) dibuat; endpoint biaya aktual `fleet.damage.update-cost`; `policy_number` kini nullable; status klaim `closed` + kolom `paid_at`.
- **FLE-13:** foto maksimal 10 per laporan, dibekukan pada status closed/rejected/written_off, file fisik ikut terhapus saat delete, izin hapus terpisah (`fleet_damage_manage`).
- **FLE-14:** `AppSettings::money()` (dua desimal) dan tanggal `translatedFormat` locale `id` pada seluruh tampilan Fleet.

**Batas penutupan:** uji konkurensi paralel pada MySQL produksi, verifikasi perilaku `->change()` enum pada MySQL riil, dan rekonsiliasi data historis (klaim/denda lama tanpa `damage_id`) tidak dilakukan dalam sesi ini — jalankan `php artisan migrate` pada staging sebelum produksi.

---

## 1. RINGKASAN EKSEKUTIF

Menu Fleet terbagi dua submenu — **Jadwal Maintenance** (`tr_maintenance`) dan **Kerusakan & Klaim** (`tr_damage_report` + `tr_insurance_claim` + `tr_damage_photo`). Seluruh 24 rute berada di bawah `web + auth + TwoFactorVerify + can:fleet_view` (terverifikasi runtime `route:list -v`).

Masalah utama adalah **kontrak enum status kerusakan yang rusak di tiga lapisan** (database, controller, UI), **seluruh rute mutasi Fleet tidak mempunyai permission aksi** di luar `fleet_view`, **penagihan kerusakan yang mengidentifikasi duplikat lewat LIKE deskripsi bebas**, dan **klaim asuransi berstatus `closed` yang tidak dikenal skema**. Status kendaraan juga dapat melompat ke `available` dari maintenance yang tidak sedang memegang kendaraan.

| Prioritas | Jumlah | Temuan |
|---|---:|---|
| Tinggi | 6 | FLE-02, FLE-04, FLE-05, FLE-07, FLE-08, FLE-09 |
| Sedang | 6 | FLE-01, FLE-03, FLE-06, FLE-11, FLE-12, FLE-13 |
| Rendah | 2 | FLE-10, FLE-14 |
| Total | **14** | FLE-01..FLE-14 |

**Catatan penilaian:** temuan didukung kode sumber, bukan bukti insiden produksi. Dampak konkurensi (FLE-06) adalah kelemahan jaminan di tingkat kode, belum diuji paralel. Integrasi denda menilai kode Fleet; perubahan perilaku settlement denda oleh remediasi keuangan 21 Sep 2026 dicatat sebagai konteks, bukan temuan baru.

---

## 2. INVENTARIS RUTE DAN TAMPILAN

### 2.1 Rute modul Fleet

Sumber: `routes/rental.php` (MODUL 4), verifikasi middleware via `route:list --path=fleet -v`. Semua rute mewarisi `web, Authenticate, TwoFactorVerify, Authorize:fleet_view`. Kolom "Izin tambahan" memperlihatkan bahwa **tidak ada satu pun rute yang memuat permission aksi**.

| HTTP | URI | Action FleetController | Nama rute | Izin tambahan |
|---|---|---|---|---|
| GET | `/fleet/maintenance` | `maintenanceIndex` | `fleet.maintenance.index` | — |
| GET | `/fleet/maintenance/get-data` | `maintenanceData` | `fleet.maintenance.data` | — |
| GET | `/fleet/maintenance/get-button-option` | `maintenanceButtonOption` | `fleet.maintenance.button-option` | — |
| GET | `/fleet/maintenance/create` | `maintenanceCreate` | `fleet.maintenance.create` | — |
| POST | `/fleet/maintenance/store` | `maintenanceStore` | `fleet.maintenance.store` | — |
| GET | `/fleet/maintenance/{id}/edit` | `maintenanceEdit` | `fleet.maintenance.edit` | — |
| PUT | `/fleet/maintenance/{id}` | `maintenanceUpdate` | `fleet.maintenance.update` | — |
| PUT | `/fleet/maintenance/{id}/complete` | `maintenanceComplete` | `fleet.maintenance.complete` | — |
| PUT | `/fleet/maintenance/{id}/reschedule` | `maintenanceReschedule` | `fleet.maintenance.reschedule` | — |
| GET | `/fleet/damage` | `damageIndex` | `fleet.damage.index` | — |
| GET | `/fleet/damage/get-data` | `damageData` | `fleet.damage.data` | — |
| GET | `/fleet/damage/get-button-option` | `damageButtonOption` | `fleet.damage.button-option` | — |
| GET | `/fleet/damage/create` | `damageCreate` | `fleet.damage.create` | — |
| POST | `/fleet/damage/store` | `damageStore` | `fleet.damage.store` | — |
| GET | `/fleet/damage/{id}` | `damageShow` | `fleet.damage.show` | — |
| PUT | `/fleet/damage/{id}/status` | `damageUpdateStatus` | `fleet.damage.update-status` | — |
| PUT | `/fleet/damage/{id}/bill-renter` | `damageBillRenter` | `fleet.damage.bill` | — |
| POST | `/fleet/damage/{id}/photo` | `damagePhotoUpload` | `fleet.damage.photo.upload` | — |
| DELETE | `/fleet/damage/photo/{photoId}` | `damagePhotoDelete` | `fleet.damage.photo.delete` | — |
| GET | `/fleet/insurance-claim/create` | `claimCreate` | `fleet.insurance-claim.create` | — |
| POST | `/fleet/insurance-claim/store` | `claimStore` | `fleet.insurance-claim.store` | — |
| GET | `/fleet/insurance-claim/{id}` | `claimShow` | `fleet.insurance-claim.show` | — |
| PUT | `/fleet/insurance-claim/{id}/status` | `claimUpdateStatus` | `fleet.insurance-claim.update-status` | — |

Tidak ditemukan rute ekspor CSV/PDF khusus Fleet. Data kerusakan/klaim diekspor lewat modul Laporan (`report_export`) pada tab klaim.

### 2.2 Tampilan

| File | Fungsi |
|---|---|
| `resources/views/fleet/maintenance/index.blade.php` | DataTables jadwal maintenance |
| `resources/views/fleet/maintenance/form.blade.php` | Form create/edit maintenance (select kendaraan/bengkel/jenis, flatpickr) |
| `resources/views/fleet/maintenance/button_option.blade.php` | Edit + tombol SELESAI (non-completed) |
| `resources/views/fleet/damage/index.blade.php` | DataTables kerusakan + kolom klaim |
| `resources/views/fleet/damage/form.blade.php` | Form laporan kerusakan |
| `resources/views/fleet/damage/show.blade.php` | Detail kerusakan, foto dropzone, ubah status, tagih penyewa |
| `resources/views/fleet/damage/button_option.blade.php` | Show, ajukan/lihat klaim |
| `resources/views/fleet/insurance-claim/form.blade.php` | Form klaim asuransi |

**Ketidaksesuaian tampilan yang ditemukan:**
- `fleet/insurance-claim/show.blade.php` **tidak ada di disk** padahal `claimShow` merender view tersebut — GET `/fleet/insurance-claim/{id}` **melempar error 500** untuk klaim mana pun (FLE-12).
- Badge `components/damage-status-badge.blade.php` memetakan `assessment/repair_in_progress/claimed_insurance/written_off`, sedangkan form ubah status di `fleet/damage/show.blade.php` menyajikan daftar berbeda (`inspected/approved/in_repair/rejected/closed`). Kombinasi keduanya tidak pernah cocok dengan enum database (FLE-02).
- Label jenis kerusakan pada `fleet/damage/form.blade.php` memakai nilai `body` dan `engine`, yang **bukan anggota enum** `damage_type` di database.

---

## 3. PERMISSION DAN BATAS AKSES

### 3.1 Kontrak permission yang ada

Sumber: `database/seeders/PermissionSeeder.php`, `database/seeders/RoleSeeder.php`, `resources/views/layouts/menu.blade.php:131-149`.

| Role bawaan | fleet_view | Catatan |
|---|---|---|
| SUPERADMIN | Ya | Semua permission |
| ADMIN | Ya | Termasuk master_add/edit/delete |
| MANAGER | Ya | Termasuk master_add/edit/delete |
| SUPERVISOR | Ya | Tidak punya master_delete |
| STAFF | Ya | Tidak punya master_* |
| VIEWER | Tidak | Tidak melihat menu Fleet sama sekali |

Menu Fleet hanya tampil bila `@can('fleet_view')`. Tidak ada permission turunan seperti `fleet_maintain`, `fleet_damage_add`, atau `fleet_claim_manage` — berbeda dengan pola modul lain (Sewa punya `rental_edit/confirm/return/cancel`, Keuangan punya `finance_invoice_add`, dst).

### [X] FLE-01 — Semua mutasi Fleet hanya dilindungi `fleet_view` — Sedang

- **Bukti:** tabel 2.1 — 24 rute tanpa satu pun middleware `can:` tambahan; kontras dengan Sewa (`can:rental_edit` untuk extension, `can:fine_pay` untuk bayar denda) dan Finance (`can:fine_pay`, `can:finance_invoice_add`).
- **Dampak:** setiap pemegang `fleet_view` (termasuk STAFF dan VIEWER-peran-kustom yang diberi fleet_view) dapat menandai maintenance selesai, mengubah status klaim hingga `paid` (memancarkan jurnal), **menghapus foto kerusakan**, dan menagihkan penyewa. Halaman Sewa pun membuka aksi return/confirm pada STAFF, tetapi di sana gate per-aksi ada; di Fleet tidak ada lapisan kedua.
- **Risiko khusus:** `claimUpdateStatus` → `paid` memicu `postQuietly` jurnal pencairan dana (FLE-09). Aksi berdampak akuntansi ini setara level `fine_pay`/`finance_refund_add` di modul lain, tetapi kini setara hanya `fleet_view`.
- **Perbaikan:** tetapkan permission aksi (mis. `fleet_maintain`, `fleet_damage_add`, `fleet_damage_manage`, `fleet_claim_manage`, `fleet_bill_renter`), pasang pada rute, selaraskan tombol UI (`@can`), dan tambahkan ke seeder role sesuai kebijakan.
- **Batas bukti:** tidak ada bukti penyalahgunaan; ini kelemahan desain akses yang sama polanya dengan temuan izin cetak pada audit keuangan §2.2.

---

## 4. INTEGRITAS DATA DAN KONSISTENSI STATUS

### [X] FLE-02 — Kontrak status kerusakan rusak di tiga lapisan — Tinggi

- **Bukti skema:** `2026_09_03_100015_create_tr_damage_report_table.php:23` — enum `['reported','assessment','repair_in_progress','repaired','claimed_insurance','written_off']`.
- **Bukti controller:** `FleetController::damageUpdateStatus` memvalidasi `'required|in:reported,inspected,approved,in_repair,repaired,rejected,closed'` — hanya `reported` dan `repaired` yang beririsan dengan enum DB. **Menyimpan `inspected`, `approved`, `in_repair`, `rejected`, atau `closed` gagal di level database** pada MySQL strict (atau tersimpan sebagai string kosong pada mode non-strict), padahal respons sudah HTTP 200 `status:true`.
- **Bukti UI:** form ubah status `fleet/damage/show.blade.php` memakai daftar yang sama dengan controller (subalih berbeda dengan DB), sedangkan `damage-status-badge` memetakan daftar yang sama dengan **database** (subalih berbeda dengan controller/UI). Badge dan dropdown tidak pernah menampilkan set yang sama.
- **Dampak:** alur kerja inspeksi → perbaikan → klaim/penulisan tidak dapat dieksekusi nyata; pengguna menerima "Status kerusakan diperbarui" sementara data tidak berubah (atau transaksi gagal di DB). Laporan tab klaim (`ReportController`) yang mengharapkan `assessment/repair_in_progress/...` tidak akan pernah terisi dari input UI.
- **Perbaikan:** putuskan satu set status kanonik, migrasi enum DB bila perlu, sinkronkan validasi controller + dropdown + badge + laporan + test (`ModuleAccessGatesTest:488-576` sudah memakai set DB).
- **Batas bukti:** perilaku MySQL strict diasumsikan dari skema; eksekusi nyata pada MySQL produksi tidak diuji (test memakai SQLite yang longgar terhadap enum).

### [X] FLE-03 — Skema maintenance tidak menandai kendaraan aktif dan `maintenanceUpdate` menimpanya membuta — Tinggi

- **Bukti:** `maintenanceUpdate` (baris `150-158`): bila status baru `in_progress` → kendaraan dipaksa `maintenance`; bila `completed` → dipaksa `available`. Tidak ada pemeriksaan `activeRental` pada kendaraan.
- **Dampak:** unit yang sedang disewakan (`status=rented`, ada `tr_rental` `ongoing/reserved`) dapat berubah `available` oleh penyelesaian maintenance yang salah kendaraan — membuka jendela double-booking karena `assertVehicleFree` di modul Sewa mengandalkan jendela tanggal, sementara UI create sewa mengandalkan `status=available`. Sebaliknya, setiap update maintenance `in_progress` memaksa kendaraan `maintenance` walau jadwal itu hanya catatan bengkel.
- **Perbaikan:** state machine kendaraan: `completed` hanya boleh mengembalikan kendaraan yang sebelumnya `maintenance` (periksa sebelum menulis); tolak transisi bila kendaraan punya rental aktif; catat perubahan status kendaraan pada catatan maintenance.
- **Batas bukti:** tidak ada bukti kejadian; ini kelemahan jaminan kode.

### [X] FLE-04 — Perubahan status maintenance dan kendaraan tidak atomik — Tinggi

- **Bukti:** `maintenanceUpdate` menjalankan `$item->vehicle->update(...)` dan `$item->update($validated)` **tanpa `DB::transaction`** (dan tanpa try/catch yang rollback — exception vehicle gagal menggagalkan status maintenance yang sudah/akan ditulis). `maintenanceComplete` juga memperbarui dua tabel tanpa transaksi.
- **Dampak:** kegagalan di tengah menulis meninggalkan status maintenance `completed` dengan kendaraan masih `maintenance`, atau sebaliknya — antrean kendaraan siap sewa menjadi tidak tepercaya.
- **Perbaikan:** bungkus kedua aksi dalam `DB::transaction` seperti pola RentalController (`$inTx` + rollback).

### [X] FLE-05 — `maintenanceReschedule` tidak mengecek bentrokan dan tidak memvalidasi hubungan tanggal — Sedang

- **Bukti:** `maintenanceReschedule` hanya memvalidasi `date`, lalu menimpa `scheduled_date` dan memaksa status `scheduled`. Tidak ada cek apakah kendaraan punya rental `ongoing/reserved` pada tanggal baru, tidak ada cek `actual_date`, dan reschedule pada jadwal `completed` mengubah kembali statusnya menjadi `scheduled` (menghapus riwayat penyelesaian dari daftar "terjadwal").
- **Dampak:** maintenance bisa dijadwalkan tepat saat unit sedang disewa; jadwal yang sudah selesai bisa "bangkit" tanpa jejak.
- **Perbaikan:** validasi status asal yang boleh di-reschedule (scheduled/overdue), larang tanggal < hari ini bila sudah ada actual, dan cek rental aktif kendaraan pada rentang baru.

### [X] FLE-06 — Tidak ada pengaman ganda penyelesaian/kendaraan saat aksi paralel — Sedang

- **Bukti:** `maintenanceComplete`, `damageUpdateStatus`, dan `claimUpdateStatus` memuat model tanpa `lockForUpdate` dan tanpa transaksi. `claimUpdateStatus` membaca `$previousStatus` lalu menulis; dua klik ganda dapat memicu dua jurnal pencairan bila keduanya membaca `previousStatus !== 'paid'` sebelum salah satunya menulis.
- **Dampak:** jurnal klaim ganda (kas masuk dicatat dua kali), status kendaraan lompat-belok. Probabilitas rendah, tetapi jurnal adalah efek yang sulit dibalik manual.
- **Perbaikan:** transaksi + lock baris pada aksi yang memicu efek berantai; untuk klaim, kunci baris klaim sebelum membaca status.

---

## 5. INTEGRASI KEUANGAN (PENAGIHAN KERUSAKAN & KLAIM)

### [X] FLE-07 — Deduplikasi tagihan kerusakan lewat LIKE deskripsi bebas — Tinggi

- **Bukti:** `damageBillRenter`: pencarian denda sebelumnya memakai `Fine::where('description','like','%kerusakan #'.$item->damage_id.'%')`. Deskripsi denda dibuat bebas oleh pengguna (`fineStore` menerima `description` string apa pun), sehingga teks "kerusakan #12" bisa muncul pada denda yang tidak berhubungan, atau berubah format dan gagal terdeteksi.
- **Dampak:** tagihan ganda (penyewa ditagih dua kali untuk kerusakan yang sama) atau penolakan tagihan sah. Identitas kewajiban tidak ada di skema — `tr_fine` tidak punya kolom `damage_id`.
- **Perbaikan:** tambahkan kolom `tr_fine.damage_id` (nullable, unique parsial bila didukung; unik di level aplikasi + index) dan deduplikasi berdasarkan kolom itu. Untuk data lama, deduplikasi LIKE boleh jadi fallback read-only sambil backfill.
- **Catatan integrasi:** remediasi keuangan 21 Sep 2026 menambah `tr_fine.paid_by/waived_by/waived_at` dan settlement denda terpusat; pembayaran denda yang dibuat Fleet kini otomatis mengikuti aturan baru (Payment `allocation=fine` + jurnal `4-2000`) karena lewat service yang sama — positif, tetapi tidak menyelesaikan identitas kewajiban ini.

### [X] FLE-08 — Tagihan kerusakan tidak terhubung ke invoice dan tidak memicu jurnal — Tinggi

- **Bukti:** `damageBillRenter` hanya membuat `Fine` `unpaid`. Tidak ada jurnal piutang (Dr 1-2100 / Cr 4-1200 atau 4-2000) saat denda diakui, dan denda tidak masuk `rental.total_amount`/invoice sewa terkait — berbeda dengan `extra_charge` yang sejak remediasi keuangan 21 Sep 2026 masuk total + invoice draft + jurnal.
- **Dampak:** piutang kerusakan tidak muncul di buku besar sampai dibayar; pelunasan denda oleh Keuangan menjurnal pendapatan denda, sehingga pengakuan kewajiban dan pengakuannya terpisah periode; laporan pendapatan "claims & fines" memakai sumber yang tidak sinkron.
- **Perbaikan:** putuskan titik pengakuan (saat ditagih = piutang + pendapatan, atau saat dibayar saja) dan posting jurnal sesuai; bila denda kerusakan hendak memengaruhi tagihan penyewa, buat dokumen penyesuaian/alokasi pada invoice, jangan menimpa invoice terbit (sesuai aturan snapshot FIN-07).

### [X] FLE-09 — Jurnal pencairan klaim `postQuietly` (gagal senyap) dan belum terpetakan ke damage/rental — Tinggi

- **Bukti:** `claimUpdateStatus` memanggil `$service->postQuietly(...)` untuk status `paid`. `postQuietly` menelan semua `Throwable` ke `Log::warning`. Dengan remediasi FIN-09, `post()` kini melempar bila akun `1-1200`/`4-3000` tidak terpetakan atau nominal nol — artinya kegagalan pemetaan COA pada produksi akan **berhenti terlihat sebagai header jurnal yang hilang**, bukan error.
- **Dampak:** pencairan klaim yang gagal dibukukan tidak terdeteksi kecuali seseorang membaca log; laporan pendapatan klaim asuransi (4-3000) bisa tidak merekonsiliasi dengan `tr_insurance_claim` berstatus `paid`.
- **Perbaikan:** jadikan hard post di dalam transaksi perubahan status (gagal = rollback status), seperti pola pembayaran sewa. Tambahkan nominal guard `approved_amount > 0` sebelum menulis status `paid`, bukan setelahnya.

### [X] FLE-10 — Estimasi/aktual biaya perbaikan tidak dibatasi skema — Rendah

- **Bukti:** `damageStore` memvalidasi `repair_cost_estimate` sebagai `nullable|numeric` tanpa `min:0`/`max`; `claimStore` `claim_amount` `required|numeric` tanpa batas; kolom DB `DECIMAL(12,2)` (maks `9.999.999.999,99`). Nilai negatif diterima; nilai > kapasitas gagal di DB dengan error mentah.
- **Dampak:** rendah untuk integritas (DB menolak overflow), tetapi nilai negatif merusak agregat laporan biaya perbaikan dan nilai tagihan.
- **Perbaikan:** `numeric|min:0|max:9999999999.99` pada ketiga kolom uang Fleet (`repair_cost_estimate`, `actual_repair_cost` (lewat UI show — lihat FLE-12), `claim_amount`, `approved_amount`).

---

## 6. VALIDASI DAN KETAHANAN OPERASIONAL

### [X] FLE-11 — Form kerusakan mengirim nilai enum yang tidak valid dan membiarkan kendaraan/sewa lepas — Sedang

- **Bukti:** dropdown `damage_type` di `fleet/damage/form.blade.php` mengirim `body|engine|interior|glass|tire|electrical|other`, sementara enum DB adalah `exterior|interior|mechanical|electrical|glass|tire|other` — `body` dan `engine` **gagal validasi DB**. Validasi controller `damageStore` memakai `'damage_type' => 'required|string'` (tidak `in:`), sehingga MySQL strict menolak saat insert dan pengguna melihat pesan exception mentah.
- **Bukti lain:** dropdown severity UI hanya `minor/moderate/severe` (DB juga punya `total_loss` — tidak dapat dipilih); `rental_id` dropdown dibangun dari 50 rental terakhir tanpa cek bahwa rental itu milik kendaraan terpilih (kerusakan bisa dikaitkan ke sewa unit lain).
- **Perbaikan:** `Rule::in` di controller sesuai enum DB; sinkronkan dropdown; filter opsi rental pada kendaraan terpilih (AJAX) atau validasi pasangan `vehicle_id+rental_id`.

### [X] FLE-12 — View klaim `show` tidak ada dan tidak ada jalur koreksi biaya aktual — Sedang

- **Bukti 1:** `FleetController::claimShow` merender `fleet.insurance-claim.show`, tetapi file tersebut tidak ada (verifikasi glob). Setiap akses `GET /fleet/insurance-claim/{id}` → `InvalidArgumentException: View not found` (HTTP 500). Tombol "LIHAT KLAIM" pada `fleet/damage/button_option.blade.php` dan kartu klaim pada `fleet/damage/show.blade.php` menaut ke rute ini.
- **Bukti 2:** tidak ada endpoint untuk mengisi `actual_repair_cost` (hanya tersimpan via seeder atau pengembalian), padahal `damageBillRenter` memakainya sebagai dasar tagihan. `damageUpdateStatus` tidak menerima input biaya.
- **Dampak:** fitur lihat klaim rusak penuh; penentuan nilai tagihan bergantung pada estimasi awal yang belum dikoreksi.
- **Perbaikan:** buat view `fleet/insurance-claim/show.blade.php` (detail klaim + riwayat status), tambahkan input `actual_repair_cost` pada halaman detail kerusakan (gate FLE-01), dan validasi `numeric|min:0`.

### [X] FLE-13 — Unggah/hapus foto tanpa batas jumlah, tanpa verifikasi kepemilikan status, dan file yatim — Sedang

- **Bukti unggah:** `damagePhotoUpload` menerima array foto, tiap file `image|max:5120`. Tidak ada batas jumlah file per laporan dan tidak ada throttle pada rute; pengguna dapat mengulang unggah file sama tanpa batas (tidak ada dedup hash).
- **Bukti hapus:** `damagePhotoDelete` menghapus `DamagePhoto` (record), tetapi **tidak menghapus file fisik** di `storage/app/public/damage-photos` — file menjadi yatim permanen. Tidak ada cek bahwa foto milik damage tertentu (ID foto global, cukup `fleet_view`).
- **Bukti state:** upload dibolehkan pada laporan berstatus apa pun, termasuk `closed`.
- **Dampak:** kebocoran storage bertahap; kemampuan menghapus bukti foto tanpa izin khusus (terkait FLE-01).
- **Perbaikan:** batasi jumlah foto per laporan, hapus file fisik saat record dihapus (atau gunakan `Storage::delete` + soft-delete bukti), kunci unggah pada status selain closed/rejected, dan pertimbangkan izin terpisah untuk hapus bukti.

---

## 7. DOKUMEN, FORMAT, DAN PENGALAMAN PENGGUNA

### [X] FLE-14 — Formatter nominal 0 desimal dan tanggal belum dilokalkan — Rendah

- **Bukti:** `maintenanceData` (`'Rp '.number_format($m->cost ?? 0, 0, ',', '.')`), `damageData` (`repair_cost`, `claim_amount`), `damageBillRenter` (pesan), dan `fleet/damage/show.blade.php` (estimasi/aktual) membulatkan ke 0 desimal, sedangkan DB menyimpan 2 desimal — pola yang sama sudah dikoreksi pada modul Keuangan (FIN-14) via `AppSettings::money()`.
- **Bukti tanggal:** `fleet/damage/show.blade.php` memakai `format('d F Y')` (nama bulan Inggris), bukan `translatedFormat` locale `id` yang dipakai dokumen keuangan.
- **Dampak:** rekonsiliasi nominal dokumen layar dengan transaksi kehilangan sen; konsistensi bahasa dokumen tidak seragam antar modul.
- **Perbaikan:** pakai `AppSettings::money()` dan `locale('id')->translatedFormat('d F Y')` pada seluruh tampilan Fleet.

### Catatan UX/maintainability, bukan temuan tersendiri

- Aksi maintenance (SELESAI/EDIT) muncul hanya setelah seleksi baris — pola sama dengan modul lain; bukan kerusakan.
- `maintenanceButtonOption`/`damageButtonOption`/`claimCreate` tidak memvalidasi skalar `id` (pola FIN-15) — masuk cakupan perbaikan validasi global, dicatat agar tidak hilang. *(Diremediasi 21 Sep 2026: validasi `id` integer pada endpoint button-option.)*
- Tidak ada filter status/periode pada tabel maintenance (kerusakan punya filter status) — enhancement, bukan celah validasi.
- Tombol "AJUKAN KLAIM" tampil untuk semua pemegang `fleet_view`; pembatasan aksi masuk FLE-01.

---

## 8. PENGUJIAN DAN BATAS VERIFIKASI

### Yang ditemukan pada sumber pengujian

| File | Cakupan relevan | Batas |
|---|---|---|
| `tests/Feature/ModuleAccessGatesTest.php:65,291` | VIEWER ditolak `/fleet/damage`; gate menu | Hanya akses baca; tidak ada tes mutasi Fleet |
| `tests/Feature/ModuleAccessGatesTest.php:474-576` | Data maintenance/damage sintetis untuk menguji **Laporan** (ekspor klaim, utilisasi) | Menguji ReportController, bukan FleetController |
| `tests/Feature/RentalIntegrityTest.php` | Alur sewa; menyentuh status kendaraan `maintenance` setelah return | Tidak menguji aksi maintenance Fleet |
| `tests/app-enhancements.test.js` | Frontend global | Tidak menguji Fleet |

**Tidak ditemukan pengujian langsung** untuk `maintenanceStore/Update/Complete/Reschedule`, `damageStore/UpdateStatus/BillRenter`, foto upload/delete, `claimStore/UpdateStatus`, maupun deduplikasi tagihan kerusakan. Enum status kerusakan yang rusak (FLE-02) lolos karena tidak ada tes yang menyentuh `damageUpdateStatus`.

**Perintah proyek untuk implementasi/regresi:**

```powershell
composer test
php artisan test --filter=FleetMaintenanceTest
php vendor/bin/pint --test
node --test tests/app-enhancements.test.js
```

**Rencana regresi setelah perbaikan:**

1. Matriks izin: VIEWER/STAFF tanpa permission aksi ditolak pada store/update/complete/status/bill/photo-delete; pemegang izin lulus.
2. State machine maintenance: scheduled → in_progress → completed; kendaraan `rented` tidak pernah dipaksa `available`; transaksi rollback saat kegagalan.
3. Enum status kerusakan: seluruh status UI dapat disimpan pada MySQL strict; badge dan dropdown menampilkan set yang sama.
4. Tagihan kerusakan: satu tagihan per damage (duplikat ditolak), jurnal pengakuan sesuai kebijakan, nominal sesuai `actual_repair_cost`.
5. Klaim: nomor unik, satu klaim per damage (DB unique), status `closed` valid atau dihapus dari UI, jurnal pencairan hard-post dan rollback bersama status.
6. Foto: batas jumlah, file fisik ikut terhapus, unggah ditolak pada laporan closed.
7. Validasi: nilai uang negatif/overflow ditolak; `damage_type`/`severity` sesuai enum.

---

## 9. PROTEKSI YANG SUDAH ADA

- **Gate menu dan server:** menu Fleet di `layouts/menu.blade.php:131-149` dijaga `@can('fleet_view')`; seluruh rute memuat `can:fleet_view` + `auth` + `two_factor` (warisan remediasi FIN-01) — terverifikasi `route:list -v`.
- **CSRF:** semua form memakai `@csrf`; AJAX mengirim `_token`.
- **Validasi exists:** `vehicle_id`, `workshop_id`, `maintenance_type_id`, `rental_id`, `damage_id` memakai rule `exists` pada tabel yang benar.
- **Kardinalitas klaim:** `tr_insurance_claim.damage_id` **unique** di database — satu klaim per kerusakan dijamin mesin; UI juga menyembunyikan tombol klaim bila sudah ada.
- **Nomor klaim:** `gen_number` dengan pola lock NumberTrait + `claim_number` unique.
- **Upload:** validasi mime `image` + ukuran 5 MB; multi-file dengan fallback tunggal (audit 2.4).
- **Escaping tabel:** badge status dirender sebagai komponen view, bukan string HTML dari input pengguna; `rawColumns` hanya berisi HTML badge internal.
- **Foreign key:** kerusakan terkait rental `cascadeOnDelete`, kendaraan `restrictOnDelete` — riwayat kerusakan tidak menghapus unit.

---

## 10. MATRIKS PRIORITAS ACTION PLAN

| ID | Pekerjaan | Prioritas | Area utama | Status |
|---|---|---|---|---|
| FLE-01 | Permission aksi per rute Fleet | Sedang | Routing/keamanan | [X] Selesai 21 Sep 2026 |
| FLE-02 | Satukan enum status kerusakan (DB+controller+UI+badge+laporan) | Tinggi | Skema/Controller/View | [X] Selesai 21 Sep 2026 |
| FLE-03 | State machine kendaraan pada maintenance (larang menimpa rented) | Tinggi | FleetController | [X] Selesai 21 Sep 2026 |
| FLE-04 | Transaksi DB pada update maintenance + kendaraan | Tinggi | FleetController | [X] Selesai 21 Sep 2026 |
| FLE-05 | Validasi reschedule (rental aktif, status asal, tanggal) | Sedang | FleetController | [X] Selesai 21 Sep 2026 |
| FLE-06 | Transaksi + lock pada aksi berantai (status klaim/kendaraan) | Sedang | FleetController | [X] Selesai 21 Sep 2026 |
| FLE-07 | Identitas kewajiban: `tr_fine.damage_id` untuk dedup tagihan | Tinggi | Skema + Fleet/Finance | [X] Selesai 21 Sep 2026 |
| FLE-08 | Titik pengakuan piutang kerusakan + jurnal | Tinggi | Fleet + Accounting | [X] Selesai 21 Sep 2026 |
| FLE-09 | Jurnal klaim hard-post dalam transaksi + guard nominal | Tinggi | Fleet + Accounting | [X] Selesai 21 Sep 2026 |
| FLE-10 | Batas nominal uang pada validasi Fleet | Rendah | Validasi | [X] Selesai 21 Sep 2026 |
| FLE-11 | Rule::in enum kerusakan + sinkron dropdown + pasangan rental-kendaraan | Sedang | Controller/View | [X] Selesai 21 Sep 2026 |
| FLE-12 | Buat view klaim show + input biaya aktual | Sedang | View/Controller | [X] Selesai 21 Sep 2026 |
| FLE-13 | Batas foto, hapus file fisik, kunci status closed, izin hapus | Sedang | Controller/Storage | [X] Selesai 21 Sep 2026 |
| FLE-14 | Formatter uang 2 desimal + tanggal Indonesia | Rendah | View/DataTables | [X] Selesai 21 Sep 2026 |

---

## 11. KEPUTUSAN BISNIS DAN URUTAN PERBAIKAN

### Keputusan yang perlu ditetapkan

- Set status kerusakan kanonik: pertahankan enum database (assessment/repair_in_progress/claimed_insurance/written_off) atau revisi ke set yang dipakai UI saat ini? (FLE-02)
- Siapa yang boleh menagih penyewa dan mengubah status klaim hingga `paid`? Perlukah permission terpisah untuk hapus foto bukti? (FLE-01)
- Kapan piutang kerusakan diakui — saat ditagih (piutang+pendapatan) atau saat dibayar saja? Apakah denda kerusakan memengaruhi invoice sewa atau berdiri sendiri? (FLE-07/FLE-08)
- Apakah pencairan klaim wajib hard-post (gagal status = rollback) atau diterima sebagai proses manual belakangan? (FLE-09)
- Apakah maintenance boleh dijadwalkan pada unit yang sedang disewa (mis. servis pencegahan setelah kembali), dan siapa yang menyetujui? (FLE-05)
- Kebijakan retensi foto kerusakan (bukti klaim): berapa lama, siapa yang boleh menghapus? (FLE-13)

### Urutan implementasi yang disarankan

1. **Kontrak status dulu** (FLE-02): tanpa satu set status, validasi, laporan, dan pengujian tidak bisa dibangun di atasnya. Ikutkan `Rule::in` (FLE-11) karena satu akar masalah.
2. **Integritas kendaraan** (FLE-03, FLE-04, FLE-05): transaksi + state machine kendaraan; prasyarat kepercayaan antarmodul Sewa ↔ Fleet.
3. **Integrasi keuangan** (FLE-07, FLE-08, FLE-09): skema identitas tagihan, kebijakan pengakuan, hard-post jurnal — selaraskan dengan service settlement denda hasil remediasi keuangan 21 Sep 2026 agar dua jalur tetap satu perilaku.
4. **Akses & dokumen** (FLE-01, FLE-12, FLE-13, FLE-14): permission aksi, view klaim yang hilang, kebijakan foto, formatter.
5. **Regresi** (bagian 8): jalankan matriks di atas pada database pengujian terisolasi; verifikasi perilaku MySQL strict untuk enum (SQLite uji tidak mewakili).
6. **Data existing**: setelah kebijakan disepakati, nilai rekonsiliasi kerusakan yang sudah ditagih/klaim berstatus tidak valid — jangan koreksi massal hanya dari temuan statis.

**Kesimpulan:** 14 temuan (6 tinggi, 6 sedang, 2 rendah) — **seluruhnya telah diremediasi dan diverifikasi regresi pada 21 Sep 2026** (lihat bagian Status Remediasi). Tidak ada celah akses publik dan gate dasar berfungsi; risiko terbesar adalah **kontrak status yang rusak** (FLE-02) yang membuat alur kerja inspeksi-perbaikan-klaim tidak dapat dipercaya, **identitas tagihan kerusakan yang bebas** (FLE-07/FLE-08) yang berdampak uang, dan **jurnal klaim yang gagal senyap** (FLE-09). Temuan ini tidak menyimpulkan kejadian kerugian; koreksi disarankan mengikuti urutan di atas dengan keputusan bisnis pada tiap tahap.
