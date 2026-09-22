# AUDIT — DATA SEEDER
## BaskaDrive Rental ERP — Kelengkapan, Realisme & Kepatuhan Kontrak Data Seed (Seluruh Model)

**Tanggal audit:** 22 September 2026
**Lokasi dokumen:** `audit/15092026/audit_seeder.md`
**Cakupan:** seluruh 14 seeder di `database/seeders/` (pipeline admin + pipeline ERP + seeder komprehensif) dan kepatuhannya terhadap skema DB, kontrak remediasi FIN-01..16 / FLE-01..14 / AKN-01..12, serta standar data realistis untuk demo & uji penerimaan.
**Metode:** penelusuran seeder vs skema migrasi & model, kontrak service (RentalSettlementService, FineSettlementService, AccountingService), dan verifikasi runtime via `SeederIntegrityTest`.

---

## 1. Arsitektur Seeder Saat Ini

`DatabaseSeeder` memanggil 14 seeler berurutan:

| # | Seeder | Isi |
|---|---|---|
| 1 | PermissionSeeder / RoleSeeder | 60+ permission, 6 peran |
| 2 | UserSeeder | 20 user aktif + 3 soft-deleted (superadmin/admin/manager/supervisor/staff/viewer) |
| 3 | UserLogSeeder | Log aktivitas format LogHelper |
| 4 | SanctumTokenSeeder | Token API demo |
| 5 | SettingsSeeder | `AppSettings::defaults()` idempoten |
| 6 | RentalErpMasterSeeder | Brand/model/vehicle/customer/driver/location/workshop/mtype/promo (kecil) |
| 7 | RentalErpEmployeeSeeder | 6 karyawan |
| 8 | RentalErpCoaSeeder | 26 akun COA idempoten |
| 9 | RentalErpRentalSeeder | 8 rental deterministik + detail + extension |
| 10 | RentalErpOperationalSeeder | 3 return, 1 damage+foto, 1 klaim, 4 maintenance, 4 denda |
| 11 | RentalErpFinanceSeeder | 6 invoice, 5 payment, 1 refund, jurnal |
| 12 | **RentalErpCompleteSeeder** | Dataset 12 bulan: 10 brand, 34 model, 46 unit, 48 pelanggan, 16 sopir, 8 lokasi, 10 bengkel, 6 tipe maintenance, 10 promo, 26 COA, 14 karyawan, ~150 sewa + detail/extension/return/inspeksi/damage/foto/klaim/maintenance/denda/invoice/payment/refund/jurnal berpasangan — **dengan TRUNCATE semua tabel ERP di awal run()** |

**Fakta kunci:** `RentalErpCompleteSeeder` me-truncate seluruh tabel ERP (master + transaksi + `user_logs`) lalu mengisinya ulang sendiri. Artinya seluruh hasil seeder #6–#11 **dibuang setiap kali seed dijalankan** — dan `user_logs` hasil UserLogSeeder (#3) ikut terbuang.

## 2. Temuan (SED-01..SED-10)

### SED-01 — TINGGI: Kerja ganda — 6 seeder pipeline dibuang oleh truncate CompleteSeeder
Seeder #6–#11 menghitung & menulis ribuan baris (rental, invoice, jurnal, dsb.) yang langsung di-truncate oleh `RentalErpCompleteSeeder`. Waktu seed ±2× lebih lama di setiap run — termasuk **setiap metode test** yang memanggil `DatabaseSeeder` (20+ test feature). `user_logs` dari UserLogSeeder juga terbuang. *(Ditutup: `DatabaseSeeder` hanya menjalankan admin seeders + RentalErpCompleteSeeder; pipeline lama dipertahankan sebagai arsip opsional; truncate `user_logs` dihapus dari CompleteSeeder.)*

