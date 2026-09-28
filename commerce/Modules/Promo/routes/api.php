<?php

use Illuminate\Support\Facades\Route;
use Modules\Promo\Http\Controllers\Admin\PromotionController;

// ── Administration des promotions ───────────────────────────────────────────
// Réservé aux comptes disposant de la permission « promotions.manage ».
Route::middleware(['auth:sanctum', 'module:promotions'])->prefix('admin')->group(function () {
    Route::get   ('promotions',            [PromotionController::class, 'index']);
    Route::post  ('promotions',            [PromotionController::class, 'store']);
    Route::get   ('promotions/{promotion}',[PromotionController::class, 'show']);
    Route::put   ('promotions/{promotion}',[PromotionController::class, 'update']);
    Route::delete('promotions/{promotion}',[PromotionController::class, 'destroy']);
});
