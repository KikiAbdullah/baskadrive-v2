# 🔍 AUDIT — MENU SEWA (RENTAL OPERASIONAL)
## BaskaDrive Rental ERP

**Tanggal Audit:** 15 September 2026  
**Cakupan:** `app/Http/Controllers/Rental/RentalController.php` (1.168 baris) + `DashboardController.php`, `app/Models/Rental.php` & relasi, `routes/rental.php:172-230`, `resources/views/rental/**` (13 file), `resources/views/dashboard/index.blade.php` (249 baris), migrasi `100011/013/014/019-022/025-026/160000`  
**Metode:** Static review + probe route/middleware + validasi enum DB vs controller  
**Legenda:** `[X]` = sudah diperbaiki — `[ ]` = temuan terbuka (belum diperbaiki)

> Temuan baru pasca perbaikan M-01..M-20 & S-01..S-04. **Status tersinkron dengan kode per 16/09/2026** (`php artisan test` → 34/34 PASS). FASE 1–3 tuntas; tersisa **6 temuan prioritas-rendah terbuka** (UX/polish + 1 tech-debt): fallback Safari `return`, `prompt()` handover, `target=_blank` `_step-1`, link rental→VIEWER 403 di Dashboard, `invoice.print` redirect-only, dan trigger kendaraan untuk `reserved`/`overdue`.

---

