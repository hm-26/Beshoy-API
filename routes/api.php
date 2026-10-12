<?php

use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API — exactly the contract the React site already consumes
|--------------------------------------------------------------------------
*/
Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::get('/profile', [ProfileController::class, 'show']);
Route::get('/reports', [ReportController::class, 'index']);
Route::get('/reports/{id}', [ReportController::class, 'show'])->whereNumber('id');

/*
|--------------------------------------------------------------------------
| Auth (token based, Laravel Sanctum)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // 6 attempts per minute per IP — slows down password guessing
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin API — requires a valid token that belongs to an admin user
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin', 'throttle:120,1'])->group(function () {
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/reports', [AdminReportController::class, 'index']);
    Route::post('/reports', [AdminReportController::class, 'store']);
    Route::get('/reports/{report}', [AdminReportController::class, 'show'])->whereNumber('report');
    Route::put('/reports/{report}', [AdminReportController::class, 'update'])->whereNumber('report');
    Route::delete('/reports/{report}', [AdminReportController::class, 'destroy'])->whereNumber('report');
});
