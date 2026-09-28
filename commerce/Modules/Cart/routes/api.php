<?php
use Modules\Cart\Http\Controllers\CartController;
use Modules\Cart\Http\Controllers\Admin\AbandonedCartController;
use Illuminate\Support\Facades\Route;

Route::prefix('cart')->group(function () {
    Route::get('/',              [CartController::class, 'index']);
    Route::post('/items',        [CartController::class, 'add']);
    Route::put('/items/{id}',    [CartController::class, 'update']);
    Route::delete('/items/{id}', [CartController::class, 'remove']);
    Route::post('/coupon',       [CartController::class, 'applyCoupon']);
    Route::delete('/coupon',     [CartController::class, 'removeCoupon']);
    // Fusion panier invité → panier utilisateur (auth requise)
    Route::middleware('auth:sanctum')->post('/merge', [CartController::class, 'merge']);
});

// Admin — abandoned carts
Route::middleware(['auth:sanctum', 'role:admin|staff'])->prefix('admin')->group(function () {
    Route::get('abandoned-carts',        [AbandonedCartController::class, 'index']);
    Route::get('abandoned-carts/stats',  [AbandonedCartController::class, 'stats']);
});
