# AUDIT — MENU KEUANGAN
## BaskaDrive Rental ERP — Invoice, Denda, Pembayaran, Refund & Dokumen Keuangan

**Tanggal audit:** 17 September 2026
**Lokasi dokumen:** `audit/15092026/audit_keuangan.md`
**Cakupan utama:** menu **Keuangan** dengan prefix `/finance`, bukan `/finances`; tiga tab Invoice, Denda, dan Pembayaran.
**Cakupan integrasi:** penerbitan invoice, pembayaran/refund melalui Sewa, pengembalian kendaraan, jurnal otomatis, dan konsumsi transaksi oleh Laporan. Menu Akuntansi formal tidak diaudit menyeluruh di dokumen ini.
**Metode:** penelusuran statis rute, middleware, controller, model, skema/migrasi, service, Blade, JavaScript, serta inventaris pengujian.
**Legenda:** `[ ]` = terbuka; `[X]` = sudah diperbaiki dan diverifikasi. Seluruh temuan di bawah belum diperbaiki dalam pekerjaan audit ini.

> Tidak ada perubahan kode aplikasi, transaksi keuangan, pengiriman email, pengujian konkurensi, atau reproduksi celah dalam audit ini. Temuan terkonfirmasi berarti didukung kode sumber, bukan terbukti terjadi di produksi. Konfigurasi rahasia, data produksi, dan middleware/schema aktual deployment tidak diperiksa. Referensi baris mengikuti snapshot sumber saat audit.

---