### SED-02 — TINGGI: Formula invoice melanggar kontrak aplikasi saat ada diskon
Kontrak service (FIN-07): `total_amount = sub_total − discount + tax` dengan `sub_total` bruto sebelum diskon. Seeder menulis `sub_total = total − tax` (netto) → untuk ±22% sewa berpromo, rumus invoice tidak konsisten dan uji `recalculateDraftInvoice`/laporan terhadap data demo akan melenceng. *(Ditutup: `sub_total = total + discount − tax` (bruto), diuji invariant-nya.)*

### SED-03 — TINGGI: Jurnal adjustment dua baris 0/0 — melanggar AKN-07
4 jurnal "penyesuaian" dibuat dengan debit=0 kredit=0 pada kedua baris. Kontrak AKN-07 (`AccountingService::post`) menolak jurnal tanpa detail nonnol — data demo justru memuat pelanggaran kontraknya sendiri dan membiaskan laporan (jurnal kosong di ledger). *(Ditutup: diganti adjustment nyata — koreksi selisih kas Dr 5-2500 / Cr 1-1100.)*

### SED-04 — TINGGI: Refund tidak seimbang secara akuntansi
Refund pembatalan dibukukan **Dr Pendapatan Sewa (4-1100) / Cr Bank** — padahal rental `cancelled` tidak pernah di-invoice/dibukukan pendapatannya → pendapatan kumulatif bisa negatif; refund deposit (bukan pendapatan) juga dibukukan ke 4-1100. Kontrak aplikasi: uang muka dibukukan Dr Kas / Cr 2-3000 (Uang Muka Pelanggan) dan pengembaliannya Dr 2-3000 / Cr Kas. *(Ditutup: deposit ditanahkan sebagai payment `allocation=deposit` (Dr Kas/Cr 2-3000); refund deposit = Dr 2-3000 / Cr Bank + Cr 4-1100 sebesar extra charge yang dipotong dari deposit (mendemonstrasikan deposit menutup kerusakan); refund pembatalan = prepayment 50% Dr Kas/Cr 2-3000 lalu refund Dr 2-3000 / Cr Bank.)*

### SED-05 — SEDANG: Klaim asuransi `paid` tanpa jurnal pencairan (FLE-09)
±25% klaim berstatus `paid` tetapi tidak ada jurnal `insurance` Dr 1-1200 / Cr 4-3000 — kontrak FLE-09 tidak terdemonstrasi dan pendapatan klaim tidak masuk laba rugi demo. *(Ditutup: setiap klaim paid dibukukan Dr 1-1200 / Cr 4-3000 atas approved_amount.)*

### SED-06 — SEDANG: Denda tidak memakai kontrak FIN/FLE (damage_id, paid_by/waived_by, akrual)
Denda `damage` tidak menautkan `damage_id` (FLE-07), tidak mengisi `paid_by`/`waived_by` (FIN-04), dan denda kerusakan tidak melalui akrual piutang Dr 1-2100 / Cr 4-2000 (FLE-08). *(Ditutup: denda damage tertaut damage nyata; akrual saat issued, settlement Dr Kas/Cr 1-2100 saat paid, reversal saat waived; identitas petugas terisi.)*

### SED-07 — SEDANG: Status kendaraan tidak sinkron dengan sewa aktif
65% unit memiliki rental `ongoing`/`reserved`, tetapi seluruh `m_vehicle.status` = `available` → dashboard & guard FLE-03 menampilkan kondisi mustahil. Pipeline lama (yang dibuang) punya sinkronisasi; CompleteSeeder tidak. *(Ditutup: sinkronisasi akhir — vehicle dengan rental ongoing → `rented`, reserved → `reserved`.)*

### SED-08 — SEDANG: Odometer & kilometer tidak konsisten antar tabel
`return_mileage` dihitung dari progres km unit (bagus), tetapi `current_mileage` maintenance bisa negatif (`km − 15.000` tanpa guard), dan odometer inspeksi `random_int(8000..50000)` tidak berkaitan dengan km unit — handover_in bisa lebih kecil dari handover_out. *(Ditutup: guard minimal, odometer inspeksi disinkronkan dengan km unit.)*

