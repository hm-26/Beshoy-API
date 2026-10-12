<?php

use App\Http\Controllers\Api\AdminAttachmentController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AttachmentController;
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

// Attachment bytes (images / videos / documents). The URL is a short-lived *signed* link that the report JSON
// hands out, so files of draft reports stay private and <img>/<video> tags work without an Authorization header.
Route::get('/attachments/{attachment}/file', [AttachmentController::class, 'file'])
    ->whereNumber('attachment')
    ->middleware(['signed:relative', 'throttle:600,1'])
    ->name('attachments.file');

/*
|--------------------------------------------------------------------------
| Auth (token based, Laravel Sanctum)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    // rate limit defined in AppServiceProvider (per IP and per e-mail) — slows down password guessing
    // secret login link: the secret is checked here, the answer is a short-lived HttpOnly cookie that /login requires
    Route::post('/gate', [AuthController::class, 'openGate'])->middleware('throttle:gate');
    Route::get('/gate', [AuthController::class, 'gateStatus'])->middleware('throttle:60,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware(['login.gate', 'throttle:login']);

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

    Route::post('/reports/{report}/attachments', [AdminAttachmentController::class, 'store'])->whereNumber('report');
    Route::patch('/attachments/{attachment}', [AdminAttachmentController::class, 'update'])->whereNumber('attachment');
    Route::delete('/attachments/{attachment}', [AdminAttachmentController::class, 'destroy'])->whereNumber('attachment');
});
