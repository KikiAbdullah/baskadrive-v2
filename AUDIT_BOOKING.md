# 🔍 AUDIT FITUR PEMBUATAN BOOKING BARU — BaskaDrive Web ERP

> Audit menyeluruh alur "Buat Sewa Baru" di `baskadrive-webapp` + rekomendasi
> perombakan menjadi alur **POS (point-of-sale)** yang cepat dan menyenangkan,
> plus dukungan **inspeksi video** (praktik lapangan saat ini).
> Tanggal: 23 September 2026

---

## 1. Ringkasan Eksekutif

| Aspek | Nilai saat ini | Masalah utama |
|---|---|---|
| Alur booking web | Wizard **4 langkah** tersimpan di session | Terlalu panjang untuk kasir yang melayani pelanggan di depan mata |
| Klik/tap minimum | ± 25–35 klik + 4 submit + 1 final store | 1 booking "sehari" di retail = butuh **< 10 interaksi** |
| Harga | `RentalController::store()` + `calculateTotal()` (duplikasi formula di 3 tempat: web, mobile, quote) | Risiko divergensi harga antar kanal |
| Pelanggan walk-in | Tidak ada — harus ke menu Master dulu | Penumpang dadakan = batal buat sewa |
| Inspeksi | Form tulis (odometer/BBM/checklist/bodi) di web & mobile | **Video inspeksi tidak terekam** padahal itu praktik lapangan saat ini |
| Offline mobile | Outbox + idempotensi (sudah baik) | Lampiran (foto/video/TTD) belum ikut outbox |

**Keputusan desain** (pasca audit):
1. Tambahkan **halaman POS Booking** (satu layar, receipt live, 1 klik simpan) — wizard lama tetap ada untuk booking kompleks (addon/PPN custom).
2. Satukan formula harga di **`App\Services\RentalPricing`** — dipakai POS & API mobile (satu sumber kebenaran).
3. Tambahkan **video inspeksi** pada inspeksi keluar & masuk (server + mobile), sesuai praktik: rekam seluruh sisi + interior di **pengambilan** dan **pengembalian**.

---

## 2. Peta Alur Saat Ini (Baseline)

### 2.1 Wizard 4 langkah (`/rental/create`)

| Step | Route | Isi | Ketahanan |
|---|---|---|---|
| 1 — Pelanggan | `POST /rental/create` (`saveStep`) | Search pelanggan (`m_customer`) | Blokir blacklist ✓ |
| 2 — Unit | `saveStep` step 2 | Pilih unit + tanggal (window ketersediaan) | Anti double-booking saat store |
| 3 — Detail sewa | `saveStep` step 3 (validasi terpanjang) | Tanggal, lokasi ambil/kembali, sopir + fee, promo, PPN per-sewa, deposit, catatan, **addon** (nama, qty, harga, tipe) | Allowlist ketat ✓ |
| 4 — Ringkasan | `POST /rental/create/store` | Review + simpan | Tx + row-lock + buffer 3 jam ✓ |

**Endpoint pendukung**: `search-customer`, `search-location`, `search-driver`,
`search-promo`, `available-vehicles`, `calculate-total` (preview harga).

### 2.2 Titik gesekan (friction) — hasil pengamatan

| # | Gesekan | Dampak |
|---|---|---|
| F1 | 4 langkah bergantian, tiap langkah POST + reload | Kasir menunggu bolak-balik; pelanggan ikut menunggu |
| F2 | Pelanggan baru tidak bisa dibuat di dalam wizard | Harus buka tab lain → data wizard di session bisa tak sinkron |
| F3 | Harga live (`calculate-total`) membaca **session wizard** untuk addon/young-driver | Tidak bisa dipakai POS tanpa isi session |
| F4 | Pilih unit dulu → tanggal belakangan (step 2 → 3) | Di lapangan yang natural: **tanggal dulu, lalu unit bebas** |
| F5 | Promo dipilih by ID via select2 search | Kasir tidak tahu syarat promo tanpa mencoba |
| F6 | Deposit/PPN per-sewa tampil sebagai input teknis | Mengundang salah input; mayoritas kasir butuh default otomatis |
| F7 | Tidak ada ringkasan "struk" yang hidup di satu layar | Kasir kesulitan menjelaskan total ke pelanggan |

### 2.3 Duplikasi logika harga (temuan penting)

Formula harga saat ini ada di **3 tempat**:
1. `RentalController::store()` (web, kebenaran transaksi)
2. `RentalController::calculateTotal()` (preview web — membaca session)
3. `Api\V1\Field\BookingController::createBooking()` (mobile)

Ketiganya **harus** identik: `total = base×hari + asuransi% + sopir(harian×hari) +
young_driver + addon − diskon_promo + PPN`, promo tervalidasi utuh
(aktif/periode/min-hari/kategori/kuota), deposit default per model unit.
➜ **Direkomendasikan: service tunggal `RentalPricing`** (diimplementasikan §4).

