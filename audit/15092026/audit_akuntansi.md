# AUDIT — MENU AKUNTANSI
## BaskaDrive Rental ERP — Jurnal Umum, Jurnal Manual, Buku Besar & Laporan Keuangan

**Tanggal audit:** 21 September 2026
**Lokasi dokumen:** `audit/15092026/audit_akuntansi.md`
**Cakupan utama:** menu **Akuntansi** prefix `/accounting` — Jurnal Umum (+export CSV), Jurnal Manual, Buku Besar (+detail per akun), Laporan Laba Rugi / Neraca / Arus Kas; termasuk `AccountingService` (mesin posting & agregasi) yang dipakai lintas modul (Sewa, Keuangan, Fleet).
**Metode:** penelusuran statis rute, controller, model, skema/migrasi, seeder COA, view Blade, JavaScript form jurnal manual, service akuntansi, dan pengujian; disertai pelacakan seluruh pemanggil `post()`/`postQuietly()` lintas modul. Satu verifikasi runtime rute dilakukan untuk keberadaan placeholder parameter.
**Legenda:** `[ ]` = terbuka; `[X]` = sudah diperbaiki dan diverifikasi. Total temuan: **12** (AKN-01..AKN-12): 2 kritis, 5 tinggi, 3 sedang, 2 rendah.

> **Status remediasi (22 Sep 2026):** seluruh temuan AKN-01..AKN-12 telah diperbaiki dan diverifikasi regresi (`AccountingModuleTest` — 20 test; full suite 128 test / 7.755 assertions hijau). Lihat bagian Klarifikasi Penutupan di akhir dokumen.

> Audit ini statis pada snapshot kode saat ini. Perilaku MySQL produksi (tutup buku berskala besar, konkurensi posting) tidak diuji. Modul akuntansi adalah **sumber kebenaran keuangan lintas modul** — temuan di sini berdampak ke semua jurnal otomatis dari Sewa, Keuangan, dan Fleet.

---

