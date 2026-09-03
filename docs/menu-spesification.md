# Spesifikasi Menu & Route ERP Rental Mobil

## 8 Modul Utama — 13 Sub-Menu — 100+ Route

---

### MODUL 1: 🖥️ DASHBOARD & MONITORING
| Menu | Route | View | Controller | Method |
|------|-------|------|------------|--------|
| Dashboard Utama | `dashboard.index` | `dashboard.index` | `DashboardController@index` | GET |
| Kalender Sewa | `dashboard.calendar` | `dashboard.calendar` | `DashboardController@calendar` | GET |
| Monitor Armada (Peta) | `dashboard.fleet-map` | `dashboard.fleet-map` | `DashboardController@fleetMap` | GET |

---

### MODUL 2: 📋 MASTER DATA
#### Merek & Model
| Menu | Route | Sumber Data | CRUD |
|------|-------|-------------|------|
| Daftar Brand | `master.brand.index` | `m_brand` | ✅ |
| Data Brand (JSON) | `master.brand.data` | `m_brand` | GET |
| Create Brand | `master.brand.create` | `m_brand` | GET |
| Store Brand | `master.brand.store` | `m_brand` | POST |
| Edit Brand | `master.brand.edit` | `m_brand` | GET |
| Update Brand | `master.brand.update` | `m_brand` | PUT |
| Delete Brand | `master.brand.destroy` | `m_brand` | DELETE |
| Daftar Model | `master.vehicle-model.index` | `m_vehicle_model` | ✅ |
| Data Model (JSON) | `master.vehicle-model.data` | `m_vehicle_model` | GET |

#### Unit Mobil
| Menu | Route | Sumber Data | CRUD |
|------|-------|-------------|------|
| Daftar Unit | `master.vehicle.index` | `v_vehicle_list` | ✅ |
| Detail Unit | `master.vehicle.show` | `m_vehicle` | GET |
| Barcode | `master.vehicle.barcode` | `m_vehicle` | GET |

#### Pelanggan
| Menu | Route | Sumber Data | CRUD |
|------|-------|-------------|------|
| Daftar Pelanggan | `master.customer.index` | `v_customer_list` | ✅ |
| Detail Pelanggan | `master.customer.show` | `m_customer` | GET |
| Verifikasi | `master.customer.verify` | `m_customer` | PUT |
| Sewakan Langsung | `master.customer.rental-now` | — | POST |

#### Karyawan, Sopir, Lokasi, Workshop, Tipe Maintenance, Promo, COA
| Menu | Route | CRUD | Keterangan |
|------|-------|------|------------|
| Data Karyawan | `master.employee.*` | ✅ | + toggle active |
| Data Sopir | `master.driver.*` | ✅ | + history rental |
| Lokasi/Cabang | `master.location.*` | ✅ | resource |
| Bengkel Mitra | `master.workshop.*` | ✅ | resource |
| Tipe Maintenance | `master.maintenance-type.*` | ✅ | resource |
| Manajemen Promo | `master.promo.*` | ✅ | + toggle active |
| COA (Akun) | `master.coa.*` | ✅ | + tree view |

---

### MODUL 3: 🚗 OPERASIONAL SEWA
| Menu | Route | Sumber Data | Fungsi |
|------|-------|-------------|--------|
| Buat Sewa Baru | `rental.create.wizard` | — | Wizard 4 langkah |
| Step Wizard | `rental.create.step` | — | GET step/{step} |
| Search Customer | `rental.create.search-customer` | `m_customer` | GET (JSON) |
| Available Vehicles | `rental.create.available-vehicles` | `m_vehicle` | GET (JSON) |
| Kalkulasi Biaya | `rental.create.calculate-total` | — | POST (JSON) |
| Store Rental | `rental.create.store` | `tr_rental` | POST |
| **Daftar Sewa Aktif** | `rental.active` | `v_rental_active_list` | List ongoing/overdue |
| Data Aktif (JSON) | `rental.active.data` | `tr_rental` | GET |
| **Reservasi** | `rental.reserved` | `v_rental_reserved_list` | List booking |
| Konfirmasi Ambil | `rental.confirm` | `tr_rental` | PUT (ongoing) |
| Batalkan | `rental.cancel` | `tr_rental` | PUT (cancelled) |
| **Arsip Transaksi** | `rental.archive` | `v_rental_archive_list` | Histori |
| Export | `rental.export` | — | GET |

