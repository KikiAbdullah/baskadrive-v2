<?php

use App\Http\Controllers\Rental\{
    AccountingController,
    DashboardController,
    FinanceController,
    FleetController,
    ReportController,
    RentalController,
    SystemController,
};
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
use Illuminate\Support\Facades\Route;

// ===========================================
// MODUL 1: DASHBOARD & MONITORING
// ===========================================
Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.'], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');
    Route::get('/fleet-map', [DashboardController::class, 'fleetMap'])->name('fleet-map');
});

// ===========================================
// MODUL 2: MASTER DATA
// ===========================================
Route::group(['prefix' => 'master', 'as' => 'master.'], function () {

    // Brand
    Route::group(['prefix' => 'brand', 'as' => 'brand.'], function () {
        Route::get('/', [BrandController::class, 'index'])->name('index');
        Route::get('/get-data', [BrandController::class, 'ajaxData'])->name('data');
        Route::get('/create', [BrandController::class, 'create'])->name('create');
        Route::post('/store', [BrandController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [BrandController::class, 'edit'])->name('edit');
        Route::put('/{id}', [BrandController::class, 'update'])->name('update');
        Route::delete('/{id}', [BrandController::class, 'destroy'])->name('destroy');
    });

    // Vehicle Model
    Route::group(['prefix' => 'vehicle-model', 'as' => 'vehicle-model.'], function () {
        Route::get('/', [VehicleModelController::class, 'index'])->name('index');
        Route::get('/get-data', [VehicleModelController::class, 'ajaxData'])->name('data');
        Route::get('/create', [VehicleModelController::class, 'create'])->name('create');
        Route::post('/store', [VehicleModelController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [VehicleModelController::class, 'edit'])->name('edit');
        Route::put('/{id}', [VehicleModelController::class, 'update'])->name('update');
        Route::delete('/{id}', [VehicleModelController::class, 'destroy'])->name('destroy');
    });

    // Vehicle (Unit Mobil)
    Route::group(['prefix' => 'vehicle', 'as' => 'vehicle.'], function () {
        Route::get('/', [VehicleController::class, 'index'])->name('index');
        Route::get('/get-data', [VehicleController::class, 'ajaxData'])->name('data');
        Route::get('/create', [VehicleController::class, 'create'])->name('create');
        Route::post('/store', [VehicleController::class, 'store'])->name('store');
        Route::get('/{id}', [VehicleController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [VehicleController::class, 'edit'])->name('edit');
        Route::put('/{id}', [VehicleController::class, 'update'])->name('update');
        Route::delete('/{id}', [VehicleController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/barcode', [VehicleController::class, 'barcode'])->name('barcode');
    });

    // Customer
    Route::group(['prefix' => 'customer', 'as' => 'customer.'], function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::get('/get-data', [CustomerController::class, 'ajaxData'])->name('data');
        Route::get('/create', [CustomerController::class, 'create'])->name('create');
        Route::post('/store', [CustomerController::class, 'store'])->name('store');
        Route::get('/{id}', [CustomerController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [CustomerController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CustomerController::class, 'update'])->name('update');
        Route::delete('/{id}', [CustomerController::class, 'destroy'])->name('destroy');
        Route::put('/{id}/verify', [CustomerController::class, 'verify'])->name('verify');
        Route::post('/{id}/rental-now', [CustomerController::class, 'rentalNow'])->name('rental-now');
    });

    // Employee
    Route::group(['prefix' => 'employee', 'as' => 'employee.'], function () {
        Route::get('/', [EmployeeController::class, 'index'])->name('index');
        Route::get('/get-data', [EmployeeController::class, 'ajaxData'])->name('data');
        Route::get('/create', [EmployeeController::class, 'create'])->name('create');
        Route::post('/store', [EmployeeController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [EmployeeController::class, 'edit'])->name('edit');
        Route::put('/{id}', [EmployeeController::class, 'update'])->name('update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->name('destroy');
        Route::put('/{id}/toggle-active', [EmployeeController::class, 'toggleActive'])->name('toggle-active');
    });

    // Driver
    Route::group(['prefix' => 'driver', 'as' => 'driver.'], function () {
        Route::get('/', [DriverController::class, 'index'])->name('index');
        Route::get('/get-data', [DriverController::class, 'ajaxData'])->name('data');
        Route::get('/create', [DriverController::class, 'create'])->name('create');
        Route::post('/store', [DriverController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [DriverController::class, 'edit'])->name('edit');
        Route::put('/{id}', [DriverController::class, 'update'])->name('update');
        Route::delete('/{id}', [DriverController::class, 'destroy'])->name('destroy');
        Route::get('/{id}/history', [DriverController::class, 'history'])->name('history');
    });

    // Location
    Route::group(['prefix' => 'location', 'as' => 'location.'], function () {
        Route::get('/', [LocationController::class, 'index'])->name('index');
        Route::get('/get-data', [LocationController::class, 'ajaxData'])->name('data');
        Route::post('/store', [LocationController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [LocationController::class, 'edit'])->name('edit');
        Route::put('/{id}', [LocationController::class, 'update'])->name('update');
        Route::delete('/{id}', [LocationController::class, 'destroy'])->name('destroy');
    });

    // Workshop
    Route::group(['prefix' => 'workshop', 'as' => 'workshop.'], function () {
        Route::get('/', [WorkshopController::class, 'index'])->name('index');
        Route::get('/get-data', [WorkshopController::class, 'ajaxData'])->name('data');
        Route::post('/store', [WorkshopController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [WorkshopController::class, 'edit'])->name('edit');
        Route::put('/{id}', [WorkshopController::class, 'update'])->name('update');
        Route::delete('/{id}', [WorkshopController::class, 'destroy'])->name('destroy');
    });

    // Maintenance Type
    Route::group(['prefix' => 'maintenance-type', 'as' => 'maintenance-type.'], function () {
        Route::get('/', [MaintenanceTypeController::class, 'index'])->name('index');
        Route::get('/get-data', [MaintenanceTypeController::class, 'ajaxData'])->name('data');
        Route::post('/store', [MaintenanceTypeController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [MaintenanceTypeController::class, 'edit'])->name('edit');
        Route::put('/{id}', [MaintenanceTypeController::class, 'update'])->name('update');
        Route::delete('/{id}', [MaintenanceTypeController::class, 'destroy'])->name('destroy');
    });

    // Promo
    Route::group(['prefix' => 'promo', 'as' => 'promo.'], function () {
        Route::get('/', [PromoController::class, 'index'])->name('index');
        Route::get('/get-data', [PromoController::class, 'ajaxData'])->name('data');
        Route::get('/create', [PromoController::class, 'create'])->name('create');
        Route::post('/store', [PromoController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [PromoController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PromoController::class, 'update'])->name('update');
        Route::delete('/{id}', [PromoController::class, 'destroy'])->name('destroy');
        Route::put('/{id}/toggle', [PromoController::class, 'toggle'])->name('toggle');
    });

    // COA (Chart of Account)
    Route::group(['prefix' => 'coa', 'as' => 'coa.'], function () {
        Route::get('/', [CoaController::class, 'index'])->name('index');
        Route::get('/get-tree', [CoaController::class, 'tree'])->name('tree');
        Route::get('/get-data', [CoaController::class, 'ajaxData'])->name('data');
        Route::get('/create', [CoaController::class, 'create'])->name('create');
        Route::post('/store', [CoaController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [CoaController::class, 'edit'])->name('edit');
        Route::put('/{id}', [CoaController::class, 'update'])->name('update');
        Route::delete('/{id}', [CoaController::class, 'destroy'])->name('destroy');
    });
});

// ===========================================
// MODUL 3: OPERASIONAL SEWA (Core)
// ===========================================
Route::group(['prefix' => 'rental', 'as' => 'rental.'], function () {

    // Daftar Sewa (satu menu, filter via tab status)
    Route::get('/', [RentalController::class, 'index'])->name('index');
    Route::get('/get-data', [RentalController::class, 'data'])->name('data');
    Route::get('/export', [RentalController::class, 'export'])->name('export');

    // Buat Sewa Baru (Wizard)
    Route::get('/create', [RentalController::class, 'create'])->name('create');
    Route::post('/create', [RentalController::class, 'saveStep'])->name('create.save');
    Route::get('/create/step/{step}', [RentalController::class, 'createStep'])->name('create.step');
    Route::post('/create/store', [RentalController::class, 'store'])->name('create.store');
    Route::get('/create/search-customer', [RentalController::class, 'searchCustomer'])->name('create.search-customer');
    Route::get('/create/available-vehicles', [RentalController::class, 'availableVehicles'])->name('create.available-vehicles');
    Route::post('/create/calculate-total', [RentalController::class, 'calculateTotal'])->name('create.calculate-total');

    // Aksi cepat
    Route::put('{id}/confirm', [RentalController::class, 'confirmPickup'])->name('confirm');
    Route::put('{id}/cancel', [RentalController::class, 'cancelReservation'])->name('cancel');

    // Detail Transaksi (Command Center per rental)
    Route::get('{rental}', [RentalController::class, 'show'])->name('show');
    Route::get('{rental}/edit', [RentalController::class, 'edit'])->name('edit');
    Route::put('{rental}', [RentalController::class, 'update'])->name('update');
    Route::get('{rental}/print', [RentalController::class, 'printContract'])->name('print');

    // Sub-aksi detail
    Route::group(['prefix' => '{rental}', 'as' => 'detail.'], function () {
        // Extension
        Route::post('/extension', [RentalController::class, 'extensionStore'])->name('extension.store');
        Route::put('/extension/{extensionId}/approve', [RentalController::class, 'extensionApprove'])->name('extension.approve');
        Route::put('/extension/{extensionId}/reject', [RentalController::class, 'extensionReject'])->name('extension.reject');

        // Return
        Route::get('/return', [RentalController::class, 'returnForm'])->name('return.form');
        Route::post('/return', [RentalController::class, 'returnStore'])->name('return.store');

        // Fine
        Route::post('/fine', [RentalController::class, 'fineStore'])->name('fine.store');
        Route::put('/fine/{fineId}/pay', [RentalController::class, 'finePay'])->name('fine.pay');
        Route::put('/fine/{fineId}/waive', [RentalController::class, 'fineWaive'])->name('fine.waive');

        // Invoice
        Route::get('/invoice', [RentalController::class, 'invoiceGenerate'])->name('invoice.generate');
        Route::post('/invoice', [RentalController::class, 'invoiceStore'])->name('invoice.store');
        Route::get('/invoice/print', [RentalController::class, 'invoicePrint'])->name('invoice.print');

        // Payment
        Route::post('/payment', [RentalController::class, 'paymentStore'])->name('payment.store');

        // Refund
        Route::post('/refund', [RentalController::class, 'refundStore'])->name('refund.store');
    });
});

// ===========================================
// MODUL 4: FLEET MAINTENANCE & KERUSAKAN
// ===========================================
Route::group(['prefix' => 'fleet', 'as' => 'fleet.'], function () {

    // Maintenance
    Route::group(['prefix' => 'maintenance', 'as' => 'maintenance.'], function () {
        Route::get('/', [FleetController::class, 'maintenanceIndex'])->name('index');
        Route::get('/get-data', [FleetController::class, 'maintenanceData'])->name('data');
        Route::get('/create', [FleetController::class, 'maintenanceCreate'])->name('create');
        Route::post('/store', [FleetController::class, 'maintenanceStore'])->name('store');
        Route::get('/{id}/edit', [FleetController::class, 'maintenanceEdit'])->name('edit');
        Route::put('/{id}', [FleetController::class, 'maintenanceUpdate'])->name('update');
        Route::put('/{id}/complete', [FleetController::class, 'maintenanceComplete'])->name('complete');
        Route::put('/{id}/reschedule', [FleetController::class, 'maintenanceReschedule'])->name('reschedule');
    });

    // Damage Report
    Route::group(['prefix' => 'damage', 'as' => 'damage.'], function () {
        Route::get('/', [FleetController::class, 'damageIndex'])->name('index');
        Route::get('/get-data', [FleetController::class, 'damageData'])->name('data');
        Route::get('/create', [FleetController::class, 'damageCreate'])->name('create');
        Route::post('/store', [FleetController::class, 'damageStore'])->name('store');
        Route::get('/{id}', [FleetController::class, 'damageShow'])->name('show');
        Route::put('/{id}/status', [FleetController::class, 'damageUpdateStatus'])->name('update-status');
        Route::post('/{id}/photo', [FleetController::class, 'damagePhotoUpload'])->name('photo.upload');
        Route::delete('/photo/{photoId}', [FleetController::class, 'damagePhotoDelete'])->name('photo.delete');
    });

    // Insurance Claim
    Route::group(['prefix' => 'insurance-claim', 'as' => 'insurance-claim.'], function () {
        Route::get('/', [FleetController::class, 'claimIndex'])->name('index');
        Route::get('/get-data', [FleetController::class, 'claimData'])->name('data');
        Route::get('/create', [FleetController::class, 'claimCreate'])->name('create');
        Route::post('/store', [FleetController::class, 'claimStore'])->name('store');
        Route::get('/{id}', [FleetController::class, 'claimShow'])->name('show');
        Route::put('/{id}/status', [FleetController::class, 'claimUpdateStatus'])->name('update-status');
    });
});

// ===========================================
// MODUL 5: KEUANGAN & PENAGIHAN
// ===========================================
Route::group(['prefix' => 'finance', 'as' => 'finance.'], function () {

    // Invoice
    Route::group(['prefix' => 'invoice', 'as' => 'invoice.'], function () {
        Route::get('/', [FinanceController::class, 'invoiceIndex'])->name('index');
        Route::get('/get-data', [FinanceController::class, 'invoiceData'])->name('data');
        Route::get('/{id}', [FinanceController::class, 'invoiceShow'])->name('show');
        Route::get('/{id}/print', [FinanceController::class, 'invoicePrint'])->name('print');
        Route::post('/{id}/send-email', [FinanceController::class, 'invoiceSendEmail'])->name('send-email');
    });

    // Fine
    Route::group(['prefix' => 'fine', 'as' => 'fine.'], function () {
        Route::get('/', [FinanceController::class, 'fineIndex'])->name('index');
        Route::get('/get-data', [FinanceController::class, 'fineData'])->name('data');
        Route::put('/{id}/pay', [FinanceController::class, 'finePay'])->name('pay');
        Route::put('/{id}/waive', [FinanceController::class, 'fineWaive'])->name('waive');
    });

    // Payment History
    Route::get('/payment', [FinanceController::class, 'paymentIndex'])->name('payment');
    Route::get('/payment/get-data', [FinanceController::class, 'paymentData'])->name('payment.data');
    Route::get('/payment/{id}/receipt', [FinanceController::class, 'paymentReceipt'])->name('payment.receipt');
});

// ===========================================
// MODUL 6: AKUNTANSI (Double-Entry)
// ===========================================
Route::group(['prefix' => 'accounting', 'as' => 'accounting.'], function () {

    // Jurnal Umum
    Route::group(['prefix' => 'journal', 'as' => 'journal.'], function () {
        Route::get('/', [AccountingController::class, 'journalIndex'])->name('index');
        Route::get('/get-data', [AccountingController::class, 'journalData'])->name('data');
        Route::get('/{id}', [AccountingController::class, 'journalShow'])->name('show');
        Route::get('/export', [AccountingController::class, 'journalExport'])->name('export');
    });

    // Posting Jurnal Manual
    Route::group(['prefix' => 'manual-journal', 'as' => 'manual-journal.'], function () {
        Route::get('/', [AccountingController::class, 'manualJournalCreate'])->name('create');
        Route::post('/store', [AccountingController::class, 'manualJournalStore'])->name('store');
        Route::get('/validate', [AccountingController::class, 'manualJournalValidate'])->name('validate');
    });

    // Buku Besar
    Route::group(['prefix' => 'ledger', 'as' => 'ledger.'], function () {
        Route::get('/', [AccountingController::class, 'ledgerIndex'])->name('index');
        Route::get('/get-data', [AccountingController::class, 'ledgerData'])->name('data');
        Route::get('/{accountId}', [AccountingController::class, 'ledgerDetail'])->name('detail');
    });
});

// ===========================================
// MODUL 7: LAPORAN & ANALYTICS
// ===========================================
Route::group(['prefix' => 'report', 'as' => 'report.'], function () {
    Route::get('/revenue', [ReportController::class, 'revenue'])->name('revenue');
    Route::get('/revenue/get-data', [ReportController::class, 'revenueData'])->name('revenue.data');
    Route::get('/fleet-utilization', [ReportController::class, 'fleetUtilization'])->name('fleet-utilization');
    Route::get('/fleet-utilization/get-data', [ReportController::class, 'fleetUtilizationData'])->name('fleet-utilization.data');
    Route::get('/top-customers', [ReportController::class, 'topCustomers'])->name('top-customers');
    Route::get('/top-customers/get-data', [ReportController::class, 'topCustomersData'])->name('top-customers.data');
    Route::get('/claims', [ReportController::class, 'claims'])->name('claims');
    Route::get('/claims/get-data', [ReportController::class, 'claimsData'])->name('claims.data');
    Route::get('/financial', [ReportController::class, 'financial'])->name('financial');
    Route::get('/financial/get-data', [ReportController::class, 'financialData'])->name('financial.data');
    Route::get('/export/{type}', [ReportController::class, 'export'])->name('export');
});

// ===========================================
// MODUL 8: SISTEM & ADMINISTRASI
// ===========================================
Route::group(['prefix' => 'system', 'as' => 'system.'], function () {
    Route::get('/settings', [SystemController::class, 'settings'])->name('settings');
    Route::put('/settings', [SystemController::class, 'settingsUpdate'])->name('settings.update');
    Route::get('/activity-log', [SystemController::class, 'activityLog'])->name('activity-log');
    Route::get('/activity-log/get-data', [SystemController::class, 'activityLogData'])->name('activity-log.data');
});