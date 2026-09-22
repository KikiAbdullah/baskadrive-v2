<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\V1\Field\DamageController;
use App\Http\Controllers\Api\V1\Field\DeviceController;
use App\Http\Controllers\Api\V1\Field\HandoverController;
use App\Http\Controllers\Api\V1\Field\MetaController;
use App\Http\Controllers\Api\V1\Field\PaymentController;
use App\Http\Controllers\Api\V1\Field\TaskController;
use App\Http\Controllers\Api\V1\Field\VehicleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Audit keamanan: endpoint auth API dibatasi lajunya (login tanpa throttle dulu
// bisa dipakai brute force token; refresh/me logout dibatasi anti spam).
Route::group([
    'prefix' => 'auth',
    'middleware' => ['throttle:10,1'],
], function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('me', [AuthController::class, 'me']);
});

/*
|--------------------------------------------------------------------------
| API Mobile — BaskaDrive Operator (konsep §9, prefix /api/v1/field)
|--------------------------------------------------------------------------
| Auth: JWT (guard api) · Envelope {status,msg,data} · Error kode B.7 via
| header X-Error-Code · Tulis idempotent via client_uuid · Throttle per-user
| (upload lebih longgar — konsep §9.2).
*/
Route::prefix('v1/field')->middleware(['auth:api', 'throttle:120,1'])->group(function () {

    // Referensi — meta boleh dipanggil sering (cache harian di mobile).
    Route::get('meta', [MetaController::class, 'index']);

    // Tugas (F-02)
    Route::get('tasks', [TaskController::class, 'index']);
    Route::get('tasks/{rental}', [TaskController::class, 'show']);
    Route::post('tasks/{rental}/enroute', [TaskController::class, 'enroute']);

    // Serah terima keluar / masuk (F-03/F-04)
    Route::post('rentals/{rental}/handover-out', [HandoverController::class, 'handoverOut']);
    Route::post('rentals/{rental}/return/calculate', [HandoverController::class, 'returnCalculate']);
    Route::post('rentals/{rental}/return', [HandoverController::class, 'returnRental']);

    // Kerusakan (F-05) — upload foto: throttle lebih longgar per-user (§9.2)
    Route::get('damages', [DamageController::class, 'index']);
    Route::post('damages', [DamageController::class, 'store'])->middleware('throttle:30,1');

    // Unit (F-06)
    Route::get('vehicles/search', [VehicleController::class, 'search']);
    Route::get('vehicles/{vehicle}', [VehicleController::class, 'show']);

    // Pembayaran (F-07)
    Route::post('payments', [PaymentController::class, 'store']);
    Route::get('invoices/{invoice}/receipt-url', [PaymentController::class, 'receiptUrl']);

    // Perangkat (F-09)
    Route::post('devices', [DeviceController::class, 'store']);
});

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
