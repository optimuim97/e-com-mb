<?php

use Illuminate\Support\Facades\Route;
use Modules\Review\Http\Controllers\Shop\ReviewController;
use Modules\Review\Http\Controllers\Admin\ReviewController as AdminReviewController;

// Public: list approved reviews per product
Route::get('products/{slug}/reviews', [ReviewController::class, 'index'])->middleware('module:reviews');

// Authenticated shop routes
Route::middleware(['auth:sanctum', 'module:reviews'])->group(function () {
    Route::post('products/{slug}/reviews', [ReviewController::class, 'store']);
    Route::get('reviews/mine',             [ReviewController::class, 'mine']);
    Route::delete('reviews/{review}',      [ReviewController::class, 'destroy']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'role:admin|staff', 'module:reviews'])->prefix('admin')->group(function () {
    Route::get('reviews',           [AdminReviewController::class, 'index']);
    Route::patch('reviews/{review}',[AdminReviewController::class, 'update']);
    Route::delete('reviews/{review}',[AdminReviewController::class, 'destroy']);
});