### SED-09 — RENDAH: Log aktivitas memakai user_id acak 1..20
`user_logs.user_id` random 1..20 — cocok dengan jumlah user saat ini tetapi rapuh bila UserSeeder berubah (FK hanya di MySQL). *(Ditutup: user_id diambil dari daftar user aktual.)*

### SED-10 — RENDAH: Kode mati pada seeder promo
`$this->pick(array_slice($cats, 0, 3), 2)` — argumen kedua tidak pernah dipakai `pick()`; seleksi kategori promo efektif deterministik dua hardcoded set. *(Ditutup: kode mati dihapus, seleksi kategori eksplisit.)*

## 3. Yang Sudah Baik (dipertahankan)
- Dataset 12 bulan realistis: distribusi status sewa dari posisi tanggal (completed/ongoing/reserved/cancelled + overdue via `ongoing` + end < now sesuai semantik aplikasi), durasi berbobot 2–14 hari, harga per model, PPN 11% (0/5% variasi korporat), deposit per model, add-on & perpanjangan, progres kilometer per unit.
- Jurnal utama berpasangan dan menyertai kejadian: invoice (Dr 1-2100/Cr 4-1100), pembayaran (Dr Kas/Bank/Cr 1-2100), maintenance (Dr 5-2100/5-2200/Cr Kas), denda non-damage paid (Dr Kas/Cr 4-2000).
- Semua enum mengikuti skema: `payment_method` (5 nilai valid), `refund_type` (deposit_return/cancellation), `item_type` add-on, status klaim/maintenance/invoice, `journal_type` termasuk `insurance`.
- Rentang tanggal kronologis sebab-akibat (issue ≤ start, due = issue+7, paid ≤ due+10, return ≥ end).
- `m_coa` CompleteSeeder identik dengan `RentalErpCoaSeeder` (26 akun) — tidak ada divergensi akun.
- `user_id` random 1..20 aman terhadap UserSeeder (20 user) — tetap diperkuat SED-09.

## 4. Model Tanpa Tabel (observasi, di luar cakupan seeder)
`Cart` & `CartAction` adalah model yatim — tidak ada migrasi tabelnya → tidak dapat & tidak perlu di-seed. Tidak ada model lain tanpa data seed (seluruh 28 tabel ERP + admin terisi oleh pipeline akhir).

## 5. Batas Verifikasi
- Integritas diverifikasi pada SQLite (lingkungan test); perilaku `enum`/`decimal` MySQL produksi tetap perlu uji paralel (batas sama dengan FIN-16/FLE-14/AKN-12).
- Volume seed ±150 sewa/±1.300 baris jurnal — belum diuji beban 10×.
- Foto damage berupa path virtual `damage-photos/*.jpg` (tanpa file fisik) — wajar untuk demo.
- `RentalErpCompleteSeeder` bersifat regenerate (truncate ERP lalu isi ulang): cara regenerasi data demo adalah `php artisan migrate:fresh --seed`; re-run tanpa fresh akan menggandakan transaksi oleh desain.

## 6. Rencana Regresi — `SeederIntegrityTest` (baru)
1. Semua tabel inti non-kosong (28 tabel: brand…journal_detail + user_logs + app_settings).
2. Setiap jurnal seimbang (Σdebit = Σkredit per jurnal; ≥ 20 jurnal; tidak ada detail 0/0).
3. Formula invoice `total = sub_total − discount + tax` (ε 0,01); `paid_amount ≤ total`; status `paid` ⇔ `paid_amount = total`; satu invoice per rental.
4. Payment konsisten: `allocation=rental` ⇒ `invoice_id` terisi; `allocation=deposit` ⇒ `invoice_id` NULL; Σ payment rental (allocation=rental) = Σ `invoice.paid_amount`.
5. Denda: `damage` ⇒ `damage_id` terisi; `paid` ⇒ `paid_by` & `paid_date` terisi; `waived` ⇒ `waived_by` terisi; denda damage berjurnal akrual + settlement/waive sesuai status.
6. Klaim `paid` berjurnal insurance Dr 1-1200 / Cr 4-3000.
7. Rental: `return_date ≥ start`; tidak ada dua sewa aktif overlap pada unit yang sama; unit dengan sewa ongoing berstatus `rented`.
8. Deposit: rental berdeposit ⇒ ada payment `deposit`; refund deposit ⇒ Dr 2-3000 tercatat.
9. Jurnal kronologis: `transaction_date ≤` hari ini.
10. Inspeksi: odometer `handover_in ≥ handover_out` per rental (bila keduanya ada).

