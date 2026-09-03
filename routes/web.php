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
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

Route::get('register', [RegisterController::class, 'showRegistrationForm'])->name('register');
Route::post('register', [RegisterController::class, 'register']);

Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

Route::get('password/confirm', [ConfirmPasswordController::class, 'showConfirmForm'])->name('password.confirm');
Route::post('password/confirm', [ConfirmPasswordController::class, 'confirm']);

Route::get('email/verify', [VerificationController::class, 'notice'])->name('verification.notice');
Route::get('email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->name('verification.verify');
Route::post('email/resend', [VerificationController::class, 'resend'])->name('verification.resend');

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
            // PERMISSIONS
            Route::group(['prefix' => 'permission', 'as' => 'permission.', 'middleware' => 'can:permissions_view'], function () {
                Route::get('get-data', [PermissionController::class, 'ajaxData'])->name('get-data');
            });
            Route::resource('permission', PermissionController::class)->middleware('can:permissions_view');

            // ROLES
            Route::resource('role', RoleController::class)->middleware('can:roles_view');

            // USERS
            Route::group(['prefix' => 'user', 'as' => 'user.', 'middleware' => 'can:users_view'], function () {
                Route::get('get-data', [UserController::class, 'ajaxData'])->name('get-data');
            });
            Route::resource('user', UserController::class)->middleware('can:users_view');
        });

        // ===========================================
        // DEBUG / UTILITIES
        // ===========================================
        Route::group(['prefix' => 'debug', 'as' => 'debug.'], function () {
            Route::get('log-viewer', [LogViewerController::class, 'index'])->name('log-viewer.index');
        });
        Route::get('get-button-option', [AjaxController::class, 'getButtonOption'])->name('get.button-option');
    });
});
