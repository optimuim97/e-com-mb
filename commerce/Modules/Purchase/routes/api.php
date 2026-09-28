<?php

use Illuminate\Support\Facades\Route;
use Modules\Purchase\Http\Controllers\Admin\PurchaseOrderController;
use Modules\Purchase\Http\Controllers\Admin\SupplierController;
use Modules\Purchase\Http\Middleware\EnsurePurchasesEnabled;

/*
| Module Achats — réservé à l'administration.
|
| Trois verrous, du plus large au plus fin :
|   1. `role:admin`  — prix d'achat et marges ne sortent pas du cercle admin ;
|   2. l'interrupteur du module, éteint par défaut ;
|   3. les permissions `purchases.*` / `suppliers.*` portées par les contrôleurs.
*/

Route::middleware(['auth:sanctum', 'role:admin', EnsurePurchasesEnabled::class])
    ->prefix('admin')
    ->group(function () {

        Route::apiResource('suppliers', SupplierController::class);

        // Statiques et actions avant la ressource : « receive » serait sinon
        // interprété comme un identifiant de bon d'achat.
        Route::post('purchase-orders/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive']);
        Route::post('purchase-orders/{purchaseOrder}/cancel',  [PurchaseOrderController::class, 'cancel']);

        Route::apiResource('purchase-orders', PurchaseOrderController::class);
    });
