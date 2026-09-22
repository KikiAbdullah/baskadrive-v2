<?php

use App\Http\Controllers\Master\BrandController;
use App\Http\Controllers\Master\CoaController;
use App\Http\Controllers\Master\CustomerController;
use App\Http\Controllers\Master\DriverController;
use App\Http\Controllers\Master\EmployeeController;
use App\Http\Controllers\Master\LocationController;
use App\Http\Controllers\Master\MaintenanceTypeController;
use App\Http\Controllers\Master\PromoController;
use App\Http\Controllers\Master\VehicleController;
use App\Http\Controllers\Master\VehicleModelController;
use App\Http\Controllers\Master\WorkshopController;
use App\Http\Controllers\Rental\AccountingController;
use App\Http\Controllers\Rental\DashboardController;
use App\Http\Controllers\Rental\FinanceController;
use App\Http\Controllers\Rental\FleetController;
use App\Http\Controllers\Rental\RentalController;
use App\Http\Controllers\Rental\ReportController;
use App\Http\Controllers\Rental\SystemController;
use Illuminate\Support\Facades\Route;

// ===========================================
// MODUL 1: DASHBOARD & MONITORING
// ===========================================
Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.', 'middleware' => ['can:dashboard_view']], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');
    Route::get('/fleet-map', [DashboardController::class, 'fleetMap'])->name('fleet-map');
});

