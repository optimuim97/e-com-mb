<?php

use Illuminate\Support\Facades\Route;
use Modules\Wishlist\Http\Controllers\WishlistController;

Route::middleware(['auth:sanctum', 'module:wishlist'])->prefix('wishlist')->group(function () {
    Route::get('/',                      [WishlistController::class, 'index']);
    Route::post('/toggle',               [WishlistController::class, 'toggle']);
    Route::delete('/{productId}',        [WishlistController::class, 'remove']);
});
