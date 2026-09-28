<?php
use Modules\Customer\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:admin|staff'])->prefix('admin')->group(function () {
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::get('users/export', [UserController::class, 'export']);
});