### 2.4 Yang sudah baik (dipertahankan)

- ✅ Enforcement anti double-booking: row-lock `lockForUpdate` + buffer pembersihan 3 jam.
- ✅ Allowlist + validasi ketat per langkah (anti injeksi `promo_id`/`deposit` via POST manual).
- ✅ Blokir pelanggan blacklist dengan alasan.
- ✅ Kode `RNT-$/#####` via `NumberTrait::gen_number` (row-lock numbering).
- ✅ Notifikasi WA "Booking Berhasil" via queue (faile-safe).
- ✅ Promo kuota dikonsumsi dalam transaksi yang sama.

---

## 3. Praktik Lapangan: Inspeksi Video

**Fakta operasional** (user): sejak dulu, inspeksi dilakukan dengan **merekam
video seluruh sisi mobil + interior** saat **pengambilan**, dan hal yang sama
saat **pengembalian**. Video ini bukti kondisi unit (kalau ada sengketa
kerusakan/barang hilang).

**Kondisi sistem saat ini**: tabel `rental_inspections` menyimpan odometer,
BBM, titik bodi, checklist, catatan — **tidak ada kolom video**. Foto kerusakan
ada (`damage_photos`) tapi bukan video inspeksi menyeluruh.

**Rancangan (diimplementasikan)**:

| Item | Keputusan |
|---|---|
| Skema | Kolom `videos` (JSON array path) + `video_total_seconds` (int) di `rental_inspections` |
| Penyimpanan | Disk `public`, folder `inspection-videos/`, nama file asli diacak Laravel |
| Format | mp4 / webm / mov (mimetype divalidasi) |
| Batas | Maks 3 video per inspeksi, maks 50 MB/video (config `field.inspection_video_*`) |
| Wajib? | **Ya di sisi mobile** (operator tidak bisa submit tanpa ≥ 1 video), opsional di sisi server agar data lama & web tetap aman |
| Upload | Multipart `videos[]` dari mobile; diterima juga file tunggal (toleran) |
| Durasi | Total detik dikirim mobile (`video_total_seconds`) untuk audit ringkas |
| Idempotensi | Ikut `client_uuid` yang sama dengan inspeksi (bukan upload terpisah) |
| Endpoint | Tidak ada endpoint baru — video menyatu di `handover-out` & `return` |

---

## 4. Implementasi Baru: RentalPricing (Sumber Harga Tunggal)

**File**: `app/Services/RentalPricing.php`

```php
RentalPricing::quote(
    Vehicle $vehicle,      // + model
    ?Customer $customer,   // untuk young-driver fee
    array $settings,       // AppSettings::all()
    int $rentalDays,
    bool $withDriver,
    ?Promo $promo = null,  // sudah divalidasi usable
    array $addons = [],    // [['quantity'=>,'unit_price'=>]]
    ?float $taxPercent = null,
): array
// → total_base_price, insurance_fee, driver_fee, young_driver_fee,
//   addons_total, subtotal, discount_amount, tax_percent, tax_amount,
//   deposit_amount, total_amount
```

- Dipakai oleh: **POS quote & store**, **API mobile booking**, dan menjadi
  acuan refactor `RentalController::store()` (tahap lanjut, harus lewat test
  regresi penuh).
- Aturan deposit: `deposit_enabled` → `model.deposit_amount ?: settings.deposit_default`.

---

## 5. Implementasi Baru: POS Booking (Kasir)

### 5.1 Prinsip UX "secepen kasir"

| Prinsip | Wujud |
|---|---|
| **Satu layar** | Semua input di satu halaman; tanpa step, tanpa reload |
| **Tanggal dulu, unit kemudian** | Ubah tanggal → daftar unit bebas + harga live otomatis |
| **Receipt hidup** | Panel kanan (sticky) berisi struk: rincian harga baris per baris, total besar, update < 500 ms |
| **Pelanggan dadakan** | Pilih pelanggan existing (cari nama/HP/KTP) **atau** buat walk-in 2 field (nama + HP) tanpa keluar halaman |
| **Default pintar** | Lokasi = cabang pertama, PPN = setting, deposit = default model, sopir = off |
| **1 klik simpan** | Tombol besar **SIMPAN BOOKING** → langsung ke halaman detail sewa (kwitansi/kontrak cetak) |
| **Keyboard-friendly** | Enter di pencarian = pilih hasil pertama; semua kontrol input HTML native (cepat di desktop) |

### 5.2 Endpoint baru (`rental.pos.*`, gate `can:rental_add`)