## DAFTAR ISI

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Inventaris Rute dan Tampilan](#2-inventaris-rute-dan-tampilan)
3. [Permission dan Batas Akses](#3-permission-dan-batas-akses)
4. [Integritas Transaksi dan Saldo](#4-integritas-transaksi-dan-saldo)
5. [Validasi dan Ketahanan Operasional](#5-validasi-dan-ketahanan-operasional)
6. [Dokumen, Format, dan Pengalaman Pengguna](#6-dokumen-format-dan-pengalaman-pengguna)
7. [Pengujian dan Batas Verifikasi](#7-pengujian-dan-batas-verifikasi)
8. [Proteksi yang Sudah Ada](#8-proteksi-yang-sudah-ada)
9. [Matriks Prioritas Action Plan](#9-matriks-prioritas-action-plan)
10. [Keputusan Bisnis dan Urutan Perbaikan](#10-keputusan-bisnis-dan-urutan-perbaikan)

---

## 1. RINGKASAN EKSEKUTIF

Menu Keuangan memiliki gate `finance_view` dan gate terpisah untuk bayar/bebaskan denda. Pembuatan invoice, pembayaran sewa, dan refund berada pada controller Sewa dengan izin masing-masing. Namun, proses keuangan belum memakai aturan settlement yang konsisten di semua jalur.

Masalah utama adalah **perbedaan pembayaran denda antara Finance dan Sewa**, **refund yang tidak merekonsiliasi invoice/status sewa/jurnal**, serta **perubahan nilai sewa yang tidak disertai penyesuaian invoice**. Transaksi database sudah dipakai pada sejumlah aksi, tetapi posisi lock dan identitas operasi belum cukup untuk menjamin konsistensi seluruh alur.

| Prioritas | Jumlah | Temuan |
|---|---:|---|
| Tinggi | 8 | FIN-01, FIN-02, FIN-03, FIN-04, FIN-05, FIN-07, FIN-08, FIN-09 |
| Sedang | 7 | FIN-06, FIN-10, FIN-11, FIN-12, FIN-13, FIN-15, FIN-16 |
| Rendah | 1 | FIN-14 |
| Total terbuka | **16** | Termasuk gap pengujian dan temuan dengan dampak bersyarat |

**Catatan penilaian:** FIN-01 bergantung pada kebijakan penggunaan 2FA. FIN-05 dan bagian konkurensi FIN-06/FIN-11 merupakan kelemahan jaminan di tingkat kode, bukan hasil uji paralel. FIN-09 menilai integrasi otomatis; kemungkinan koreksi melalui jurnal manual belum diverifikasi. Jumlah di atas bukan jumlah insiden atau kerugian yang terjadi.

---

## 2. INVENTARIS RUTE DAN TAMPILAN

### 2.1 Rute langsung menu Keuangan

Sumber: `routes/rental.php:278-304`, `routes/web.php:62-115`, `app/Http/Controllers/Rental/FinanceController.php:18-21`.

Seluruh rute berikut mewarisi `web` + `auth` + `can:finance_view` berdasarkan susunan sumber. Tidak ada `two_factor` pada grup Finance; lihat FIN-01.

| HTTP | URI | Action FinanceController | Nama rute | Izin tambahan |
|---|---|---|---|---|
| GET | `/finance` | `index` | `finance.index` | — |
| GET | `/finance/invoice/get-data` | `invoiceData` | `finance.invoice.data` | — |
| GET | `/finance/invoice/get-button-option` | `invoiceButtonOption` | `finance.invoice.button-option` | — |
| GET | `/finance/invoice/{id}` | `invoiceShow` | `finance.invoice.show` | — |
| GET | `/finance/invoice/{id}/print` | `invoicePrint` | `finance.invoice.print` | — |
| POST | `/finance/invoice/{id}/send-email` | `invoiceSendEmail` | `finance.invoice.send-email` | Tidak ada gate aksi terpisah |
| GET | `/finance/fine/get-data` | `fineData` | `finance.fine.data` | — |
| GET | `/finance/fine/get-button-option` | `fineButtonOption` | `finance.fine.button-option` | — |
| PUT | `/finance/fine/{id}/pay` | `finePay` | `finance.fine.pay` | `fine_pay` |
| PUT | `/finance/fine/{id}/waive` | `fineWaive` | `finance.fine.waive` | `fine_waive` |
| GET | `/finance/payment/get-data` | `paymentData` | `finance.payment.data` | — |
| GET | `/finance/payment/get-button-option` | `paymentButtonOption` | `finance.payment.button-option` | — |
| GET | `/finance/payment/{id}/receipt` | `paymentReceipt` | `finance.payment.receipt` | — |

Tidak ditemukan ekspor CSV massal atau tab Refund tersendiri pada grup Finance. Cetak invoice dan kwitansi adalah download PDF individual, bukan ekspor modul Laporan.

### 2.2 Aksi keuangan yang berada pada Sewa

Sumber: `routes/rental.php:218-226`; semua aksi berikut juga membutuhkan `rental_view` dari grup induk.

| HTTP | URI | Izin aksi |
|---|---|---|
| GET / POST | `/rental/{rental}/invoice` | `finance_invoice_add` |
| GET | `/rental/{rental}/invoice/print` | Tidak ada izin tambahan di luar `rental_view` |
| POST | `/rental/{rental}/payment` | `finance_payment_add` |
| POST | `/rental/{rental}/refund` | `finance_refund_add` |
| POST | `/rental/{rental}/fine` | `fine_add` |
| PUT | `/rental/{rental}/fine/{fineId}/pay` | `fine_pay` |
| PUT | `/rental/{rental}/fine/{fineId}/waive` | `fine_waive` |

**Implikasi kebijakan:** izin transaksi Finance pada jalur ini tidak berdiri sendiri tanpa `rental_view`. Cetak lewat Sewa tidak mewajibkan `finance_view`; ini perlu keputusan kebijakan, bukan otomatis temuan akses ilegal karena halaman Sewa memang memuat informasi keuangan.

### 2.3 Tampilan dan dokumen

| File | Fungsi |
|---|---|
| `resources/views/finance/index.blade.php` | Tiga tab, filter status, DataTables server-side, aksi berdasarkan baris terpilih |
| `resources/views/finance/invoice/button_option.blade.php` | Detail, cetak, kirim invoice |
| `resources/views/finance/invoice/show.blade.php` | Detail invoice, ringkasan saldo, riwayat pembayaran |
| `resources/views/finance/invoice/print.blade.php` | PDF invoice; dipakai Finance, Sewa, dan lampiran email |
| `resources/views/finance/fine/button_option.blade.php` | Tombol bayar/bebaskan untuk denda unpaid sesuai permission |
| `resources/views/finance/payment/button_option.blade.php` | Cetak kwitansi |
| `resources/views/finance/payment/receipt.blade.php` | PDF kwitansi dan QR verifikasi |
| `resources/views/verify/receipt.blade.php` | Verifikasi publik tanda keaslian kwitansi |

Branding berasal dari `AppSettings::all()` melalui `FinanceController.php:107-109`. PDF download memakai `app/Support/PdfDocument.php:20-27`. Email menggunakan `app/Mail/InvoiceMail.php`.

---

## 3. PERMISSION DAN BATAS AKSES

### 3.1 Kontrak permission yang ada

Sumber: `database/seeders/PermissionSeeder.php:46-51`, `database/seeders/RoleSeeder.php:25-82`, `resources/views/layouts/menu.blade.php:157-164`.

| Role bawaan | Finance view | Buat invoice | Catat pembayaran | Refund | Bayar denda | Bebaskan denda |
|---|---|---|---|---|---|---|
| SUPERADMIN | Semua permission | Ya | Ya | Ya | Ya | Ya |
| ADMIN | Ya | Ya | Ya | Ya | Ya | Ya |
| MANAGER | Ya | Ya | Ya | Ya | Ya | Ya |
| SUPERVISOR | Ya | Ya | Ya | Ya | Ya | Tidak |
| STAFF | Ya | Ya | Ya | Tidak | Ya | Tidak |
| VIEWER | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak |

Tabel mencerminkan **seeder**, bukan permission pengguna yang sudah tersimpan di deployment. Tidak ada alasan otomatis memakai `report_export` untuk PDF Finance: permission tersebut milik modul Laporan.

### [ ] FIN-01 — Grup Finance/Sewa berada di luar batas middleware 2FA — Tinggi, bersyarat

- **Bukti:** `routes/web.php:62-64` membuka grup `auth` dan grup dalam `two_factor`; grup dalam ditutup pada baris 108, sedangkan `require rental.php` baru berjalan pada baris 114. Finance didefinisikan pada `routes/rental.php:278-304` tanpa middleware 2FA tambahan.
- **Kondisi:** autentikasi dan permission tetap ada. Namun, bila kebijakan aplikasi mengharuskan verifikasi faktor kedua, operasi ERP tidak mengikuti batas yang dipakai dashboard/setup.
- **Perbaikan:** tempatkan include ERP dalam batas 2FA yang memang ditetapkan, atau beri middleware eksplisit pada grup yang diperlukan. Pastikan alur verifikasi sendiri tetap dapat diakses.
- **Verifikasi lanjutan:** audit middleware efektif dan pengujian akses dengan kondisi 2FA belum/sudah selesai pada lingkungan pengujian. Nilai konfigurasi aktif tidak diperiksa di audit ini.

---

## 4. INTEGRITAS TRANSAKSI DAN SALDO

### [ ] FIN-02 — State pembayaran/pembebasan denda tidak dijaga konsisten — Tinggi

- **Bukti:** `FinanceController.php:209-247` memuat denda tanpa predicate status; `finePay` membuat Payment, sedangkan `fineWaive` langsung menetapkan `waived`. Pembacaan denda terjadi sebelum transaksi dan tanpa lock.
- **Perbandingan:** `RentalController.php:1344-1364` setidaknya membatasi status awal ke `unpaid`, tetapi tidak memakai satu service settlement bersama.
- **Dampak:** sumber tidak menjamin satu settlement per denda; perubahan paid menjadi waived tidak disertai reversal pembayaran. Tombol tersembunyi pada status selain unpaid di `finance/fine/button_option.blade.php:1-14` bukan pengganti validasi server.
- **Perbaikan:** satu service dengan state transition eksplisit, lock di dalam transaksi, relasi settlement yang unik, serta reversal terpisah untuk koreksi transaksi yang sudah dibayar. Jangan menimpa identitas penerbit denda untuk menyimpan petugas pembayaran (`FinanceController.php:218`).
- **Batas bukti:** tidak dilakukan pengulangan permintaan atau pengujian paralel.

### [ ] FIN-03 — Pembayaran denda bercampur dengan pelunasan pokok sewa — Tinggi

- **Bukti:** `FinanceController.php:221-228` membuat Payment dengan `rental_id`, tetapi tanpa penanda alokasi denda. `RentalController.php:1347-1348` hanya mengubah status denda tanpa Payment. Kedua jalur tidak membuat jurnal pembayaran denda.
- **Integrasi:** `RentalController.php:1426` dan `1495-1498` menjumlahkan seluruh Payment completed untuk rental; tidak membedakan pokok sewa dari denda. Pembayaran denda melalui Finance dapat ikut masuk perhitungan pembayaran invoice/batas sisa sewa.
- **Pelaporan:** `ReportController.php:202-218` memasukkan denda paid sebagai beban; padahal denda dalam alur ini ditagihkan kepada penyewa. Finance juga mencatat penerimaannya sebagai Payment, sementara jalur Sewa tidak.
- **Perbaikan:** modelkan tujuan pembayaran dan alokasi kewajiban; semua jalur menggunakan service yang sama dan jurnal yang sesuai. Tidak cukup sekadar mengisi `invoice_id` denda ke invoice sewa apabila kewajibannya memang berbeda.

### [ ] FIN-04 — Refund tidak merekonsiliasi invoice, status sewa, dan jurnal — Tinggi

- **Bukti:** `RentalController.php:1584-1611` mencatat Refund processed dan mengubah Payment menjadi refunded hanya saat nilai kumulatif mencapai pembayaran. Tidak ada penyesuaian `Invoice.paid_amount`, status invoice, `Rental.payment_status`, atau jurnal reversal dalam alur ini.
- **Dampak:** refund parsial masih meninggalkan nilai Payment penuh dalam jumlah completed; refund penuh mengeluarkan Payment dari jumlah tersebut, tetapi saldo dibayar invoice tidak ikut berubah.
- **Lintas laporan:** pendapatan mengambil Payment completed (`ReportController.php:128-137`), sedangkan refund processed juga dikurangkan sebagai beban (`ReportController.php:205-218`). Arus penerimaan asal dan pengembalian tidak dipertahankan konsisten, terutama lintas periode.
- **Perbaikan:** pertahankan gerakan penerimaan/refund sebagai transaksi tertaut, hitung alokasi neto, dan rekonsiliasi proyeksi saldo serta jurnal secara atomik. Refund deposit tidak boleh otomatis diperlakukan sama dengan pengurangan piutang sewa; tetapkan aturan per jenis.
- **Proteksi yang tetap benar:** payment refund di-scope ke rental, dikunci dalam transaksi, dan dibatasi kumulatif refund nonfailed. Masalah ini bukan ketiadaan seluruh validasi refund.

### [ ] FIN-05 — Lock pembayaran berada sebelum transaksi; identitas operasi belum ada — Tinggi

- **Bukti lock:** `RentalController.php:1475` memanggil `Rental::lockForUpdate()` sebelum `DB::beginTransaction()` pada baris 1491. Lock berikutnya pada baris 1495 hanya mengunci invoice jika ada.
- **Risiko:** dengan autocommit biasa, lock awal tidak melindungi seluruh perhitungan dan penyimpanan pembayaran. Dampak paralel aktual bergantung pada engine, isolasi, indeks, dan keberadaan invoice; belum diuji.
- **Bukti identitas:** Payment dan Refund dibuat sebagai record baru pada `RentalController.php:1510-1519,1596-1604`; tidak ada idempotency key operasi. `reference_number` bukan pengenal replay unik dalam `database/migrations/2026_09_03_100021_create_tr_payment_table.php:13-29`.
- **Perbaikan:** mulai transaksi sebelum mengambil lock parent, tetapkan urutan lock bersama untuk semua mutasi saldo, dan gunakan kunci operasi unik dengan hasil tersimpan. Batas nominal tidak menggantikan identitas operasi karena cicilan sah juga bisa bernilai sama.

### [ ] FIN-06 — Penerbitan invoice belum atomik dan tidak menautkan pembayaran terdahulu — Sedang

- **Bukti:** pengecekan invoice existing pada `RentalController.php:1389-1394` terjadi sebelum transaksi baris 1404. Migrasi `2026_09_03_100020_create_tr_invoice_table.php:13-33` menjamin nomor invoice unik, bukan satu invoice per rental.
- **Pembayaran terdahulu:** `RentalController.php:1417-1428` menyalin total pembayaran completed ke `paid_amount`, selalu menetapkan status draft, dan tidak menautkan Payment sebelumnya ke invoice baru.
- **Dampak tampilan:** relasi riwayat invoice memakai `invoice_id` (`app/Models/Invoice.php:39-42`); saldo paid dapat positif dengan riwayat kosong. Tampilan detail memakai status tersimpan, PDF memakai perbandingan nilai pembayaran (`finance/invoice/show.blade.php:37,149-160`; `finance/invoice/print.blade.php:23-35`).
- **Perbaikan:** penerbitan di bawah lock rental; tegakkan kardinalitas invoice atau revisi secara eksplisit di skema. Tautkan alokasi prepayment yang sesuai, dan pisahkan status penerbitan/pengiriman dari status pelunasan.
- **Batas bukti:** tidak diklaim invoice ganda sudah terjadi; tabrakan nomor atau lock database juga dapat menyebabkan salah satu operasi gagal.

### [ ] FIN-07 — Edit/perpanjangan sewa tidak merekonsiliasi invoice yang sudah terbit — Tinggi

- **Bukti:** `RentalController.php:974-1015` menghitung ulang nilai sewa saat update; `1175-1190` menambah nilai sewa/pajak pada persetujuan extension. Alur tersebut tidak memperbarui invoice atau membuat dokumen penyesuaian.
- **Dua sumber nilai:** batas pembayaran menggunakan total rental (`RentalController.php:1500-1507`), sedangkan pelunasan invoice menggunakan total invoice (`1521-1525`).
- **Dokumen:** `finance/invoice/print.blade.php:447-543` menggabungkan periode/detail rental terkini dengan jumlah invoice tersimpan. Cetak ulang bukan snapshot finansial yang sepenuhnya tetap.
- **Perbaikan:** definisikan kapan invoice dibekukan; draft dapat direkalkulasi terkontrol, invoice terbit membutuhkan adjustment/debit-credit note. Snapshot rincian dan sinkronkan proyeksi settlement saat kewajiban berubah.

### [ ] FIN-08 — Extra charge dan pengembalian deposit belum terhubung ke settlement — Tinggi

- **Bukti:** `RentalController.php:1238-1291` menyimpan `extra_charge` dan `deposit_refund` pada pengembalian kendaraan. Extra charge memiliki jurnal piutang, tetapi tidak dimasukkan ke total rental/invoice; deposit refund tidak membuat Refund atau jurnal pengeluaran pada jalur ini.
- **Dampak:** pembayaran biasa tetap dibatasi oleh total rental, tidak oleh kewajiban tambahan yang baru diakui. Batas pengembalian deposit memakai deposit rental, bukan buku besar deposit yang sudah diterima dan belum dikembalikan.
- **Kontrak dokumen:** `finance/invoice/print.blade.php:575-580` menyebut deposit terpisah dari tagihan. Jalur Refund justru memerlukan Payment umum tanpa alokasi deposit tersendiri.
- **Perbaikan:** catat penerimaan deposit sebagai kewajiban terpisah, catat pengembaliannya sebagai settlement deposit, dan terbitkan alokasi/dokumen biaya tambahan. Jika deposit sengaja dikelola di luar aplikasi, nyatakan batas tersebut pada UI dan laporan.

### [ ] FIN-09 — Rangkaian jurnal otomatis belum lengkap dan akun kosong dapat lolos — Tinggi

- **Pengakuan awal:** pembayaran biasa menjurnal Dr Kas/Bank dan Cr Piutang (`RentalController.php:1534-1539`). Pada alur create/confirm/issue invoice yang ditelusuri (`176-224,801-806,1417-1431`), tidak ditemukan pasangan pengakuan piutang/pendapatan sewa dasar secara otomatis. Trigger yang diperiksa pada migrasi `2026_09_03_100026_create_rental_erp_function_and_triggers.php:46-88` tidak menyediakan pengganti tersebut.
- **Akun kosong:** `AccountingService.php:42-57` membuang baris tanpa account sebelum menghitung keseimbangan. Bila seluruh akun wajib tidak ditemukan, debit dan kredit sama-sama nol sehingga header jurnal kosong tetap dapat dibuat.
- **Dampak:** keberadaan header jurnal belum membuktikan transaksi sumber telah dibukukan secara lengkap. Jurnal manual mungkin dipakai sebagai pelengkap, tetapi proses itu tidak diverifikasi.
- **Perbaikan:** tetapkan titik pengakuan kewajiban/pendapatan, reversal, dan pajak; gunakan referensi sumber unik. Tolak akun wajib yang tidak terpetakan dan jurnal tanpa detail sebelum menyimpan. Saat salah satu sisi nonnol hilang, pemeriksaan keseimbangan yang sudah ada harus tetap menggagalkan transaksi.

---

## 5. VALIDASI DAN KETAHANAN OPERASIONAL

### [ ] FIN-10 — Validasi keuangan tidak selaras skema dan eligibility transaksi — Sedang

- **Pembayaran biasa:** maksimum `99999999999` dan referensi 100 karakter pada `RentalController.php:1482-1486`; kolom `DECIMAL(12,2)` dan referensi 50 karakter pada migrasi `2026_09_03_100021_create_tr_payment_table.php:16-19`. Kapasitas positif DECIMAL tersebut adalah `9.999.999.999,99`.
- **Denda:** `FinanceController.php:209-228` menerima payment method/reference tanpa validasi enum/panjang, dan tidak memeriksa tutup buku seperti pembayaran biasa. UI hanya mengirim token sehingga metode selalu default cash (`finance/index.blade.php:362-366`).
- **Saldo nol:** pengecekan batas pembayaran dilewati bila total rental nol (`RentalController.php:1503`).
- **Refund:** `RentalController.php:1572-1585` tidak mensyaratkan status sumber completed pada lookup dan tidak menetapkan maksimum nominal sesuai storage. Keberadaan data pending/failed di produksi tidak diperiksa.
- **Perbaikan:** request/domain validation bersama; nilai uang maksimal dan skala desimal sesuai skema, metode pembayaran eksplisit, status sumber yang diizinkan, serta aturan saldo nol dan tutup buku. Gunakan aritmetika uang presisi tetap dan tinjau toleransi `0.01` agar tidak menyembunyikan selisih settlement.

### [ ] FIN-11 — Pengiriman email invoice belum mempunyai batas aksi dan lifecycle file yang aman — Sedang

- **Izin:** `routes/rental.php:289` hanya mewarisi `finance_view`. Aksi tersebut mengirim dokumen ke pelanggan dan dapat mengubah draft menjadi sent (`FinanceController.php:159-160`), sehingga bukan operasi baca murni.
- **Beban dan file:** tidak ada throttle khusus pada rute; rendering PDF dan pengiriman email berjalan sinkron. Path temp pada `FinanceController.php:144-155` hanya berdasarkan nomor/id invoice, sehingga operasi paralel berbagi file yang kemudian dihapus.
- **Status:** update sent memakai status pada model yang dimuat sebelum rendering/pengiriman, tanpa pemeriksaan ulang state terkini.
- **Perbaikan:** putuskan permission pengiriman tersendiri atau izin aksi yang sudah disepakati; selaraskan gate UI/server. Gunakan file unik per operasi, cleanup mencakup kegagalan render, kontrol laju/queue, dan transisi status kondisional.
- **Batas bukti:** role bawaan yang memiliki finance_view umumnya juga memiliki izin buat invoice. Kebutuhan gate berbeda terutama relevan pada role kustom; kebijakan send harus ditetapkan. Benturan file paralel belum direproduksi.

### [ ] FIN-12 — Kegagalan aksi tidak ditampilkan dan pesan internal diteruskan — Sedang

- **Bukti UI:** `finance/index.blade.php:339-344,368-378,402-412` hanya menangani `res.status` sukses; tidak ada cabang kegagalan aplikasi maupun handler error HTTP pada ketiga aksi.
- **Bukti backend:** `FinanceController.php:164-167,233-247` mengembalikan pesan exception mentah; sejumlah kegagalan dikirim sebagai HTTP 200 dengan `status:false`.
- **Dampak:** pengguna tidak memperoleh penjelasan bahwa pembayaran/pembebasan/email gagal dan dapat mencoba ulang tanpa mengetahui hasil sebelumnya. Detail internal transport/database dapat ikut tersampaikan, tergantung exception.
- **Perbaikan:** tangani respons gagal dan error jaringan; tampilkan pesan operasional umum dengan kode pelacakan, log detail hanya di server, dan konsistenkan status HTTP. Refresh status invoice setelah kirim berhasil dan batasi ulang-klik selama permintaan berlangsung.

### [ ] FIN-15 — Validasi filter dan pembatasan beban Finance belum eksplisit — Sedang

- **Bukti:** `FinanceController.php:27-39` langsung memakai input tab sebagai key tanpa validasi scalar. Input berbentuk array tidak aman untuk `array_key_exists` pada PHP 8. Filter status pada `53-72,175-192,255-273` juga tidak divalidasi sesuai enum per entitas.
- **Beban:** pemrosesan DataTables diserahkan ke library tanpa batas panjang halaman pada controller. PDF individual dan email tidak mempunyai throttle khusus dalam `routes/rental.php:278-304`.
- **Batas klaim:** nilai status biasa tetap memakai binding Eloquent; tidak ada bukti SQL injection pada query filter yang ditinjau. Perilaku batas DataTables/library dan beban deployment perlu diukur, bukan diasumsikan sebagai insiden OOM.
- **Perbaikan:** validasi tipe tab, status, id pilihan baris, dan parameter paginasi; tetapkan batas server. Uji konsumsi memori/latensi dokumen bersejarah dengan detail besar dan batasi render/email sesuai kebutuhan.

---

## 6. DOKUMEN, FORMAT, DAN PENGALAMAN PENGGUNA

### [ ] FIN-13 — Rincian dan status PDF tidak sepenuhnya menjelaskan invoice — Sedang

- **Rincian:** `finance/invoice/show.blade.php:72-101` hanya mengiterasi rental details. Rental details yang dibuat pada `RentalController.php:209-218` berisi add-on, sedangkan biaya dasar/asuransi/sopir berada pada rental.
- **PDF:** saat detail tersedia, `finance/invoice/print.blade.php:475-496` juga memakai rincian tersebut. Saat tidak tersedia, baris fallback `498-505` memakai subtotal sebagai harga satuan sekaligus total baris, dengan kuantitas hari sewa; makna unit price dan kuantitas tidak cocok untuk sewa beberapa hari.
- **Status:** `finance/invoice/print.blade.php:23-35` hanya membedakan lunas/sebagian/belum dibayar berdasarkan nominal. Status operasional seperti cancelled tidak direpresentasikan oleh perhitungan ini, sedangkan detail memakai badge status tersimpan.
- **Perbaikan:** buat snapshot line item lengkap yang menjumlah ke subtotal, diskon, pajak, dan total; tampilkan status dokumen dan settlement secara terpisah serta konsisten pada layar, PDF, dan email.

### [ ] FIN-14 — Sen dibulatkan pada Finance dan nama bulan tidak dilokalkan eksplisit — Rendah

- **Bukti nominal:** `FinanceController.php:67-69,188,269`, `finance/invoice/print.blade.php:511-543`, dan `finance/payment/receipt.blade.php:330` menggunakan `number_format(..., 0, ',', '.')`. Database/model tetap menyimpan dua desimal.
- **Koreksi interpretasi:** bukan hanya DataTables; PDF Finance juga membulatkan ke nol desimal. Format ini berbeda dengan helper bertipe dan presisi dua desimal pada modul Laporan yang sudah diperbaiki.
- **Tanggal:** `finance/invoice/print.blade.php:375` dan `finance/payment/receipt.blade.php:268,312` memakai `format('d F Y')`, bukan `translatedFormat`; nama bulan mengikuti format PHP, bukan terjemahan Indonesia.
- **Perbaikan:** sepakati tampilan nominal sampai sen dan pakai formatter bersama berdasarkan tipe nilai. Gunakan label bulan Indonesia secara eksplisit. Pembulatan tampilan tidak mengubah nilai tersimpan, tetapi dapat menyulitkan rekonsiliasi kwitansi dengan transaksi.

### Catatan UX/maintainability, bukan bug finansial tersendiri

- Aksi muncul setelah seleksi baris (`finance/index.blade.php:295-317`). Tambahkan petunjuk pemilihan bila diperlukan; pola ini sendiri bukan kerusakan fitur.
- URL tiga aksi dibangun dengan `url('finance/...')` dan string id (`333,362,396`); named route lebih mudah dirawat, tetapi URL saat ini tidak otomatis salah.
- Tab Finance hanya mempunyai filter status, belum filter periode. Penambahan rentang tanggal merupakan enhancement, bukan temuan validasi rentang yang sudah ada.
- Direktori temp PDF memang perlu writable. Kebutuhan filesystem tersebut bukan bug tanpa bukti salah konfigurasi; masalah lifecycle file bersama dibahas dalam FIN-11.

---

## 7. PENGUJIAN DAN BATAS VERIFIKASI

### [ ] FIN-16 — Cakupan regresi langsung operasi Finance belum memadai — Sedang

**Yang ditemukan pada sumber pengujian:**

| File | Cakupan relevan | Batas |
|---|---|---|
| `tests/Feature/RentalIntegrityTest.php:63-98` | Pembayaran parsial/lunas, penolakan overpayment, keberadaan jurnal | Melalui controller Sewa, bukan settlement denda Finance; tidak membuktikan isi jurnal lengkap |
| `tests/Feature/ModuleAccessGatesTest.php` | Penolakan VIEWER pada modul operasional termasuk Finance | Bukan matriks aksi bayar/waive/email/refund secara menyeluruh |
| `tests/Unit/ExampleTest.php` | Formatter modul Laporan | Belum berarti template Finance memakai helper tersebut |
| `tests/app-enhancements.test.js` | Perilaku range picker/global frontend | Tidak menguji settlement Finance |

Tidak ditemukan pengujian langsung yang menyeluruh untuk `FinanceController::finePay`, `fineWaive`, `invoiceSendEmail`, invoice prepayment, rekonsiliasi refund/deposit, maupun kesetaraan jalur Finance/Sewa. `phpunit.xml` memakai SQLite in-memory; hasilnya tidak menggantikan pengujian lock/transaksi MySQL.

**Rencana regresi setelah perbaikan:**

1. Matriks izin halaman/aksi, 2FA, PDF, dan pengiriman email sesuai kebijakan role.
2. State machine denda, alokasi pembayaran terpisah, serta kesetaraan mutasi dari dua menu.
3. Refund parsial/penuh: saldo invoice, status sewa, riwayat payment, dan jurnal tetap konsisten.
4. Penerbitan invoice setelah prepayment serta perubahan rental setelah invoice dibekukan.
5. Settlement extra charge dan deposit berdasarkan jumlah yang benar-benar tercatat diterima.
6. Gagal posting akibat akun tidak tersedia: seluruh mutasi finansial rollback dan jurnal kosong tidak terbentuk.
7. Nilai batas schema, referensi panjang, presisi sen, status tidak valid, dan error UI yang dapat dipahami.
8. Rekonsiliasi subtotal/detail dan status pada PDF; render biner riil serta email dengan mail fake.
9. Jaminan atomisitas/idempotensi pada database pengujian terisolasi yang sesuai engine produksi, setelah aturan transaksi diperbaiki.

**Perintah proyek yang dapat digunakan saat implementasi/regresi** (tidak dijalankan sebagai bagian audit dokumen ini):

```powershell
composer test
php artisan test --filter=RentalIntegrityTest
php artisan test --filter=ModuleAccessGatesTest
php vendor/bin/pint --test
node --test tests/app-enhancements.test.js
```

Pastikan koneksi pengujian terisolasi sebelum menjalankan suite berbasis `RefreshDatabase`. Audit ini tidak mengklaim lulus/gagal baru dari tes, browser, PDF biner, email, MySQL strict, atau transaksi paralel. Pemeriksaan lint/typecheck aplikasi tidak diperlukan untuk perubahan Markdown saja; tidak ada konfigurasi lint Markdown yang dijadikan dasar audit ini.

---

## 8. PROTEKSI YANG SUDAH ADA

- **Gate menu dan server:** `resources/views/layouts/menu.blade.php:157-164`, `routes/rental.php:278-304`; Finance tidak terbuka publik.
- **Gate mutasi terpisah:** bayar/bebaskan denda dan aksi keuangan pada Sewa mempunyai izin masing-masing. Masalah email tidak berarti seluruh mutasi kehilangan gate.
- **CSRF:** rute mutasi menggunakan web middleware; AJAX Finance mengirim `_token` (`finance/index.blade.php:336,365,399`).
- **Refund ownership/cap:** lookup Payment dibatasi rental dan dikunci dalam transaksi; refund sebelumnya dengan status selain failed dihitung (`RentalController.php:1581-1594`).
- **Transaksi pembayaran biasa:** pembuatan Payment, update invoice/sewa, serta posting jurnal berada dalam transaksi (`RentalController.php:1491-1541`). Penempatan lock parent tetap perlu FIN-05.
- **Tutup buku:** pemeriksaan ada pada pembuatan invoice, pembayaran biasa, refund, dan return; keberadaan pemeriksaan tersebut tidak mencakup otomatis semua aksi denda.
- **Soft delete:** Invoice, Payment, Refund, dan Rental memakai scope Eloquent. Tidak ditemukan rute delete/restore Finance pada inventaris ini.
- **Keaslian kwitansi:** `ReceiptVerificationController.php:15-31` memeriksa tanda HMAC melalui `hash_equals`; `app/Support/QrCode.php:27-30` membuat signature. Tampilan publik `verify/receipt.blade.php:27-43` tidak menampilkan identitas pelanggan. URL tanpa signature yang sesuai tidak cukup sebagai bukti sah.
- **Escaping tabel:** Finance menandai badge status sebagai raw HTML, bukan seluruh kolom pengguna (`FinanceController.php:71,191,272`). Ini bukan sertifikasi keamanan seluruh aplikasi.
- **Retensi:** beberapa foreign key menerapkan cascade/null pada hard delete. Tidak ditemukan jalur Finance yang mengaktifkan hard delete tersebut; perlakukan sebagai kebijakan retensi yang perlu ditetapkan, bukan celah reachable yang sudah terbukti.

### Catatan integrasi Fleet untuk audit lanjutan

`FleetController.php:351-381` melakukan deduplikasi tagihan kerusakan melalui pencarian deskripsi denda, bukan relasi identitas kerusakan yang unik. Rute penagihan pada `routes/rental.php:253-261` mengikuti `fleet_view`. Tinjau identitas kewajiban dan izin membuat tagihan pada audit Fleet; tidak ditambahkan sebagai temuan ke-17 karena di luar menu utama yang diminta.

---

## 9. MATRIKS PRIORITAS ACTION PLAN

| ID | Pekerjaan | Prioritas | Area utama | Status |
|---|---|---|---|---|
| FIN-01 | Tegaskan batas 2FA grup Finance/Sewa | Tinggi, bersyarat | Routing/keamanan | [ ] Terbuka |
| FIN-02 | State machine, lock, dan settlement denda tunggal | Tinggi | Finance + Sewa | [ ] Terbuka |
| FIN-03 | Alokasi denda/pokok dan klasifikasi laporan | Tinggi | Finance + Sewa + Laporan | [ ] Terbuka |
| FIN-04 | Rekonsiliasi refund, saldo, status, dan jurnal | Tinggi | Sewa + Laporan | [ ] Terbuka |
| FIN-05 | Lock transaksi dan idempotensi operasi | Tinggi | Pembayaran/refund | [ ] Terbuka |
| FIN-06 | Invoice atomik, prepayment linkage, status | Sedang | Penerbitan invoice | [ ] Terbuka |
| FIN-07 | Snapshot invoice dan adjustment perubahan sewa | Tinggi | Sewa + Invoice | [ ] Terbuka |
| FIN-08 | Buku deposit dan settlement extra charge | Tinggi | Pengembalian + Finance | [ ] Terbuka |
| FIN-09 | Lengkapi pengakuan otomatis; tolak jurnal kosong | Tinggi | AccountingService/integrasi | [ ] Terbuka |
| FIN-10 | Validasi schema, metode, eligibility, tutup buku | Sedang | Semua mutasi keuangan | [ ] Terbuka |
| FIN-11 | Kebijakan send-email, throttle, temp unik, state | Sedang | Invoice email | [ ] Terbuka |
| FIN-12 | Error UI/server dan status respons yang jelas | Sedang | Finance AJAX | [ ] Terbuka |
| FIN-13 | Rekonsiliasi rincian/status dokumen | Sedang | Detail invoice/PDF | [ ] Terbuka |
| FIN-14 | Formatter presisi dua desimal dan bulan Indonesia | Rendah | Tabel/PDF | [ ] Terbuka |
| FIN-15 | Validasi filter/paginasi dan batas beban | Sedang | DataTables/PDF/email | [ ] Terbuka |
| FIN-16 | Tambah regresi langsung operasi Finance | Sedang | Tests | [ ] Terbuka |

---

## 10. KEPUTUSAN BISNIS DAN URUTAN PERBAIKAN

### Keputusan yang perlu ditetapkan

- Apakah seluruh operasi ERP wajib melewati 2FA saat fitur aktif?
- Apakah izin melihat Finance juga berarti boleh mengirim invoice dan mengunduh dokumen pelanggan?
- Kapan piutang/pendapatan diakui, dan kapan invoice menjadi snapshot yang tidak boleh diedit?
- Bagaimana pemisahan pokok sewa, add-on, denda, deposit, refund, dan biaya tambahan pada settlement?
- Apa perlakuan invoice/pembayaran setelah cancellation atau extension?
- Apakah satu rental hanya memiliki satu invoice aktif, atau mendukung revisi/invoice tambahan?
- Apakah deposit/jurnal tertentu sengaja dikelola manual di luar aplikasi? Jika ya, batas integrasi harus dinyatakan jelas.

### Urutan implementasi yang disarankan

1. Tetapkan invariant transaksi serta kebijakan akses, lalu pusatkan service settlement agar Finance dan Sewa tidak berbeda perilaku.
2. Perbaiki state machine, lock dalam transaksi, identitas operasi, dan constraint database yang sesuai.
3. Rekonsiliasi invoice/refund/denda/deposit/extra charge dan jurnal sumbernya; jangan hanya membetulkan badge UI.
4. Bangun snapshot dokumen dan aturan adjustment, lalu selaraskan format nominal, tanggal, dan error handling.
5. Jalankan regresi pada database pengujian terisolasi; verifikasi PDF/email dan perilaku engine produksi.
6. Setelah aturan baru disepakati, lakukan penilaian rekonsiliasi data existing sebelum backfill. Jangan mengubah saldo historis atau menjalankan koreksi massal hanya berdasarkan dugaan dari audit statis.

**Kesimpulan:** dokumen ini merekam 16 pekerjaan terbuka dengan bukti sumber dan batas verifikasi. Status perbaikan pada audit Sewa/Laporan sebelumnya tidak otomatis menutup integrasi keuangan yang ditemukan pada snapshot ini. Tidak ada perbaikan atau transaksi yang dijalankan dalam pekerjaan pembuatan audit ini.
