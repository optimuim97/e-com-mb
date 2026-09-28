<?php
use Modules\Catalog\Http\Controllers\Shop\ProductController;
use Modules\Catalog\Http\Controllers\Shop\CategoryController;
use Modules\Catalog\Http\Controllers\Shop\ProductLineController;
use Modules\Catalog\Http\Controllers\Shop\HomeController;
use Modules\Catalog\Http\Controllers\Admin\ProductController as AdminProductController;
use Modules\Catalog\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use Modules\Catalog\Http\Controllers\Admin\ProductLineController as AdminProductLineController;
use Modules\Catalog\Http\Controllers\Admin\StockMovementController;
use Modules\Catalog\Http\Controllers\Admin\WeeklyStockController;
use Illuminate\Support\Facades\Route;

Route::get('home/stats',        [HomeController::class, 'stats']);
Route::get('categories',        [CategoryController::class, 'index']);
Route::get('categories/{slug}', [CategoryController::class, 'show']);
Route::get('product-lines',          [ProductLineController::class, 'index']);
Route::get('product-lines/{slug}',   [ProductLineController::class, 'show']);
Route::get('products',                [ProductController::class, 'index']);
Route::get('products/{slug}',         [ProductController::class, 'show']);
Route::get('products/{slug}/related', [ProductController::class, 'related']);

Route::middleware(['auth:sanctum', 'role:admin|staff'])->prefix('admin')->group(function () {
    // Avant la ressource : sinon « reorder » serait capturé comme un
    // identifiant de produit par products/{product}.
    Route::put('products/reorder',      [AdminProductController::class, 'reorder']);
    Route::apiResource('products',      AdminProductController::class);
    Route::get('edit-products/{id}',    [AdminProductController::class, 'editShow']);
    Route::post('products/{product}/images',                [AdminProductController::class, 'uploadImage']);
    Route::patch('products/{product}/images/{image}/cover', [AdminProductController::class, 'setCover']);
    Route::delete('products/{product}/images/{image}',      [AdminProductController::class, 'deleteImage']);
    // ── Registre des mouvements de stock ────────────────────────────────
    // Statique avant paramétrée : « summary » serait sinon pris pour un id.
    Route::get ('stock-movements/summary', [StockMovementController::class, 'summary']);
    Route::get ('stock-movements',         [StockMovementController::class, 'index']);
    Route::post('stock-movements/adjust',  [StockMovementController::class, 'adjust']);

    // ── Stock mis en vente, semaine par semaine ─────────────────────────
    Route::get ('weekly-stock', [WeeklyStockController::class, 'index']);
    Route::post('weekly-stock', [WeeklyStockController::class, 'store']);

    Route::apiResource('categories',    AdminCategoryController::class);
    Route::apiResource('product-lines', AdminProductLineController::class);
    Route::post('product-lines/{productLine}/cover',    [AdminProductLineController::class, 'uploadCover']);
    Route::get ('product-lines/{productLine}/products', [AdminProductLineController::class, 'listProducts']);
    Route::put ('product-lines/{productLine}/products', [AdminProductLineController::class, 'syncProducts']);
});