## DAFTAR ISI

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Inventaris Rute dan Tampilan](#2-inventaris-rute-dan-tampilan)
3. [Posting Jurnal (Service Bersama)](#3-posting-jurnal-service-bersama)
4. [Jurnal Manual](#4-jurnal-manual)
5. [Buku Besar dan Jurnal Umum (Baca & Ekspor)](#5-buku-besar-dan-jurnal-umum-baca--ekspor)
6. [Laporan Keuangan Formal](#6-laporan-keuangan-formal)
7. [Pengujian dan Batas Verifikasi](#7-pengujian-dan-batas-verifikasi)
8. [Proteksi yang Sudah Ada](#8-proteksi-yang-sudah-ada)
9. [Matriks Prioritas Action Plan](#9-matriks-prioritas-action-plan)
10. [Keputusan Bisnis dan Urutan Perbaikan](#10-keputusan-bisnis-dan-urutan-perbaikan)

---

## 1. RINGKASAN EKSEKUTIF

Modul Akuntansi dibangun di atas `AccountingService::post()` yang sudah kuat setelah remediasi FIN-09 (menolak baris akun kosong dan jurnal tanpa detail nonnol) serta guard tutup buku `assertPeriodOpen`. Namun lapisan penyajiannya retak: **tombol EXPORT CSV jurnal melempar error 500** karena rute kehilangan placeholder `{id}`, **seluruh nilai jurnal ditampilkan 0 desimal** (sen hilang, rekonsiliasi dokumen tidak bisa), **Neraca tidak seimbang** karena laba yang dimasukkan ke ekuitas hanya tahun berjalan sementara aset dihitung sepanjang masa, dan **saldo buku besar memakai rumus debit−kredit mentah** untuk semua tipe akun sehingga pendapatan/liabilitas tampil negatif.

| Prioritas | Jumlah | Temuan |
|---|---:|---|
| Kritis | 2 | AKN-01, AKN-02 |
| Tinggi | 5 | AKN-03, AKN-04, AKN-05, AKN-06, AKN-07 |
| Sedang | 3 | AKN-08, AKN-09, AKN-10 |
| Rendah | 2 | AKN-11, AKN-12 |
| Total | **12** | AKN-01..AKN-12 |

**Catatan penilaian:** temuan berbasis kode, bukan bukti kerugian. AKN-03/ACN-10 memerlukan keputusan presentasi keuangan (posisi laba, klasifikasi arus kas) sebelum/menyertakan implementasi.

---

## 2. INVENTARIS RUTE DAN TAMPILAN

### 2.1 Rute modul Akuntansi

Sumber: `routes/rental.php` MODUL 6. Semua rute mewarisi `web + auth + two_factor + can:accounting_view`.

| HTTP | URI | Action | Nama rute | Izin tambahan |
|---|---|---|---|---|
| GET | `/accounting/journal` | `journalIndex` | `accounting.journal.index` | — |
| GET | `/accounting/journal/get-data` | `journalData` | `accounting.journal.data` | — |
| GET | `/accounting/journal/get-button-option` | `journalButtonOption` | `accounting.journal.button-option` | — |
| GET | `/accounting/journal/export` | `journalExport` | `accounting.journal.export` | **tanpa `{id}`, tanpa `accounting_export`** (AKN-01, AKN-11) |
| GET | `/accounting/journal/{id}` | `journalShow` | `accounting.journal.show` | — |
| GET | `/accounting/manual-journal` | `manualJournalCreate` | `accounting.manual-journal.create` | — |
| POST | `/accounting/manual-journal/store` | `manualJournalStore` | `accounting.manual-journal.store` | **tanpa izin tulis** (AKN-07) |
| GET | `/accounting/manual-journal/validate` | `manualJournalValidate` | `accounting.manual-journal.validate` | — |
| GET | `/accounting/ledger` | `ledgerIndex` | `accounting.ledger.index` | — |
| GET | `/accounting/ledger/get-data` | `ledgerData` | `accounting.ledger.data` | — |
| GET | `/accounting/ledger/get-button-option` | `ledgerButtonOption` | `accounting.ledger.button-option` | — |
| GET | `/accounting/ledger/{accountId}` | `ledgerDetail` | `accounting.ledger.detail` | — |
| GET | `/accounting/statement/income` | `incomeStatement` | `accounting.statement.income` | — |
| GET | `/accounting/statement/balance-sheet` | `balanceSheet` | `accounting.statement.balance` | — |
| GET | `/accounting/statement/cash-flow` | `cashFlow` | `accounting.statement.cashflow` | — |

Permission `accounting_export` **sudah didefinisikan** di PermissionSeeder dan diberikan ke SUPERADMIN/ADMIN/MANAGER, tetapi **tidak pernah dipasang ke satu rute pun** di modul ini.

### 2.2 Tampilan

| File | Fungsi | Catatan |
|---|---|---|
| `accounting/journal/index.blade.php` | DataTables jurnal + filter tipe | filter mengirim `manual/auto/adjustment/closing` — `auto`/`closing` **bukan nilai enum DB** (AKN-06) |
| `accounting/journal/show.blade.php` | Detail jurnal + entri + export | nominal 0 desimal (AKN-02) |
| `accounting/journal/button_option.blade.php` | Show + Export CSV | menaut rute export rusak (AKN-01) |
| `accounting/ledger/index.blade.php` | DataTables akun + saldo | filter menyediakan `revenue` yang bukan enum (AKN-06); saldo salah tanda (AKN-04) |
| `accounting/ledger/detail.blade.php` | Mutasi + saldo berjalan per akun | saldo berjalan rumus mentah (AKN-04) |
| `accounting/manual-journal/form.blade.php` | Form jurnal manual dinamis | validasi sisi per baris tidak ada di klien (AKN-07) |
| `accounting/statements/income-statement.blade.php` | Laba rugi | nominal 0 desimal (AKN-02) |
| `accounting/statements/balance-sheet.blade.php` | Neraca | tidak seimbang (AKN-03) |
| `accounting/statements/cash-flow.blade.php` | Arus kas | klasifikasi kategori (AKN-10) |

### 2.3 Mesin posting bersama

`AccountingService::post()` dipanggil dari: `FineSettlementService` (pembayaran/waive/reversal denda, akrual kerusakan), `RentalSettlementService` (pembayaran, refund, extra charge, piutang invoice), `FleetController` (pencairan klaim), dan `manualJournalStore`. Perubahan perilaku `post()` berdampak ke semua jalur tersebut.

---

## 3. POSTING JURNAL (SERVICE BERSAMA)

### [X] AKN-05 — N+1 query pada penyajian jurnal dan buku besar — Tinggi (selesai 22 Sep 2026: `withSum` agregasi SQL pada journalData & ledgerData)

- **Bukti:** `journalData` memuat `Journal::with(['creator','details'])` lalu menghitung `$j->details->sum('debit')` per baris di PHP; `ledgerData` memuat `Coa::with(['parent','journalDetails'])` lalu menjumlahkan mutasi per akun di PHP. Dengan server-side DataTables namun query tanpa agregasi SQL, setiap halaman memindahkan seluruh detail ke memori. `RentalErpCompleteSeeder` menanam jurnal 12 bulan — volume tumbuh linear dengan transaksi.
- **Dampak:** halaman jurnal/buku besar melambat proporsional dengan volume; pada produksi berbulan-bulan menjadi bottleneck.
- **Perbaikan:** agregasi di SQL (`leftJoin` subquery `SUM(debit), SUM(credit)` atau `withSum('details','debit')`); ledger memakai pola agregasi `accountBalances()` yang sudah ada di service.

### Catatan proteksi yang sudah benar di service

- `post()` menolak baris akun kosong & jurnal tanpa detail nonnol (FIN-09), menolak ketidakseimbangan ≥ 0,01, dan menolak transaksi di bawah tanggal tutup buku (`assertPeriodOpen`) — semua pemanggil lintas modul termanfaatkan dengan baik.
- `tr_journal.reference_number` unique di DB — nomor ganda otomatis ditolak mesin.

---

## 4. JURNAL MANUAL

### [X] AKN-07 — Entri tidak valid diterima: satu baris dua sisi, baris nol semua, nominal tanpa batas — Tinggi (selesai 22 Sep 2026: validasi XOR per baris, batas DECIMAL(15,2), akun wajib daun & aktif, referensi ≤ 50)

- **Bukti:** `manualJournalStore` hanya menjumlahkan kolom `debit` dan `credit` lalu membandingkan total. Konsekuensi:
  1. Satu baris boleh berisi debit=500 **dan** credit=300 bersamaan — total keduanya naik 300 sehingga jurnal tampak "seimbang" padahal barisnya tidak valid secara akuntansi (satu baris = satu sisi).
  2. Jurnal dengan dua baris `0/0` lolos (`min:2` baris terpenuhi, total seimbang 0-0) — header jurnal tanpa substansi.
  3. `entries.*.debit/credit` memakai `numeric` tanpa `min:0`/`max` — nilai negatif diterima dan nilai > DECIMAL(15,2) meledak sebagai error DB mentah.
  4. `reference_number` tanpa `max:50` padahal kolom `varchar(50)` unique — input panjang menghasilkan SQL error 500, bukan pesan validasi.
  5. Tidak ada pemeriksaan bahwa akun aktif (`is_active`) — akun nonaktif bisa dipakai mutasi via request langsung (form hanya menyembunyikannya bila punya anak).
- **Dampak:** buku besar berisi entri cacat yang "seimbang" namun tidak bermakna; koreksinya mahal karena tidak ada hapus/void jurnal.
- **Perbaikan:** validasi per baris: XOR debit/kredit (tepat satu sisi bernilai), nilai > 0, batas atas 9.999.999.999.999,99 (kapasitas DECIMAL(15,2)), minimal satu baris debit dan satu baris kredit; `reference_number` `max:50`; akun wajib aktif & bukan akun induk (rule kustom); pesan validasi terstruktur, bukan exception.
- **Batas bukti:** form klien (`form.blade.php`) hanya menghitung total — tidak memvalidasi sisi per baris; injeksi manual POST lolos UI.

### [X] AKN-08 — Pola transaksi beracun: validasi di dalam try + `DB::rollback()` — Sedang (selesai 22 Sep 2026: validasi di luar transaksi + closure `DB::transaction`; regresi anti-racun koneksi tersedia)

- **Bukti:** `manualJournalStore` memanggil `$request->validate()` **di dalam** blok try yang catch-nya menjalankan `DB::rollback()`. `ValidationException` pada level transaksi 0 (atau pada transaksi wrapping lain) memanggil rollback melewati level awal — pada koneksi yang sama, level menjadi −1 dan transaksi berikutnya di koneksi itu gagal inisiasi ("There is already an transaction started" / silent misbehaviour). Pola identik sudah terbukti menimbulkan kegagalan beruntun pada FleetController (remediasi FLE, 21 Sep 2026) dan diperbaiki dengan pindah validasi ke luar try + `DB::transaction` closure.
- **Dampak:** satu submit tidak valid berpotensi merusak request berikutnya pada koneksi persist/pool yang sama; sulit direproduksi dan tampak acak.
- **Perbaikan:** validasi & pengecekan akun induk **sebelum** transaksi; penulisan memakai `DB::transaction(function(){...})`; catch hanya menangani exception domain.

### Catatan desain yang baik

- Cek akun induk (yang punya anak) dilarang menjadi akun mutasi — menjaga prinsip "hanya daun yang bermutasi" sehingga saldo parent tidak dihitung ganda.
- `manualJournalValidate` endpoint pratinjau keseimbangan tersedia untuk klien.

---

## 5. BUKU BESAR DAN JURNAL UMUM (BACA & EKSPOR)

### [X] AKN-01 — Rute export CSV jurnal kehilangan placeholder `{id}` → error 500 — Kritis (selesai 22 Sep 2026: rute `/journal/{id}/export` + regresi unduhan)

- **Bukti:** `routes/rental.php` mendefinisikan `Route::get('/export', [AccountingController::class, 'journalExport'])->name('export')` — **tanpa parameter**. Controller `journalExport($id)` mewajibkan argumen. View `button_option.blade.php` dan `show.blade.php` memanggil `route('accounting.journal.export', $item->journal_id)` — parameter tidak punya tempat sehingga menjadi query string, argumen `$id` tidak pernah terisi → `ArgumentCountError` (HTTP 500) pada **setiap** klik EXPORT CSV.
- **Dampak:** fitur ekspor jurnal mati total; tidak ada jalur alternatif ekspor per jurnal.
- **Perbaikan:** ubah rute menjadi `Route::get('/{id}/export', ...)` (ditempatkan **sebelum** `/{id}` show agar tidak tertelan), tambahkan `can:accounting_export` + throttle (AKN-11), dan uji regresi unduhan.

### [X] AKN-02 — Seluruh nominal akuntansi tampil 0 desimal; ekspor CSV tanpa standar — Kritis (selesai 22 Sep 2026: `AppSettings::money()` dua desimal di seluruh penyaji + CSV BOM/`;` dua desimal)

- **Bukti:** `journalData` (`'Rp '.number_format(..., 0)`), `journal/show`, `ledgerData`, `ledger/detail`, ketiga statement, semua membulatkan ke 0 desimal; `journalExport` menulis nilai mentah `d->debit` tanpa format. Padahal `tr_journal_detail.debit/credit` bertipe `DECIMAL(15,2)` dan modul Keuangan/Laporan sudah distandarkan ke dua desimal (FIN-14, `AppSettings::money()`).
- **Dampak:** sen hilang dari layar — rekonsiliasi kas bank vs jurnal tidak bisa dilakukan petugas; CSV mentah tidak seragam dengan modul Laporan (BOM UTF-8 + pemisah `;` + dua desimal koma).
- **Perbaikan:** semua penyaji memakai `AppSettings::money()`; CSV selaras standar modul Laporan (`\xEF\xBB\xBF` + `;` + format dua desimal `,` desimal).

### [X] AKN-04 — Saldo buku besar memakai debit−kredit mentah untuk semua tipe akun — Tinggi (selesai 22 Sep 2026: saldo normal via `AccountingService::signedBalance`, saldo berjalan & kolom akun induk "—")

- **Bukti:** `ledgerData` menghitung `debit - credit` tanpa memperhatikan `account_type`; `ledgerDetail` menghitung saldo berjalan dengan rumus sama. Untuk akun `income`/`liability`/`equity` (saldo normal kredit), saldo tampil **negatif**; akun dengan saldo kredit tampil `-Rp` dan saldo berjalan menurun padahal sehat.
- **Dampak:** pembacaan buku besar menyesatkan; total kolom saldo tidak dapat direkonsiliasi dengan neraca.
- **Perbaikan:** saldo bertanda sesuai saldo normal: `asset/expense` = debit−kredit; `liability/equity/income` = kredit−debit; saldo berjalan detail mengikuti saldo normal akun; tampilkan juga saldo akhir total di header.

### [X] AKN-06 — Filter tipe dan rentang tanggal tidak divalidasi; opsi UI tidak cocok enum — Tinggi (selesai 22 Sep 2026: `Rule::in` dari kontrak enum, dropdown disinkronkan, `statementRange` pola FIN-15 → 422)

- **Bukti:**
  1. `ledgerData` menerima `type` bebas → `where('account_type', $request->type)`; dropdown UI menyediakan `revenue` padahal enum DB adalah `income` — opsi "Pendapatan" selalu menghasilkan daftar kosong.
  2. `journalData` menerima `type` bebas; dropdown UI menyediakan `auto` dan `closing` yang **bukan** anggota enum `journal_type` (`rental,payment,refund,maintenance,fine,adjustment,manual,insurance`) — dua opsi filter mati.
  3. `statementRange` mem-parse tanggal tanpa validasi: input `start_date=invalid` melempar exception 500; `start > end` diterima; rentang tak dibatasi (pola FIN-15 pada modul Laporan sudah menuntaskan masalah sama dengan validasi ketat).
- **Dampak:** filter menipu (opsi mati), error 500 dari input tanggal, perilaku tidak konsisten antar modul.
- **Perbaikan:** `Rule::in` sesuai enum pada kedua endpoint data; dropdown disinkronkan; `statementRange` divalidasi (`date`, `after_or_equal`, rentang maksimum yang sama dengan modul Laporan) dan mengembalikan 422 bukan 500.

### [X] AKN-09 — Ekspor CSV jurnal tidak selaras standar modul Laporan — Sedang (selesai 22 Sep 2026: BOM UTF-8 + pemisah `;` + format Indonesia, nama file `jurnal_{ref}_{Ymd}.csv`)

- **Bukti:** `journalExport` memakai `fputcsv` default (koma, tanpa BOM) sementara modul Laporan (`ReportController`, hasil remediasi audit laporan) memakai BOM UTF-8 + pemisah `;` + format Indonesia — standar buka-langsung-di-Excel.
- **Dampak:** file dari dua fitur ekspor yang sama berperilaku beda; di Excel locale Indonesia kolom tergeser.
- **Perbaikan:** seluruh CSV modul Akuntansi mengikuti konvensi modul Laporan; nama file konsisten `jurnal_{ref}_{Ymd}.csv`.

---

## 6. LAPORAN KEUANGAN FORMAL

### [X] AKN-03 — Neraca tidak seimbang: laba tahun berjalan vs akumulasi sepanjang masa — Tinggi (selesai 22 Sep 2026: laba akumulasi ke ekuitas + baris informasi laba tahun berjalan/lama; regresi A = L + E lintas tahun)

- **Bukti:** `balanceSheet()` menghitung aset/kewajiban/ekuitas **akumulasi sejak 2000-01-01**, lalu `total_equity_with_profit = equity + pnl['net_profit']` di mana `pnl` dihitung **sejak 1 Januari tahun berjalan saja**. Laba tahun-tahun sebelumnya (yang belum ditutup ke ekuitas karena tidak ada jurnal penutupan otomatis) **hilang** dari ekuitas → `Aset ≠ Kewajiban + Ekuitas` pada setiap tahun kedua dan seterusnya.
- **Dampak:** laporan posisi keuangan yang dipublikasikan seimbang hanya pada tahun pertama pemakaian; audit eksternal akan menolak.
- **Perbaikan:** profit yang ditambahkan ke ekuitas = laba **akumulasi sejak awal masa** hingga `asOf` (bukan YTD); profit tahun berjalan tetap ditampilkan sebagai baris informasi terpisah ("Laba Tahun Berjalan" vs "Laba Ditahan Tahun Lalu" implisit). Uji: buat jurnal pendapatan tahun lalu + tahun ini, aset = kewajiban+ekuitas harus berimbang.
- **Keputusan bisnis:** bila nanti ada fitur jurnal penutupan tahunan, rumus ini harus disesuaikan kembali — dicatat sebagai dependensi.

### [X] AKN-10 — Klasifikasi arus kas salah: pembayaran piutang masuk "investasi" — Sedang (selesai 22 Sep 2026: klasifikasi berbasis kode akun lawan via `classifyCashFlow`)

- **Bukti:** `cashFlow()` mengklasifikasi dari **tipe** akun lawan: `asset` → `investing`, `liability|equity` → `financing`, sisanya `operating`. Jurnal pembayaran piutang sewa (Dr Kas / Cr 1-2100 Piutang Sewa) — pola rutin denda kerusakan sejak remediasi FLE — memiliki lawan `asset` sehingga masuk **investing**, padahal secara PSAK/arus kas metode langsung itu arus kas **operasional**. Sebaliknya pelunasan utang jangka panjang dan pembelian aset tetap benar, tetapi piutang usaha salah kategori.
- **Dampak:** arus kas investasi menggelembung, operasional tampak lebih buruk dari kenyataan — dasar keputusan keliru.
- **Perbaikan:** klasifikasi berbasis kode akun lawan: `1-2xxx` (piutang) & `2-3xxx` (pendapatan diterima dimuka) → operating; `1-3xxx` (aset tetap) → investing; `2-2xxx` (utang jangka panjang) & `3-xxxx` (ekuitas) → financing; `4-xxxx`/`5-xxxx` → operating; fallback tipe akun bila kode tidak cocok.
- **Keputusan bisnis:** presentasi metode langsung vs tidak langsung — implementasi memakai mapping di atas sebagai default dan dokumentasikan.

### Catatan presentasi

- Laba rugi & neraca sudah memakai `signed_balance` berbasis tipe akun dengan benar; hanya arus kas & buku besar yang salah rumus.
- Semua statement 0 desimal (AKN-02) dan tanggal belum `translatedFormat` (AKN-12).

---

## 7. PENGUJIAN DAN BATAS VERIFIKASI

### Yang ada saat ini

| File | Cakupan akuntansi | Batas |
|---|---|---|
| `ModuleAccessGatesTest` | akses `accounting.journal.index` per role; 403 untuk VIEWER/STAFF | hanya akses halaman; tidak ada test mutasi/pembacaan data |
| `RentalIntegrityTest`, `FinanceSettlementTest`, `FleetActionsTest` | memverifikasi jurnal **sisi posting** (Dr/Cr per akun) | tidak menyentuh controller akuntansi |
| `tests/app-enhancements.test.js` | frontend global | tidak menguji form jurnal manual |

**Tidak ada pengujian** untuk: export CSV (bug AKN-01 lolos), jurnal manual end-to-end (AKN-07 lolos), neraca/arus kas/buku besar (AKN-03/04/10 lolos), filter invalid (AKN-06 lolos).

**Perintah proyek:**

```powershell
composer test
php artisan test --filter=AccountingModuleTest
php vendor/bin/pint --test
```

**Rencana regresi setelah perbaikan:**

1. Export CSV jurnal menghasilkan unduhan 200 dengan BOM, pemisah `;`, dua desimal, dan isi sesuai jurnal.
2. Jurnal manual: baris dua sisi ditolak; jurnal 0-0 ditolak; nominal negatif/overflow ditolak; referensi >50 ditolak; akun induk/nonaktif ditolak; seimbang tersimpan dan muncul di jurnal umum.
3. Neraca seimbang (A = L + E) dengan jurnal lintas dua tahun.
4. Buku besar: saldo pendapatan/liabilitas positif sesuai saldo normal; saldo berjalan berakhir tepat pada saldo total.
5. Arus kas: pembayaran piutang masuk operating; pembelian aset tetap masuk investing.
6. Filter/tanggal invalid → 422 dengan pesan; opsi dropdown selaras enum.
7. Izin: pemegang `accounting_view` tanpa `accounting_export` ditolak di export; throttling aktif.

---

## 8. PROTEKSI YANG SUDAH ADA

- **Gate modul:** seluruh rute membawa `can:accounting_view` + `auth` + `two_factor` (warisan FIN-01, terverifikasi `ModuleAccessGatesTest`).
- **Mesin posting:** `post()` menolak jurnal tak seimbang, baris tanpa akun, jurnal tanpa detail, dan transaksi di bawah tutup buku — dipakai semua modul (FIN-09).
- **Integritas DB:** `reference_number` unique; FK detail→jurnal `cascadeOnDelete` dan detail→akun `restrictOnDelete` (jurnal tidak bisa menggantung, akun bermutasi tidak bisa dihapus); `journal_type` enum.
- **Hierarki COA:** akun induk dilarang bermutasi (controller + form), menjaga agregasi saldo.
- **Tutup buku:** `assertPeriodOpen` berbasis `app_settings.accounting_closing_date` dengan cache.
- **Tidak ada rute hapus jurnal** — koreksi lewat jurnal balik, bukan penghapusan (praktik akuntansi yang benar).

---

## 9. MATRIKS PRIORITAS ACTION PLAN

| ID | Pekerjaan | Prioritas | Area utama | Status |
|---|---|---|---|---|
| AKN-01 | Rute export jurnal `/{id}/export` + izin | Kritis | Routing | [X] Selesai (22 Sep 2026) |
| AKN-02 | Nominal 2 desimal seluruh penyaji + CSV standar | Kritis | View/Controller | [X] Selesai (22 Sep 2026) |
| AKN-03 | Neraca: laba akumulasi ke ekuitas | Tinggi | AccountingService | [X] Selesai (22 Sep 2026) |
| AKN-04 | Saldo buku besar sesuai saldo normal | Tinggi | Controller/View | [X] Selesai (22 Sep 2026) |
| AKN-05 | Agregasi SQL jurnal & buku besar | Tinggi | Controller | [X] Selesai (22 Sep 2026) |
| AKN-06 | Validasi filter & tanggal statement; sinkron enum UI | Tinggi | Controller/View | [X] Selesai (22 Sep 2026) |
| AKN-07 | Validasi entri jurnal manual per baris | Tinggi | Controller | [X] Selesai (22 Sep 2026) |
| AKN-08 | Transaksi closure + validasi di luar try | Sedang | Controller | [X] Selesai (22 Sep 2026) |
| AKN-09 | CSV selaras modul Laporan | Sedang | Controller | [X] Selesai (22 Sep 2026) |
| AKN-10 | Klasifikasi arus kas berbasis kode akun | Sedang | AccountingService | [X] Selesai (22 Sep 2026) |
| AKN-11 | `can:accounting_export` + throttle pada export; validasi id button-option | Rendah | Routing | [X] Selesai (22 Sep 2026) |
| AKN-12 | Tanggal `translatedFormat` + konsistensi formatter | Rendah | View | [X] Selesai (22 Sep 2026) |

---

## 10. KEPUTUSAN BISNIS DAN URUTAN PERBAIKAN

### Keputusan yang perlu ditetapkan

- Presentasi ekuitas pada neraca: laba akumulasi (default akuntansi tanpa jurnal penutupan) vs adopsi jurnal penutupan tahunan di masa depan? (AKN-03)
- Klasifikasi arus kas: setujui mapping kode akun (piutang=operating, aset tetap=investing, utang jangka panjang/ekuitas=financing)? (AKN-10)
- Siapa yang boleh membuat jurnal manual — perlukah permission tulis terpisah (`accounting_journal_add`) alih-alih dibuka untuk semua `accounting_view`? (AKN-07 catatan)
- Standar ekspor: cukup CSV (Excel-ID) atau perlu PDF per jurnal? (AKN-02/09)

### Urutan implementasi yang disarankan

1. **AKN-01** (satu baris rute) — fitur mati, biaya perbaikan paling kecil.
2. **AKN-07 + AKN-08** (jurnal manual) — pintu masuk data cacat; perbaiki bersama karena satu file.
3. **AKN-03 + AKN-10** (service) — koreksi rumus neraca & arus kas; butuh keputusan bisnis.
4. **AKN-04 + AKN-05 + AKN-06** (penyajian) — buku besar & jurnal umum benar dan cepat.
5. **AKN-02 + AKN-09 + AKN-11 + AKN-12** (format & izin) — lapisan presentasi.
6. **Regresi** (bagian 7): jalankan `AccountingModuleTest` baru + full suite.

**Kesimpulan:** 12 temuan (2 kritis, 5 tinggi, 3 sedang, 2 rendah). Mesin posting inti (`post()` + tutup buku) sudah sehat setelah FIN-09; risiko terbesar ada pada **penyajian dan validasi** — export yang mati (AKN-01), sen yang hilang dari layar (AKN-02), neraca yang tidak berimbang antar-tahun (AKN-03), dan jurnal manual yang menerima entri cacat (AKN-07). Temuan tidak menyimpulkan kejadian kerugian; koreksi mengikuti urutan di atas.

---

## KLARIFIKASI PENUTUPAN (22 September 2026)

Seluruh temuan AKN-01..AKN-12 dinyatakan **selesai dan terverifikasi**.

**Perubahan utama:**

- **Routing & izin (AKN-01/07/11):** `routes/rental.php` — export kini `/journal/{id}/export` dengan `can:accounting_export` + `throttle:30,1`; form & store jurnal manual memakai gate baru `accounting_journal_add` (SUPERADMIN/ADMIN/MANAGER; SUPERVISOR ke bawah view-only). Tombol UI (jurnal manual, EXPORT CSV) di-gate `@can` sehingga tidak tampil bagi role tanpa izin.
- **Jurnal manual (AKN-07/08):** validasi XOR per baris (tepat satu sisi > 0), batas atas kapasitas DECIMAL(15,2), minimal satu debit & satu kredit, referensi ≤ 50 (selaras kolom), akun wajib daun (bukan induk) & aktif; seluruh validasi dilakukan sebelum transaksi dan penulisan memakai closure `DB::transaction` — pola anti-racun koneksi dari remediasi Fleet diterapkan dan diuji regresi.
- **Penyajian (AKN-02/04/05/06/12):** agregasi `withSum` di SQL untuk jurnal & buku besar; saldo sesuai saldo normal (`AccountingService::signedBalance`) dengan saldo berjalan yang benar dan kolom "—" untuk akun induk; filter enum-tervalidasi (`Journal::TYPES` sebagai kontrak tunggal) dan dropdown disinkronkan (`revenue`→`income`, opsi mati `auto`/`closing` dihapus); nominal dua desimal via `AppSettings::money()` dan tanggal `translatedFormat` Indonesia di seluruh layar.
- **Laporan (AKN-03/10):** neraca meleburkan laba **akumulasi** sejak awal masa ke ekuitas (invarian A = L + E diuji lintas dua tahun), dengan baris informasi laba tahun berjalan & tahun lalu; arus kas diklasifikasi berbasis kode akun lawan (`classifyCashFlow`: 1-2xxx/2-3xxx operating, 1-3xxx investing, 2-2xxx/3-xxxx financing, fallback tipe akun) — mapping default dicatat sebagai keputusan presentasi metode langsung.
- **Ekspor (AKN-09):** CSV selaras modul Laporan — BOM UTF-8, pemisah `;`, dua desimal gaya Excel-ID, nama file `jurnal_{ref}_{Ymd}.csv`, plus guard karakter pada nama file.

**Verifikasi:** `AccountingModuleTest` baru (20 test / 130 assertions) mencakup unduhan CSV & standarnya, gate & throttling ekspor, seluruh penolakan jurnal manual, non-racunnya koneksi setelah validasi gagal, filter enum & rentang statement, formatter dua desimal, saldo normal buku besar, agregasi SQL, keseimbangan neraca lintas tahun, dan klasifikasi arus kas. Full suite **128 test / 7.755 assertions lulus**, Pint bersih.

**Batas penutupan:** uji paralel pada MySQL produksi (perilaku enum strict, volume riil) dan rekonsiliasi jurnal historis yang sudah tersimpan sebelum perbaikan (nilai 0 desimal di layar hanyalah masalah tampilan; data tetap dua desimal di DB) tidak dilakukan dalam sesi ini. Bila kelak fitur jurnal penutupan tahunan diadopsi, rumus laba akumulasi pada `balanceSheet()` harus disesuaikan (dependensi AKN-03).