// ===========================================
// MODUL 2: MASTER DATA
// ===========================================
Route::group(['prefix' => 'master', 'as' => 'master.', 'middleware' => ['can:master_view']], function () {

    // Brand
    Route::group(['prefix' => 'brand', 'as' => 'brand.'], function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::get('/get-data', [BrandController::class, 'ajaxData'])->name('data');
        Route::get('/create', [BrandController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [BrandController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [BrandController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [BrandController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [BrandController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
    });

    // Vehicle Model
    Route::group(['prefix' => 'vehicle-model', 'as' => 'vehicle-model.'], function () {
        Route::get('/', [VehicleModelController::class, 'index'])->name('index');
        Route::get('/get-data', [VehicleModelController::class, 'ajaxData'])->name('data');
        Route::get('/create', [VehicleModelController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [VehicleModelController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [VehicleModelController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [VehicleModelController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [VehicleModelController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
    });

    // Vehicle (Unit Mobil)
    Route::group(['prefix' => 'vehicle', 'as' => 'vehicle.'], function () {
        Route::get('/', [VehicleController::class, 'index'])->name('index');
        Route::get('/get-data', [VehicleController::class, 'ajaxData'])->name('data');
        Route::get('/create', [VehicleController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [VehicleController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}', [VehicleController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [VehicleController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [VehicleController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [VehicleController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
        Route::get('/{id}/barcode', [VehicleController::class, 'barcode'])->name('barcode');
        Route::get('/{id}/barcode-pdf', [VehicleController::class, 'barcodePdf'])->name('barcode-pdf');
        Route::get('/{id}/history', [VehicleController::class, 'history'])->name('history');
    });

    // Customer
    Route::group(['prefix' => 'customer', 'as' => 'customer.'], function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/get-data', [CustomerController::class, 'ajaxData'])->name('data');
        Route::get('/create', [CustomerController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [CustomerController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CustomerController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [CustomerController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [CustomerController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
        Route::put('/{id}/verify', [CustomerController::class, 'verify'])->middleware('can:master_edit')->name('verify');
        Route::post('/{id}/rental-now', [CustomerController::class, 'rentalNow'])->middleware('can:master_add')->name('rental-now');
    });

    // Employee
    Route::group(['prefix' => 'employee', 'as' => 'employee.'], function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/get-data', [EmployeeController::class, 'ajaxData'])->name('data');
        Route::get('/create', [EmployeeController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [EmployeeController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [EmployeeController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [EmployeeController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
        Route::put('/{id}/toggle-active', [EmployeeController::class, 'toggleActive'])->middleware('can:master_edit')->name('toggle-active');
    });

    // Driver
    Route::group(['prefix' => 'driver', 'as' => 'driver.'], function () {
        Route::get('/', [DriverController::class, 'index'])->name('index');
        Route::get('/get-data', [DriverController::class, 'ajaxData'])->name('data');
        Route::get('/create', [DriverController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [DriverController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [DriverController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [DriverController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [DriverController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
        Route::get('/{id}/history', [DriverController::class, 'history'])->name('history');
    });

    // Location
    Route::group(['prefix' => 'location', 'as' => 'location.'], function () {
        Route::get('/', [LocationController::class, 'index'])->name('index');
        Route::get('/get-data', [LocationController::class, 'ajaxData'])->name('data');
        Route::post('/store', [LocationController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [LocationController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [LocationController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [LocationController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
    });

    // Workshop
    Route::group(['prefix' => 'workshop', 'as' => 'workshop.'], function () {
        Route::get('/', [WorkshopController::class, 'index'])->name('index');
        Route::get('/get-data', [WorkshopController::class, 'ajaxData'])->name('data');
        Route::post('/store', [WorkshopController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [WorkshopController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [WorkshopController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [WorkshopController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
    });

    // Maintenance Type
    Route::group(['prefix' => 'maintenance-type', 'as' => 'maintenance-type.'], function () {
        Route::get('/', [MaintenanceTypeController::class, 'index'])->name('index');
        Route::get('/get-data', [MaintenanceTypeController::class, 'ajaxData'])->name('data');
        Route::post('/store', [MaintenanceTypeController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [MaintenanceTypeController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [MaintenanceTypeController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [MaintenanceTypeController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
    });

    // Promo
    Route::group(['prefix' => 'promo', 'as' => 'promo.'], function () {
        Route::get('/', [PromoController::class, 'index'])->name('index');
        Route::get('/get-data', [PromoController::class, 'ajaxData'])->name('data');
        Route::get('/create', [PromoController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [PromoController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [PromoController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [PromoController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [PromoController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
        Route::put('/{id}/toggle', [PromoController::class, 'toggle'])->middleware('can:master_edit')->name('toggle');
    });

    // COA (Chart of Account)
    Route::group(['prefix' => 'coa', 'as' => 'coa.'], function () {
        Route::get('/', [CoaController::class, 'index'])->name('index');
        Route::get('/get-tree', [CoaController::class, 'tree'])->name('tree');
        Route::get('/get-data', [CoaController::class, 'ajaxData'])->name('data');
        Route::get('/create', [CoaController::class, 'create'])->middleware('can:master_add')->name('create');
        Route::post('/store', [CoaController::class, 'store'])->middleware('can:master_add')->name('store');
        Route::get('/{id}/edit', [CoaController::class, 'edit'])->middleware('can:master_edit')->name('edit');
        Route::put('/{id}', [CoaController::class, 'update'])->middleware('can:master_edit')->name('update');
        Route::delete('/{id}', [CoaController::class, 'destroy'])->middleware('can:master_delete')->name('destroy');
    });
});

// ===========================================
// MODUL 3: OPERASIONAL SEWA (Core)
// ===========================================
Route::group(['prefix' => 'rental', 'as' => 'rental.', 'middleware' => ['can:rental_view']], function () {

    // Daftar Sewa (satu menu, filter via tab status)
    Route::get('/', [RentalController::class, 'index'])->name('index');
    Route::get('/get-data', [RentalController::class, 'data'])->name('data');
    Route::get('/get-button-option', [RentalController::class, 'getButtonOption'])->name('button-option');
    // Audit sewa §6: ekspor data sensitif butuh izin tersendiri
    Route::get('/export', [RentalController::class, 'export'])->middleware('can:rental_export')->name('export');

    // Buat Sewa Baru (Wizard) — tulis: rental_add
    Route::get('/create', [RentalController::class, 'create'])->middleware('can:rental_add')->name('create');
    Route::post('/create', [RentalController::class, 'saveStep'])->middleware('can:rental_add')->name('create.save');
    Route::get('/create/step/{step}', [RentalController::class, 'createStep'])->middleware('can:rental_add')->name('create.step');
    Route::post('/create/store', [RentalController::class, 'store'])->middleware('can:rental_add')->name('create.store');
    Route::get('/create/search-customer', [RentalController::class, 'searchCustomer'])->name('create.search-customer');
    Route::get('/create/search-location', [RentalController::class, 'searchLocation'])->name('create.search-location');
    Route::get('/create/search-driver', [RentalController::class, 'searchDriver'])->name('create.search-driver');
    Route::get('/create/search-promo', [RentalController::class, 'searchPromo'])->name('create.search-promo');
    Route::get('/create/available-vehicles', [RentalController::class, 'availableVehicles'])->name('create.available-vehicles');
    Route::post('/create/calculate-total', [RentalController::class, 'calculateTotal'])->name('create.calculate-total');

    // Aksi cepat
    Route::put('{id}/confirm', [RentalController::class, 'confirmPickup'])->middleware('can:rental_confirm')->name('confirm');
    Route::put('{id}/cancel', [RentalController::class, 'cancelReservation'])->middleware('can:rental_cancel')->name('cancel');

    // Detail Transaksi (Command Center per rental)
    Route::get('{rental}', [RentalController::class, 'show'])->name('show');
    Route::get('{rental}/edit', [RentalController::class, 'edit'])->middleware('can:rental_edit')->name('edit');
    Route::put('{rental}', [RentalController::class, 'update'])->middleware('can:rental_edit')->name('update');
    Route::get('{rental}/print', [RentalController::class, 'printContract'])->name('print');

    // Sub-aksi detail
    Route::group(['prefix' => '{rental}', 'as' => 'detail.'], function () {
        // Extension — mengubah periode & total: butuh rental_edit
        Route::post('/extension', [RentalController::class, 'extensionStore'])->middleware('can:rental_edit')->name('extension.store');
        Route::put('/extension/{extensionId}/approve', [RentalController::class, 'extensionApprove'])->middleware('can:rental_edit')->name('extension.approve');
        Route::put('/extension/{extensionId}/reject', [RentalController::class, 'extensionReject'])->middleware('can:rental_edit')->name('extension.reject');

        // Return — mutasi status + mileage + jurnal: rental_return
        Route::get('/return', [RentalController::class, 'returnForm'])->middleware('can:rental_return')->name('return.form');
        Route::post('/return', [RentalController::class, 'returnStore'])->middleware('can:rental_return')->name('return.store');

        // Fine
        Route::post('/fine', [RentalController::class, 'fineStore'])->middleware('can:fine_add')->name('fine.store');
        Route::put('/fine/{fineId}/pay', [RentalController::class, 'finePay'])->middleware('can:fine_pay')->name('fine.pay');
        Route::put('/fine/{fineId}/waive', [RentalController::class, 'fineWaive'])->middleware('can:fine_waive')->name('fine.waive');

        // Invoice
        Route::get('/invoice', [RentalController::class, 'invoiceGenerate'])->middleware('can:finance_invoice_add')->name('invoice.generate');
        Route::post('/invoice', [RentalController::class, 'invoiceStore'])->middleware('can:finance_invoice_add')->name('invoice.store');
        Route::get('/invoice/print', [RentalController::class, 'invoicePrint'])->name('invoice.print');

        // Payment
        Route::post('/payment', [RentalController::class, 'paymentStore'])->middleware('can:finance_payment_add')->name('payment.store');

        // Refund
        Route::post('/refund', [RentalController::class, 'refundStore'])->middleware('can:finance_refund_add')->name('refund.store');

        // Handover Inspection (checklist bodi + kelengkapan) — bagian alur serah-terima: rental_return
        Route::get('/handover/{type}', [RentalController::class, 'handoverForm'])->middleware('can:rental_return')->name('handover.form')->where('type', 'out|in');
        Route::post('/handover/{type}', [RentalController::class, 'handoverStore'])->middleware('can:rental_return')->name('handover.store')->where('type', 'out|in');
    });
});

// ===========================================
// MODUL 4: FLEET MAINTENANCE & KERUSAKAN
// ===========================================
Route::group(['prefix' => 'fleet', 'as' => 'fleet.', 'middleware' => ['can:fleet_view']], function () {

    // Maintenance — FLE-01: mutasi butuh izin fleet_maintain
    Route::group(['prefix' => 'maintenance', 'as' => 'maintenance.'], function () {
        Route::get('/', [FleetController::class, 'maintenanceIndex'])->name('index');
        Route::get('/get-data', [FleetController::class, 'maintenanceData'])->name('data');
        Route::get('/get-button-option', [FleetController::class, 'maintenanceButtonOption'])->name('button-option');
        Route::get('/create', [FleetController::class, 'maintenanceCreate'])->middleware('can:fleet_maintain')->name('create');
        Route::post('/store', [FleetController::class, 'maintenanceStore'])->middleware('can:fleet_maintain')->name('store');
        Route::get('/{id}/edit', [FleetController::class, 'maintenanceEdit'])->middleware('can:fleet_maintain')->name('edit');
        Route::put('/{id}', [FleetController::class, 'maintenanceUpdate'])->middleware('can:fleet_maintain')->name('update');
        Route::put('/{id}/complete', [FleetController::class, 'maintenanceComplete'])->middleware('can:fleet_maintain')->name('complete');
        Route::put('/{id}/reschedule', [FleetController::class, 'maintenanceReschedule'])->middleware('can:fleet_maintain')->name('reschedule');
    });

    // Damage Report (gabungan Kerusakan + Klaim Asuransi) — FLE-01: gate per aksi
    Route::group(['prefix' => 'damage', 'as' => 'damage.'], function () {
        Route::get('/', [FleetController::class, 'damageIndex'])->name('index');
        Route::get('/get-data', [FleetController::class, 'damageData'])->name('data');
        Route::get('/get-button-option', [FleetController::class, 'damageButtonOption'])->name('button-option');
        Route::get('/create', [FleetController::class, 'damageCreate'])->middleware('can:fleet_damage_add')->name('create');
        Route::post('/store', [FleetController::class, 'damageStore'])->middleware('can:fleet_damage_add')->name('store');
        Route::get('/{id}', [FleetController::class, 'damageShow'])->name('show');
        Route::put('/{id}/status', [FleetController::class, 'damageUpdateStatus'])->middleware('can:fleet_damage_manage')->name('update-status');
        // FLE-12: jalur koreksi biaya aktual (dasar penagihan penyewa)
        Route::put('/{id}/update-cost', [FleetController::class, 'damageUpdateCost'])->middleware('can:fleet_damage_manage')->name('update-cost');
        Route::put('/{id}/bill-renter', [FleetController::class, 'damageBillRenter'])->middleware('can:fleet_bill_renter')->name('bill');
        Route::post('/{id}/photo', [FleetController::class, 'damagePhotoUpload'])->middleware('can:fleet_damage_add')->name('photo.upload');
        Route::delete('/photo/{photoId}', [FleetController::class, 'damagePhotoDelete'])->middleware('can:fleet_damage_manage')->name('photo.delete');
    });

    // Insurance Claim (form buat & show diakses dari halaman Kerusakan) — FLE-01
    Route::group(['prefix' => 'insurance-claim', 'as' => 'insurance-claim.'], function () {
        Route::get('/create', [FleetController::class, 'claimCreate'])->middleware('can:fleet_claim_manage')->name('create');
        Route::post('/store', [FleetController::class, 'claimStore'])->middleware('can:fleet_claim_manage')->name('store');
        Route::get('/{id}', [FleetController::class, 'claimShow'])->name('show');
        Route::put('/{id}/status', [FleetController::class, 'claimUpdateStatus'])->middleware('can:fleet_claim_manage')->name('update-status');
    });
});

// ===========================================
// MODUL 5: KEUANGAN & PENAGIHAN
// ===========================================
Route::group(['prefix' => 'finance', 'as' => 'finance.', 'middleware' => ['can:finance_view']], function () {

    // Satu halaman dengan tab: Invoice / Denda / Pembayaran
    Route::get('/', [FinanceController::class, 'index'])->name('index');

    // Invoice
    Route::group(['prefix' => 'invoice', 'as' => 'invoice.'], function () {
        Route::get('/get-data', [FinanceController::class, 'invoiceData'])->name('data');
        Route::get('/get-button-option', [FinanceController::class, 'invoiceButtonOption'])->name('button-option');
        Route::get('/{id}', [FinanceController::class, 'invoiceShow'])->name('show');
        Route::get('/{id}/print', [FinanceController::class, 'invoicePrint'])->middleware('throttle:30,1')->name('print');
        // FIN-11: kirim email = aksi mutasi (draft → sent) + render PDF, bukan operasi baca.
        // Gate aksi finance_invoice_add + throttle mencegah penyalahgunaan/beban berulang.
        Route::post('/{id}/send-email', [FinanceController::class, 'invoiceSendEmail'])->middleware(['can:finance_invoice_add', 'throttle:6,1'])->name('send-email');
    });

    // Fine
    Route::group(['prefix' => 'fine', 'as' => 'fine.'], function () {
        Route::get('/get-data', [FinanceController::class, 'fineData'])->name('data');
        Route::get('/get-button-option', [FinanceController::class, 'fineButtonOption'])->name('button-option');
        Route::put('/{id}/pay', [FinanceController::class, 'finePay'])->middleware('can:fine_pay')->name('pay');
        Route::put('/{id}/waive', [FinanceController::class, 'fineWaive'])->middleware('can:fine_waive')->name('waive');
    });

    // Payment History
    Route::get('/payment/get-data', [FinanceController::class, 'paymentData'])->name('payment.data');
    Route::get('/payment/get-button-option', [FinanceController::class, 'paymentButtonOption'])->name('payment.button-option');
    // FIN-15: render PDF kwitansi dibatasi lajunya (render biner berat bila diulang).
    Route::get('/payment/{id}/receipt', [FinanceController::class, 'paymentReceipt'])->middleware('throttle:30,1')->name('payment.receipt');
});

// ===========================================
// MODUL 6: AKUNTANSI (Double-Entry)
// ===========================================
Route::group(['prefix' => 'accounting', 'as' => 'accounting.', 'middleware' => ['can:accounting_view']], function () {

    // Jurnal Umum — AKN-11: ekspor data sensitif butuh izin tersendiri + throttle;
    // AKN-01: rute memakai placeholder {id} (ditempatkan sebelum /{id} show).
    Route::group(['prefix' => 'journal', 'as' => 'journal.'], function () {
        Route::get('/', [AccountingController::class, 'journalIndex'])->name('index');
        Route::get('/get-data', [AccountingController::class, 'journalData'])->name('data');
        Route::get('/get-button-option', [AccountingController::class, 'journalButtonOption'])->name('button-option');
        Route::get('/{id}/export', [AccountingController::class, 'journalExport'])->middleware(['can:accounting_export', 'throttle:30,1'])->name('export');
        Route::get('/{id}', [AccountingController::class, 'journalShow'])->name('show');
    });

    // Posting Jurnal Manual — AKN-07: menulis jurnal butuh izin tulis terpisah.
    Route::group(['prefix' => 'manual-journal', 'as' => 'manual-journal.'], function () {
        Route::get('/', [AccountingController::class, 'manualJournalCreate'])->middleware('can:accounting_journal_add')->name('create');
        Route::post('/store', [AccountingController::class, 'manualJournalStore'])->middleware('can:accounting_journal_add')->name('store');
        Route::get('/validate', [AccountingController::class, 'manualJournalValidate'])->name('validate');
    });

    // Buku Besar
    Route::group(['prefix' => 'ledger', 'as' => 'ledger.'], function () {
        Route::get('/', [AccountingController::class, 'ledgerIndex'])->name('index');
        Route::get('/get-data', [AccountingController::class, 'ledgerData'])->name('data');
        Route::get('/get-button-option', [AccountingController::class, 'ledgerButtonOption'])->name('button-option');
        Route::get('/{accountId}', [AccountingController::class, 'ledgerDetail'])->name('detail');
    });

    // Laporan Keuangan Formal (audit 2.6)
    Route::get('/statement/income', [AccountingController::class, 'incomeStatement'])->name('statement.income');
    Route::get('/statement/balance-sheet', [AccountingController::class, 'balanceSheet'])->name('statement.balance');
    Route::get('/statement/cash-flow', [AccountingController::class, 'cashFlow'])->name('statement.cashflow');
});

// ===========================================
// MODUL 7: LAPORAN & ANALYTICS (satu halaman, tab)
// ===========================================
Route::group(['prefix' => 'report', 'as' => 'report.', 'middleware' => ['can:report_view']], function () {
    Route::get('/', [ReportController::class, 'index'])->name('index');
    Route::get('/get-data', [ReportController::class, 'data'])->name('data');
    Route::get('/export/{type}', [ReportController::class, 'export'])->middleware(['can:report_export', 'throttle:5,1'])->name('export');
    Route::get('/export-pdf/{type}', [ReportController::class, 'exportPdf'])->middleware(['can:report_export', 'throttle:5,1'])->name('export-pdf');
});

// ===========================================
// MODUL 8: SISTEM & ADMINISTRASI
// ===========================================
Route::group(['prefix' => 'system', 'as' => 'system.'], function () {
    Route::get('/settings', [SystemController::class, 'settings'])->middleware('can:settings_view')->name('settings');
    Route::put('/settings', [SystemController::class, 'settingsUpdate'])->middleware('can:settings_edit')->name('settings.update');
    Route::get('/activity-log', [SystemController::class, 'activityLog'])->middleware('can:logs_view')->name('activity-log');
    Route::get('/activity-log/get-data', [SystemController::class, 'activityLogData'])->middleware('can:logs_view')->name('activity-log.data');
    Route::post('/backup', [SystemController::class, 'backup'])->middleware(['can:settings_edit', 'throttle:3,1'])->name('backup');
});