## DAFTAR ISI

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Inventaris Rute & View](#2-inventaris-rute--view)
3. [Defisit Fungsional per Sub-Menu](#3-defisit-fungsional-per-sub-menu)
4. [Validasi & Konsistensi Enum](#4-validasi--konsistensi-enum)
5. [Race Condition & Konkurensi](#5-race-condition--konkurensi)
6. [Permission & Keamanan](#6-permission--keamanan)
7. [UI/UX, Responsivitas & Aksesibilitas](#7-uiux-responsivitas--aksesibilitas)
8. [Rute Mati & Tech Debt](#8-rute-mati--tech-debt)
9. [Integrasi Lintas Modul](#9-integrasi-lintas-modul)
10. [Matriks Prioritas Action Plan](#10-matriks-prioritas-action-plan)

---

## 1. RINGKASAN EKSEKUTIF

Alur sewa inti (wizard → reserved → confirm → ongoing → return → completed, plus invoice/payment) **berjalan**. **FASE 1, 2, dan 3 TUNTAS 15/15 task** (integritas, finansial, permission, UX, race, scheduler, jurnal), terverifikasi **34/34 tes** (suite lama + `test_mark_overdue_sweep`). **Disinkronkan dengan kode 16/09/2026**: tersisa **6 polish/tech-debt prioritas-rendah** berstatus `[ ]` (rincian di §10) — tidak memengaruhi integritas data/finansial.

| Kategori | Status |
|---|---|
| 🔴 Tinggi (integritas data / finansial) | **SELESAI — 6/6 (FASE 1)** |
| 🟡 Sedang (permission / race / validasi) | **SELESAI — 4/4 (FASE 2)** |
| 🟢 Rendah (UX / tech-debt) | **SELESAI — 5/5 (FASE 3)** |
| ⚪ Sisa polish/tech-debt (di luar FASE) | **6 terbuka `[ ]`** — Safari fallback, `prompt()`, `target=_blank`, VIEWER 403 link, `invoice.print`, trigger `reserved/overdue` |

---

## 2. INVENTARIS RUTE & VIEW

**33 public method** di `RentalController.php` melayani 22 route rental. Semua route berada di bawah `auth`+`two_factor` dan grup `can:rental_view` (`rental.php:172`), kecuali 2 yang punya gate tambahan (`cancel`→`rental_cancel:191`, `fine.waive`→`fine_waive:213`).

| View | Controller | Route | Fungsi |
|---|---|---|---|
| `rental/index` | `index` 420, `data` 449 | `GET /rental` | Daftar sewa + tab status |
| `rental/create` + `_step-1..4` | `create` 38, `renderStep` 360, `createStep` 57 | Wizard 4 langkah |
| `rental/show` | `show` 539 | Command center per sewa |
| `rental/edit` | `edit` 555, `update` 575 | Edit sewa |
| `rental/return` | `returnForm` 833, `returnStore` 844 | Pengembalian |
| `rental/handover` | `handoverForm` 1104, `handoverStore` 1118 | Inspeksi out/in |
| `rental/invoice` | `invoiceGenerate` 952, `invoiceStore` 968 | Buat invoice |
| `rental/print` | `printContract` 689 | PDF kontrak |
| `rental/button_option` | `getButtonOption` 479 | Tombol aksi baris |
| `dashboard/index` | `DashboardController:index` 19 | Peta armada + kalender sewa |

**13 file view rental** semua ter-referensi; **10 route rental orphan** (lihat §8).

---

## 3. DEFISIT FUNGSIONAL PER SUB-MENU

### 3.1 Wizard Pembuatan Sewa — **TUNTAS (15/09/2026 sore)**
| Status | Temuan | Perbaikan | Lokasi |
|---|---|---|---|
| [X] | `saveStep` simpan field apapun ke session tanpa validasi | Allowlist + rule ketat per langkah (`exists:*`, `numeric min:0`, addons `array max:10` + rule per item, `driver_fee`/`notes` ikut dinormalisasi); `store` kini re-check blacklist + validasi ulang | `RentalController.php` saveStep/store |
| [X] | Pencarian pelanggan client-side, `searchCustomer` orphan | `_step-1` kini debounce 300ms → AJAX `rental.create.search-customer` dengan `escapeHtml`; fallback filter lokal bila error | `rental/_step-1.blade.php` |
| [X] | `availableVehicles` 500 saat tanggal kosong | `validate(['start_date'=>'required|date','end_date'=>'required|date|after:start_date'])` | `RentalController.php` |
| [X] | `create/step/{step}` GET ubah session | Respons AJAX diberi header `Cache-Control: no-store` (state tetap diperlukan wizard); risiko cache proxy hilang | `RentalController::createStep` |

### 3.2 Daftar Sewa & Export
| [X] | `export` OOM `get()` | → `cursor()` streaming; +validasi filter | `RentalController.php:752` |
| [X] | `whereDate` abaikan jam | → bound `startOfDay()/endOfDay()` di `export` DAN `data` | `RentalController.php` |

### 3.3 Detail / Show — **TUNTAS**
| [X] | `$fine->reason` kolom tak ada | → `$fine->description` + badge status denda | `show.blade.php` |
| [X] | Edit status bebas | → **state machine** di `update()` (reserved→reserved/ongoing saja; completed/cancelled terkunci; ongoing→ongoing) + dropdown view mengikuti peta yang sama; pesan tolakan eksplisit | `RentalController::update`, `edit.blade.php` |
| [X] | Edit kehilangan jam | → `datetime-local` `Y-m-d\TH:i` | `edit.blade.php:47-53` |

### 3.4 Pengembalian — **TUNTAS**
| [X] | minOdo salah sumber | → patokan `handover_out.odometer` (fallback mileage) |
| [X] | tanggal kembali < awal sewa | → closure rule menolak |
| [X] | `extra_charge` bisa negatif | → `min:0` + `deposit_refund` dibatasi ≤ deposit |
| [X] | kendaraan rusak langsung available | → `damaged`/`fair` → status `maintenance` |

### 3.5 Perpanjangan — **TUNTAS**
| [X] | overlap perpanjangan tak dicek | → `assertVehicleFree` saat store DAN approve (buffer 3 jam), `lockForUpdate` pada rental/extension |
| [X] | `rental_days` drift | → dihitung ulang saat approve |

### 3.6 Denda, Pembayaran, Refund, Invoice — **TUNTAS**
| [X] | `fine_type` bebas | → `in:late_return,damage,cleaning,fuel,lost_item,other` |
| [X] | overpay tak dibendung, status selalu paid | → `lockForUpdate` invoice+rental, tolak bila `paid+amount > total`, `payment_status` = `partial` vs `paid` (teruji `test_overpayment_blocked_and_partial_status`) |
| [X] | refund enum mismatch total | → `in:deposit_return,overpayment,cancellation,damage_deposit` (+alias lama dinormalisasi), refund ≤ `payment.amount − refund berjalan`, payment → `refunded` saat tuntas |
| [X] | diskon bisa > subtotal | → `computeDiscount` clamp `min(discount, subtotal)` |
| [X] | add-on tak masuk total | → dihitung ke `total_amount` (store, calculateTotal, update) & `sub_total` invoice (komponen kotor, anti double-diskon) |
| [X] | due_date masa lalu | → `after_or_equal:today` |

### 3.7 Inspeksi Handover — **TUNTAS**
| [X] | duplikat inspeksi | → `updateOrCreate` per (rental, tipe) |
| [X] | urutan & odometer | → `handover_in` wajib setelah `handover_out`; odometer akhir ≥ awal; handover_out menyinkron mileage kendaraan |
| [X] | `body_damage_points` null diam-diam | → validasi `json` + cek `is_array` eksplisit (throw jika rusak) |

---

## 4. VALIDASI & KONSISTENSI ENUM — **TUNTAS 6/6**

| Status | Before | After — **FIXED 15/09/2026** | Lokasi |
|---|---|---|---|
| [X] | `vehicle_condition` `good,minor_damage,damage` vs enum DB | → validasi `in:excellent,good,fair,damaged`; opsi view disamakan; **alias lama dinormalisasi** (`minor_damage→fair`, `damage→damaged`) sehingga request lama tak error | returnStore + `return.blade.php` |
| [X] | `payment_method` `transfer` tak ada di enum | → `in:cash,bank_transfer,credit_card,debit_card,e_wallet,other` + normalisasi `transfer→bank_transfer` (teruji) | paymentStore |
| [X] | `refund_type` 3/3 mismatch → QueryException | → enum DB penuh + alias `deposit/cancelled` dinormalisasi | refundStore |
| [X] | Promo tak cek `is_active/valid_from/to/min_rental_days` | → helper `usablePromo()` memvalidasi SEMUA syarat + kuota dengan `lockForUpdate` (dipakai store, update, calculateTotal) | `RentalController` |
| [X] | `young_driver_fee` selalu 0 | → dihitung dari `customer.date_of_birth` vs `young_driver_age` setting; masuk subtotal & invoice | `youngDriverFee()` |
| [X] | Driver fee precedence & inkonsistensi flat/per-hari | → diseragamkan TOTAL = tarif harian × hari di store/calculateTotal/update; fallback `(int)` precedence dihilangkan | `RentalController` |

---

## 5. RACE CONDITION & KONKURENSI

| Status | Skenario | Status perbaikan |
|---|---|---|
| [X] | Double-booking kendaraan | → `assertVehicleFree()` + `Vehicle::lockForUpdate()` di `store`, `update` (exclude dirinya), `extensionStore` & `extensionApprove`. Buffer 3 jam dipindah ke aritmetika PHP (`$start - 3h`) — portabel MySQL/SQLite & tetap memakai index `idx_rental_dates`. Teraudit uji: `test_double_booking_with_overlap_is_rejected` |
| [X] | Nomor sewa/invoice duplikat (`gen_number` MAX tanpa lock) | **FIXED** — `NumberTrait::lockNumberingRow()` (FOR UPDATE baris terakhir) dipanggil di 4 varian `gen_*`; portabel (try/catch untuk driver non-lock) |
| [X] | Perpanjangan overlap & approve ganda | → `lockForUpdate` pada rental & extension + cek overlap saat approve |
| [X] | Pembayaran konkuren overpay/lost-update | → lock rental+invoice, rekap `alreadyPaid` dari DB, tolak overpay, `partial/paid` akurat (teruji) |
| [X] | Jurnal swallow pada jalur uang | → `returnStore` & `paymentStore` kini `AccountingService::post` (throw) di dalam transaction — jurnal gagal = transaksi rollback; helper silent `postQuietly` tidak dipakai lagi di modul sewa |
| [X] | Dashboard fleetMap (skeleton/error UI) | **FIXED** — `mapLoading`/`mapError` skeleton+error, legend 4 warna, popup `esc()`-escaped, calendar `failure` handler |

---

## 6. PERMISSION & KEAMANAN — **TUNTAS**

Permission baru dibuat & di-seed ("10 dibuat, 33 sudah ada"): `rental_add, rental_edit, rental_confirm, rental_return, rental_export, fine_add, fine_pay, finance_invoice_add, finance_payment_add, finance_refund_add`.

| Status | Route | Gate baru |
|---|---|---|
| [X] | wizard `create/create.save/create.step/create.store` | `can:rental_add` |
| [X] | `confirm` | `can:rental_confirm` |
| [X] | `edit` + `update` + `extension.*` | `can:rental_edit` |
| [X] | `return.*` + `handover.*` | `can:rental_return` |
| [X] | `fine.store` / `fine.pay` (rental & finance) | `can:fine_add` / `can:fine_pay` |
| [X] | `invoice.generate/store` | `can:finance_invoice_add` |
| [X] | `payment.store` / `refund.store` | `can:finance_payment_add` / `can:finance_refund_add` |
| [X] | `export` | `can:rental_export` |
| [X] | UI: tombol EDIT/KONFIRMASI/PENGEMBALIAN/INVOICE & "Buat Sewa Baru" di-`@can`; item menu Sewa/Fleet/Keuangan/Akuntansi/Laporan dibungkus `@can:*_view` | — |

Role mapping di `RoleSeeder`: STAFF = operasional penuh tanpa cancel/waive/export/refund; SUPERVISOR = +cancel/export/refund (tanpa waive); MANAGER/ADMIN = semua. Teruji: `test_write_gates_per_permission` (STAFF create 200, export 403; VIEWER create 403).

CSRF ✅. **Double-submit** — **TUNTAS (FASE 2-8 diverifikasi)**: form native dikunci oleh `app-enhancements.js` global, DAN tombol AJAX (`.btn-confirm`/`.btn-cancel`/`.btn-pay`/`.btn-waive`/`.btnReturn`/`.btnInvoice`) kini punya guard `ajax-busy` eksplisit (`app-enhancements.js:44-59`) + `js-async-form` mengunci submit (`show.blade.php:506-507`). Wizard next-step/submit tetap loader Swal dengan guard busy.

---

## 7. UI/UX, RESPONSIVITAS & AKSESIBILITAS

**Baik:** `container-xxl`, `flex-column flex-md-row`, `table-responsive`, tab `flex-wrap`, `card h-100`.

**Status temuan (verifikasi 16/09/2026 terhadap kode):**
* `[X]` Tidak ada `is-invalid`/`@error` di `return`, `handover`, `invoice` — hanya `create` pakai Swal. **DIVERIFIKASI TUNTAS**: `is-invalid`/`@error` ada di `return.blade.php` (`return_date`, `return_mileage`, `vehicle_condition`), `handover.blade.php` (`odometer`), `invoice.blade.php` (`due_date`).
* `[X]` ~~`edit.blade.php` datetime kehilangan jam~~ (`datetime-local` preserve jam, lihat 3.3). ~~`_step-3:62` precedence `&&` vs `||` pada toggle `taxPercentSection`~~ → **DIVERIFIKASI TUNTAS**: variabel `$taxOn` terpusat (`_step-3.blade.php:59-64`, ternary benar). ~~`_step-4:11` query DB di view (N+1)~~ → **DIVERIFIKASI TUNTAS**: `renderStep` mengirim `stepCustomer/stepVehicle/stepPickupLoc/stepReturnLoc/stepDriver/stepPromo`, view tidak query lagi (`_step-4.blade.php:11-22`). **Sisa terbuka**: `return.blade.php:24` `datetime-local` tanpa fallback Safari **[ ]**.
* `[X]` `handover.blade.php` diagram — **DIVERIFIKASI**: kini responsif (`width:100%; max-width:500px; aspect-ratio`; `touch-action:none` + handler `touchend`, bukan lagi fixed 500×220 desktop-only) & XSS badge ditutup helper `esc()` (`handover.blade.php:146,154`). **Sisa terbuka**: input keterangan titik masih pakai `prompt()` blocking (`:174`) **[ ]**.
* `[X]` `_step-1` kartu pelanggan — **DIVERIFIKASI**: `role=button`/`tabindex=0`/`aria-pressed` + handler keyboard Enter/Space (`_step-1.blade.php:29,83-88`) → keyboard-accessible. **Sisa terbuka**: link `target=_blank` ke `master.customer.create` masih memutus flow wizard (`_step-1.blade.php:20`) **[ ]**.
* `[X]` Dashboard map/kalender — **DIVERIFIKASI**: skeleton `#mapLoading`, `#mapError` + `#calendarError` (handler `failure`), legend 4 warna, popup di-`esc()` (`dashboard/index.blade.php:112-135,205-236`). **Sisa terbuka**: `upcomingReturns`/`upcomingPickups` & link kalender tetap menaruh `rental.show` ke VIEWER (tak punya `rental_view` → 403); belum di-`@can('rental_view')` **[ ]**.

---

## 8. RUTE MATI & TECH DEBT

| Status | Route | Bukti | Keterangan |
|---|---|---|---|
| [X] | `rental.export` `178` | **FIXED** — tombol Export CSV di `rental/index.blade.php` header (`can:rental_export`, bawa filter status) |
| [X] | `search-customer` `185` | Kini dipakai AJAX `_step-1` (debounce) | **TERHUBUNG** (fix 3.1) |
| [X] | `extension.store/approve/reject` | **FIXED** — form ajukan + tombol Setujui/Tolak inline (pending) di `show.blade.php` (`js-ext-approve/reject`, gate `rental_edit`) |
| [X] | `fine.store/pay` | **FIXED** — form tambah denda + tombol Bayar (unpaid) & Bebaskan di `show.blade.php` (`js-fine-pay/waive`, gate `fine_add/pay/waive`) |
| [X] | `payment.store` | **TERHUBUNG** — form "Catat Pembayaran" (`js-async-form`) di card Pembayaran `show.blade.php:287-316` | Ter-gate `finance_payment_add` |
| [X] | `refund.store` | **TERHUBUNG** — form Refund (`js-async-form`, `<details>`) di `show.blade.php:318-354` | Ter-gate `finance_refund_add` |
| [ ] | `invoice.print` | Hanya redirect (`RentalController::invoicePrint`) | Tech debt kecil — sengaja delegasi ke `finance.invoice.print`, dibiarkan |
| [X] | URL hardcode | **TUNTAS** — `create.blade.php:102` `@json(route('rental.create.step', …))`, `index:194,234` pakai `route('rental.confirm'/'rental.cancel')` | Tidak ada lagi path string mentah |
| [X] | `_step-2:56` HTML concat tanpa escape | **TUNTAS** — helper `esc()` diterapkan pada seluruh nilai kendaraan (`_step-2.blade.php:51` dst) | FASE 3-13 |

---

## 9. INTEGRASI LINTAS MODUL

| Status | Tema | Detail |
|---|---|---|
| [X] | Period lock merata | `assertPeriodOpen` kini dipanggil di `store` tdk perlu (tanggal future), `update` (tanggal LAMA **dan BARU**), `returnStore`, `paymentStore`, `refundStore`, `invoiceStore` — sebelumnya bolong |
| [X] | Soft delete | **TUNTAS (FASE 3-11)** — `SoftDeletes` di model `Rental`/`Invoice`/`Payment`/`Refund`; kolom `softDeletes()` ada di migrasi `100011/100020/100021/100022` | `migrate:fresh --seed` tetap balanced (teruji) |
| [X] | Status `overdue` | **TUNTAS (FASE 3-12)** — command `rentals:mark-overdue` (`MarkOverdueRentals.php`, grace `overdue_grace_minutes`) + scheduler `everyTenMinutes()` di `bootstrap/app.php:38`; teruji `test_mark_overdue_sweep_flags_late_rentals` (idempoten, kendaraan tak disentuh) | trigger membiarkan `overdue` → armada tetap `rented` |
| [X] | WA `sendRentalWA` silent-fail | **TUNTAS (FASE 3-12)** — `sendRentalWA` kini `SendWhatsAppNotification::dispatch` (job queue tries 3, backoff 30/120s, `failed()`→`Log::error`) — bukan lagi `try{}catch{}` kosong; scheduler `queue:work --stop-when-empty` tiap menit (`app.php:39`) | `RentalController.php:1588`, `app/Jobs/SendWhatsAppNotification.php` |
| [X] | Promo quota preview | `calculateTotal` memakai `usablePromo(lock:false)` — preview & eksekusi konsisten |
| [ ] | Vehicle status trigger | **masih terbuka** — `trg_rental_after_update_vehicle_status` hanya tangani `ongoing→rented` & `completed/cancelled→available`; `reserved`/`overdue` TIDAK ditangani trigger. Tertutup sebagian oleh update eksplisit di `store` (`reserved`) & `returnStore`. Revisi trigger MySQL = perubahan schema (butuh migrasi baru) | `100026_create_rental_erp_function_and_triggers.php:50-62` |

---

## 10. MATRIKS PRIORITAS ACTION PLAN

```
[FASE 1 — Integritas Data & Finansial] — TUNTAS 6/6 ✅ (15/09/2026)
  [X] 1. overlap+buffer check lockForUpdate di store/update/extension(+approve)
  [X] 2. enum validasi ↔ DB sinkron (return/payment/refund) + normalisasi alias
  [X] 3. validasi promo lengkap (usablePromo) + clamp diskon (computeDiscount)
  [X] 4. lock invoice + cegah overpay + partial vs paid
  [X] 5. state machine status di update (+ view dropdown mengikuti)
  [X] 6. add-on & young driver masuk total_amount & invoice sub_total

[FASE 2 — Keamanan & Audit] — TUNTAS 4/4 ✅
  [X] 7. permission tulis terpisah (rental_add/edit/confirm/return/export,
         fine_add/pay, finance_invoice/payment/refund) + seed + @can di tombol & menu
  [X] 8. form native dikunci app-enhancements.js; tombol AJAX (.btn-confirm/.btn-cancel/
         .btn-pay/.btn-waive/.btnReturn/.btnInvoice) guard `ajax-busy` eksplisit +
         js-async-form lock → proteksi ganda DONE
  [X] 9. period lock konsisten (update cek tgl lama+baru; return/payment/refund/invoice)
  [X] 10. jurnal jalur uang jadi hard-post (gagal jurnal = rollback transaksi)

[FASE 3 — Robustness & UX] — **TUNTAS 5/5 ✅ (15-16/09/2026)**
  [X] 11. gen_number lock baris terakhir (`NumberTrait::lockNumberingRow` FOR UPDATE) di 4 varian + SoftDeletes pada tr_rental/invoice/payment/refund
  [X] 12. WA `SendWhatsAppNotification` (tries 3, backoff 30/120s, failed→Log) + `rentals:mark-overdue` command (grace setting) + scheduler `mark-overdue` tiap 10 menit & `queue:work --stop-when-empty` tiap menit
  [X] 13. Sisa client: escape HTML _step-2 (helper `esc()`), `route()` di index & create, `@error`/`is-invalid` tambah (return/handover/invoice), precedence `taxOn` variabel terpusat, query _step-4 dipindah ke `renderStep`
  [X] 14. UI extension (form+approve/reject), fine (tambah+bayar/waive), payment, refund — card baru di `show.blade.php` + handler async `js-async-form`; tombol Export di `index` header (`can:rental_export`)
  [X] 15. Dashboard: skeleton loader, legend 4 warna, error UI map+kalender (handler `.fail()`/`failure`), popup di-escape

### Verifikasi batch 15/09/2026 (sore + FASE 3)
- `php artisan view:cache` + `view:clear` → **berhasil** (semua view kompilasi)
- `php artisan test` → **34/34 PASS** (33 + `test_mark_overdue_sweep_flags_late_rentals` — idempoten + kendaraan tetap rented)
- Binder di `Wizard` kini BOM-free; `Rental*` model SoftDeletes aktif → `migrate:fresh --seed` tetap balanced

### Sisa temuan TERBUKA `[ ]` (prioritas rendah — bukan blocker, belum diverifikasi sebagai "harus")
- [ ] UX: `return.blade.php:24` `datetime-local` tanpa fallback Safari (§7).
- [ ] UX: `handover.blade.php:174` input keterangan titik masih `prompt()` blocking (§7).
- [ ] UX: `_step-1.blade.php:20` link `target=_blank` memutus flow wizard (§7).
- [ ] UX: `dashboard/index.blade.php` link `rental.show`/`upcomingReturns` belum di-`@can('rental_view')` → VIEWER 403 (§7).
- [ ] Tech-debt kecil: `RentalController::invoicePrint` hanya redirect (by-design) (§8).
- [ ] DB trigger `trg_rental_after_update_vehicle_status` tak tangani `reserved`/`overdue` — butuh migrasi baru (di luar MySQL trigger skip di SQLite) (§9).

---

> Audit disusun 15/09/2026, **disinkronkan dengan implementasi 16/09/2026** (static review + `php artisan test` 34/34). `[X]` = terverifikasi ada di kode; 6 item `[ ]` di atas tetap terbuka sebagai UX/polish & tech-debt prioritas rendah. File terkait dicatat `path:line` agar presisi tanpa melebar.