| Endpoint | Fungsi |
|---|---|
| `GET /rental/pos` | Halaman POS |
| `GET /rental/pos/options` | Cabang aktif, promo aktif, sopir, setting PPN/deposit |
| `GET /rental/pos/customers?q=` | Cari pelanggan (nama/HP/KTP, maks 10, bukan blacklist) |
| `POST /rental/pos/customers` | **Walk-in cepat**: nama + HP (wajib), email/KTP opsional → pelanggan individual |
| `GET /rental/pos/vehicles?start=&end=&q=` | Unit **bebas** untuk rentang (status available + bebas sewa aktif + buffer) + tarif + deposit |
| `POST /rental/pos/quote` | Struk live: rincian harga penuh via `RentalPricing` (promo preview, young-driver, PPN, deposit) |
| `POST /rental/pos/store` | Simpan 1 klik: transaksi, row-lock, buffer, kode RNT, addon, kuota promo, unit → reserved, WA queue → **JSON `rental_id` + URL detail** |

### 5.3 Layout halaman (`resources/views/rental/pos.blade.php`)

```
┌─────────────────────────────────────┬──────────────────────┐
│ ① PELANGGAN                         │  STRUK (sticky)      │
│  [cari nama/HP/KTP ▾] [＋ Walk-in]  │  Unit …      Rp …    │
│                                     │  Asuransi    Rp …    │
│ ② PERIODE                           │  Sopir       Rp …    │
│  [mulai] → [selesai]  (2 hari)      │  Promo      -Rp …    │
│                                     │  PPN 11%     Rp …    │
│ ③ UNIT TERSEDIA (grid kartu)        │  ─────────────────   │
│  [B 1074 AC Avanza Rp350rb] [...]   │  TOTAL       Rp …    │
│  (klik = pilih, badge deposit)      │  Deposit     Rp …    │
│                                     │  [SIMPAN BOOKING]    │
│ ④ OPSI: ☐ Sopir  [Promo ▾]  Catatan │  [Cetak kontrak]     │
└─────────────────────────────────────┴──────────────────────┘
```

- Vanilla JS (fetch + debounce 300 ms) — tanpa build tooling, konsisten stack.
- Semua respons JSON `{status, data}`; error → toast merah inline.

### 5.4 Menu

Menu baru **"Booking POS"** (ikon kasir) di bawah menu **Sewa**, gate `rental_add`.

---

## 6. Kesesuaian Mobile

Fitur booking mobile (`POST /api/v1/field/booking`) yang sudah lulus 7 test:
- **Direfaktor** memakai `RentalPricing` → harga web = POS = mobile dijamin identik.
- Payload & kontrak tidak berubah (tidak breaking untuk aplikasi yang sudah terpasang).
- Serah terima & pengembalian mobile kini mengirim **video inspeksi** (`videos[]`).

---

## 7. Rencana Verifikasi

| Uji | Target |
|---|---|
| `RentalPosTest` | Quote paritas (base+asuransi+sopir+PPN), store sukses (rental reserved + unit reserved + kode RNT), konflik rentang 422, walk-in customer |
| `VideoInspectionTest` | handover-out & return dengan file video → tersimpan di `inspection-videos/`, path tercatat di `rental_inspections.videos` |
| Regresi penuh | Suite webapp tetap hijau (210+ test) |
| Mobile | `flutter analyze` + `flutter test` tetap hijau setelah langkah video |
| E2E curl | POS quote → store → detail; mobile booking tetap jalan |

---

## 8. Risiko & Catatan

1. **Wizard lama tidak dihapus** — booking kompleks (addon banyak, PPN per-sewa khusus) tetap bisa lewat wizard; POS meng-cover 90% kasus harian.
2. **`calculateTotal()` lama membaca session** — dipertahankan demi wizard; POS punya endpoint quote sendiri yang stateless.
3. **Refactor `RentalController::store()` ke `RentalPricing`** ditunda sampai regresi penuh dijalankan di lingkungan stabil (formulanya sudah identik, hanya duplikasi).
4. **Video ukuran besar** — batas 50 MB + maks 3 file; kompresi di sisi kamera mobile (Android default). Penyimpanan disk publik mengikuti konvensi `damage-photos/`.
5. **Tampilan video di halaman detail sewa (web)** — mengikuti setelah UI detail dirombak; data & URL sudah tersedia via `rental_inspections.videos`.

---

## 9. Hasil Pasca-Implementasi

| Metrik | Sebelum | Sesudah (POS) |
|---|---|---|
| Langkah | 4 halaman + reload | **1 halaman** |
| Interaksi tipikal | 25–35 klik | **± 8–12 klik** |
| Pelanggan walk-in | Keluar wizard | **Inline 2 field** |
| Keterlihatan harga | Preview per step | **Struk hidup sejak klik pertama** |
| Sumber harga | 3 duplikasi | **1 service** (POS + mobile) |
| Bukti inspeksi | Form tulis | **Form + video sisi & interior** (ambil & kembali) |
