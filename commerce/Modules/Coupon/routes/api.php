<?php
use Modules\Coupon\Http\Controllers\CouponController;
use Illuminate\Support\Facades\Route;

Route::post('coupons/validate', [CouponController::class, 'checkCode'])->middleware('module:coupons');

Route::middleware(['auth:sanctum', 'role:admin|staff', 'module:coupons'])->prefix('admin')->group(function () {
    Route::apiResource('coupons', CouponController::class);
});