## 7. Matriks Prioritas

| ID | Tingkat | Target | Status |
|---|---|---|---|
| SED-01 | TINGGI | DatabaseSeeder + CompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-02 | TINGGI | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-03 | TINGGI | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-04 | TINGGI | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-05 | SEDANG | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-06 | SEDANG | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-07 | SEDANG | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-08 | SEDANG | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-09 | RENDAH | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |
| SED-10 | RENDAH | RentalErpCompleteSeeder | ☑ Ditutup 22 Sep 2026 |

## 8. Klarifikasi Penutupan (22 September 2026)

Seluruh 10 temuan (SED-01..SED-10) telah diremediasi dan diverifikasi:

- **Pipeline tunggal:** `DatabaseSeeder` hanya menjalankan admin seeders + `RentalErpCompleteSeeder`; enam seeder pipeline lama dipertahankan sebagai arsip yang tetap bisa dijalankan manual, tetapi tidak lagi dibuang oleh truncate (SED-01).
- **Kontrak keuangan:** formula invoice bruto (SED-02), jurnal adjustment bernilai nyata (SED-03), tahanan deposit `allocation=deposit` dengan lawan 2-3000 + refund tanpa membalik pendapatan (SED-04), jurnal pencairan klaim `insurance` Dr 1-1200/Cr 4-3000 (SED-05), denda kerusakan tertaut `damage_id` dengan akrual/settlement/waive sesuai FLE-08 plus identitas `paid_by`/`waived_by` (SED-06).
- **Realisme operasional:** status unit sinkron dengan sewa aktif (SED-07), odometer inspeksi & maintenance konsisten (SED-08), log aktivitas memakai user aktual (SED-09), kode mati promo dihapus (SED-10).
- **Kronologi:** seluruh tanggal jurnal/invoice/pembayaran/denda/klaim diklem agar tidak ada dokumen di masa depan; pembayaran booking masa depan diposisikan sebagai DP saat booking.
- **Verifikasi:** `SeederIntegrityTest` baru — **22 test / ±8.700 asersi lulus**; full suite **150 test / 16.466 asersi lulus**; Pint bersih.
- **Batas penutupan:** uji paralel MySQL produksi dan uji beban volume besar tidak dilakukan (mengikuti batas yang sama pada FIN/FLE/AKN); foto damage tetap path virtual tanpa file fisik.

## 9. Keputusan Bisnis (terkonfirmasi saat implementasi)
- **Sumber data demo tunggal:** `RentalErpCompleteSeeder` (dataset 12 bulan) menjadi satu-satunya pengisi data ERP; pipeline lama (#6–#11) dipertahankan sebagai arsip dan tidak lagi dipanggil `DatabaseSeeder`.
- **Kebijakan deposit:** deposit ditanahkan sebagai payment `allocation=deposit` dengan lawan 2-3000 (Uang Muka Pelanggan), konsisten dengan kontrak prepayment `RentalSettlementService`; potongan kerusakan dari deposit dibukukan sebagai pendapatan sewa.
- **Denda kerusakan:** mengikuti jalur akrual FLE-08 penuh (akrual → settlement/waive) agar laporan piutang demo mencerminkan produksi.