#### Detail Transaksi (Command Center) — `rental.detail.*`
| Sub-Menu | Route | Fungsi |
|----------|-------|--------|
| Detail & Kontrak | `rental.detail.show` | Overview 1 transaksi |
| Cetak Kontrak | `rental.detail.print` | PDF |
| **Perpanjangan** | | |
| Ajukan | `rental.detail.extension.store` | POST |
| Approve | `rental.detail.extension.approve` | PUT (manager) |
| Reject | `rental.detail.extension.reject` | PUT (manager) |
| **Pengembalian** | | |
| Form Return | `rental.detail.return.form` | GET |
| Proses Return | `rental.detail.return.store` | POST |
| **Denda** | | |
| Tambah Denda | `rental.detail.fine.store` | POST |
| Bayar | `rental.detail.fine.pay` | PUT |
| Waive | `rental.detail.fine.waive` | PUT |
| **Invoice** | | |
| Generate Invoice | `rental.detail.invoice.generate` | GET |
| Store Invoice | `rental.detail.invoice.store` | POST |
| Cetak Invoice | `rental.detail.invoice.print` | PDF |
| **Pembayaran** | | |
| Rekam Bayar | `rental.detail.payment.store` | POST |
| **Refund** | | |
| Proses Refund | `rental.detail.refund.store` | POST |

---

### MODUL 4: 🔧 FLEET MAINTENANCE & KERUSAKAN
| Menu | Route | Sumber Data | CRUD / Aksi |
|------|-------|-------------|-------------|
| Jadwal Maintenance | `fleet.maintenance.*` | `v_maintenance_list` | ✅ + complete + reschedule |
| Daftar Kerusakan | `fleet.damage.*` | `v_damage_list` | ✅ + upload foto, update status |
| Klaim Asuransi | `fleet.insurance-claim.*` | `tr_insurance_claim` | ✅ + update status claim |

---

### MODUL 5: 💰 KEUANGAN & PENAGIHAN
| Menu | Route | Sumber Data | Aksi |
|------|-------|-------------|------|
| Daftar Invoice | `finance.invoice.*` | `v_invoice_list` | Lihat, cetak, kirim email |
| Daftar Denda | `finance.fine.*` | `v_fine_list` | Tandai lunas, waive |
| Riwayat Pembayaran | `finance.payment` | `tr_payment` | Filter, cetak kwitansi |

---

### MODUL 6: 📊 AKUNTANSI
| Menu | Route | Sumber Data | Aksi |
|------|-------|-------------|------|
| Jurnal Umum | `accounting.journal.*` | `v_journal_list` | Filter, detail, export |
| Posting Jurnal Manual | `accounting.manual-journal.*` | `tr_journal` + `tr_journal_detail` | Form dinamis, validasi balance, posting |
| Buku Besar | `accounting.ledger.*` | `tr_journal_detail` + `m_coa` | Filter period, detail akun |

---

### MODUL 7: 📈 LAPORAN (Executive)
| Menu | Route | Sumber Data | Export |
|------|-------|-------------|--------|
| Pendapatan & Profit | `report.revenue` | `tr_rental`, `tr_payment`, `tr_maintenance` | PDF/Excel |
| Utilisasi Armada | `report.fleet-utilization` | `m_vehicle`, `tr_rental` | PDF/Excel |
| Top Pelanggan | `report.top-customers` | `m_customer`, `tr_rental` | PDF/Excel |
| Klaim & Denda | `report.claims` | `tr_fine`, `tr_insurance_claim` | PDF/Excel |
| Laporan Keuangan | `report.financial` | `tr_journal_detail`, `m_coa` | PDF/Excel |

---

### MODUL 8: ⚙️ SISTEM
| Menu | Route | Aksi |
|------|-------|------|
| Pengaturan Umum | `system.settings` | Set PPN, deposit, denda, email |
| Log Aktivitas | `system.activity-log` | Filter user & tanggal |

---

## Ringkasan Route

| Modul | Jumlah Route |
|-------|-------------|
| 1. Dashboard | 3 |
| 2. Master Data | 85 |
| 3. Operasional Sewa | 30 |
| 4. Fleet | 22 |
| 5. Keuangan | 12 |
| 6. Akuntansi | 10 |
| 7. Laporan | 11 |
| 8. Sistem | 4 |
| **Total** | **177 route** |

## Struktur File

```
routes/rental.php        → 130+ route rental ERP
routes/web.php           → include('rental.php') di dalam auth group
app/Http/Controllers/Rental/
├── DashboardController.php
├── MasterController.php
├── RentalController.php
├── FleetController.php
├── FinanceController.php
├── AccountingController.php
├── ReportController.php
└── SystemController.php
resources/views/rental/
├── dashboard/
├── master/
├── rental/
├── fleet/
├── finance/
├── accounting/
├── report/
└── system/
```