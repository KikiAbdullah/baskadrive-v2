<?php

use App\Http\Controllers\AjaxController;
use App\Http\Controllers\AppController;
use App\Http\Controllers\Auth\ConfirmPasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReceiptVerificationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
// S-04: batasi brute force — 5 percobaan login / menit (per IP)
Route::post('login', [LoginController::class, 'login'])->middleware('throttle:5,1');
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Pendaftaran publik dimatikan secara default (audit Setup S-11; pintu S-01 tertutup
// kecuali memang dibutuhkan — aktifkan lewat APP_REGISTRATION_ENABLED=true).
if (config('app.registration_enabled')) {
    Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [RegisterController::class, 'register']);
}

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::get('password/confirm', [ConfirmPasswordController::class, 'showConfirmForm'])->name('password.confirm');
Route::post('password/confirm', [ConfirmPasswordController::class, 'confirm']);

Route::get('email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::post('email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

// Verifikasi publik keaslian kwitansi (scan QR di dokumen)
Route::get('verify/receipt/{payment}', [ReceiptVerificationController::class, 'verify'])->name('verify.receipt');

// Two-Factor Routes
Route::group(['middleware' => ['auth']], function () {
    Route::get('2fa', [TwoFactorController::class, 'showTwoFactorForm'])->name('2fa.show');
    Route::post('2fa', [TwoFactorController::class, 'verifyTwoFactor'])->name('verifyTwoFactor');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['auth']], function () {
    Route::group(['middleware' => ['two_factor']], function () {
        Route::get('/', [AppController::class, 'index'])->name('siteurl');

        // ===========================================
        // USER SETUP (existing)
        // ===========================================
        Route::group(['prefix' => 'user-setup', 'as' => 'user-setup.'], function () {
            // PERMISSIONS — S-06: gate tulis terpisah, view tetap
            Route::group(['prefix' => 'permission', 'as' => 'permission.', 'middleware' => 'can:permissions_view'], function () {
                Route::get('get-data', [PermissionController::class, 'ajaxData'])->name('get-data');
            });
            Route::get('permission', [PermissionController::class, 'index'])->name('permission.index')->middleware('can:permissions_view');
            Route::get('permission/create', [PermissionController::class, 'create'])->name('permission.create')->middleware('can:permissions_add');
            Route::post('permission', [PermissionController::class, 'store'])->name('permission.store')->middleware('can:permissions_add');
            Route::get('permission/{permission}', [PermissionController::class, 'show'])->name('permission.show')->middleware('can:permissions_view');
            Route::get('permission/{permission}/edit', [PermissionController::class, 'edit'])->name('permission.edit')->middleware('can:permissions_edit');
            Route::put('permission/{permission}', [PermissionController::class, 'update'])->name('permission.update')->middleware('can:permissions_edit');
            Route::patch('permission/{permission}', [PermissionController::class, 'update'])->middleware('can:permissions_edit');
            Route::delete('permission/{permission}', [PermissionController::class, 'destroy'])->name('permission.destroy')->middleware('can:permissions_delete');

            // ROLES — S-06: gate per aksi + lindungi role sistem via controller
            Route::get('role/get-data', [RoleController::class, 'ajaxData'])->name('role.get-data')->middleware('can:roles_view');
            Route::get('role', [RoleController::class, 'index'])->name('role.index')->middleware('can:roles_view');
            Route::get('role/create', [RoleController::class, 'create'])->name('role.create')->middleware('can:roles_add');
            Route::post('role', [RoleController::class, 'store'])->name('role.store')->middleware('can:roles_add');
            Route::get('role/{role}', [RoleController::class, 'show'])->name('role.show')->middleware('can:roles_view');
            Route::get('role/{role}/edit', [RoleController::class, 'edit'])->name('role.edit')->middleware('can:roles_edit');
            Route::put('role/{role}', [RoleController::class, 'update'])->name('role.update')->middleware('can:roles_edit');
            Route::patch('role/{role}', [RoleController::class, 'update'])->middleware('can:roles_edit');
            Route::delete('role/{role}', [RoleController::class, 'destroy'])->name('role.destroy')->middleware('can:roles_delete');

            // USERS
            Route::group(['prefix' => 'user', 'as' => 'user.', 'middleware' => 'can:users_view'], function () {
                Route::get('get-data', [UserController::class, 'ajaxData'])->name('get-data');
            });
            Route::resource('user', UserController::class)->middleware('can:users_view');
        });

        // ===========================================
        // DEBUG / UTILITIES — S-05: hanya pemilik debug_view
        // ===========================================
        Route::group(['prefix' => 'debug', 'as' => 'debug.', 'middleware' => ['can:debug_view']], function () {
            Route::get('log-viewer', [LogViewerController::class, 'index'])->name('log-viewer.index');
        });
        Route::get('get-button-option', [AjaxController::class, 'getButtonOption'])->name('get.button-option');

        require __DIR__.'/rental.php';
    });

});
