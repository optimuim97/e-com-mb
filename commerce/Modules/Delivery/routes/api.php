<?php

use Illuminate\Support\Facades\Route;
use Modules\Delivery\Http\Controllers\AuthController;
use Modules\Delivery\Http\Controllers\DeliveryController;
use Modules\Delivery\Http\Controllers\PaymentController;
use Modules\Delivery\Http\Controllers\Admin\DeliveryAdminController;
use Modules\Delivery\Http\Controllers\Admin\DeliveryZoneController;
use Modules\Delivery\Http\Controllers\Admin\LocalityController;
use Modules\Delivery\Http\Controllers\Shop\LocalityController as ShopLocalityController;

/*
|--------------------------------------------------------------------------
| Delivery Module API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ==================== COURIER AUTH ====================
    Route::prefix('auth/courier')->group(function () {
        // Public
        Route::post('/login', [AuthController::class, 'login']);

        // Protected (Sanctum)
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/fcm-token', [AuthController::class, 'registerFcmToken']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // ==================== DELIVERIES ====================
    Route::middleware(['auth:sanctum', 'courier'])->prefix('deliveries')->group(function () {
        // Livraisons du jour
        Route::get('/today', [DeliveryController::class, 'today']);

        // Détail livraison
        Route::get('/{id}', [DeliveryController::class, 'show']);

        // Mise à jour statut
        Route::patch('/{id}/status', [DeliveryController::class, 'updateStatus']);

        // Confirmation (QR + signature)
        Route::post('/{id}/confirm', [DeliveryController::class, 'confirm']);

        // Paiements
        Route::post('/{id}/payment', [PaymentController::class, 'store']);
        Route::get('/{id}/payments', [PaymentController::class, 'show']);
    });
});

// ==================== ADMIN (hors préfixe v1) ====================
Route::middleware(['auth:sanctum', 'role:admin|staff'])->prefix('admin')->group(function () {
    // Stats rapides
    Route::get('deliveries/stats',           [DeliveryAdminController::class, 'stats']);
    // Liste des livreurs (pour selects)
    Route::get('couriers',                   [DeliveryAdminController::class, 'couriers']);
    // CRUD livraisons
    Route::get('deliveries',                 [DeliveryAdminController::class, 'index']);
    Route::post('deliveries',                [DeliveryAdminController::class, 'store']);
    Route::get('deliveries/{id}',            [DeliveryAdminController::class, 'show']);
    Route::patch('deliveries/{id}/assign',   [DeliveryAdminController::class, 'assign']);
    Route::patch('deliveries/{id}/status',   [DeliveryAdminController::class, 'updateStatus']);

    // CRUD zones de livraison
    Route::get   ('delivery-zones',          [DeliveryZoneController::class, 'index']);
    Route::post  ('delivery-zones',          [DeliveryZoneController::class, 'store']);
    Route::patch ('delivery-zones/{id}',     [DeliveryZoneController::class, 'update']);
    Route::delete('delivery-zones/{id}',     [DeliveryZoneController::class, 'destroy']);

    // Villes et communes desservies
    Route::get   ('localities',              [LocalityController::class, 'index']);
    Route::post  ('localities',              [LocalityController::class, 'store']);
    Route::patch ('localities/{locality}',   [LocalityController::class, 'update']);
    Route::delete('localities/{locality}',   [LocalityController::class, 'destroy']);
});

// ==================== PUBLIC : quote livraison ====================
Route::get('shipping/quote',        [DeliveryZoneController::class, 'quote']);
Route::get('shipping/destinations', [DeliveryZoneController::class, 'destinations']);
// Villes et communes proposées dans les formulaires d'adresse.
Route::get('localities',            [ShopLocalityController::class, 'index']);
