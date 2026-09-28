<?php
use Modules\Order\Http\Controllers\Shop\OrderController;
use Modules\Order\Http\Controllers\Shop\QuickOrderController;
use Modules\Order\Http\Controllers\Admin\DeliveryRoundController;
use Modules\Order\Http\Controllers\Admin\ExtractionController;
use Modules\Order\Http\Controllers\Courier\RoundController as CourierRoundController;
use Modules\Order\Http\Controllers\Admin\OrderController as AdminOrderController;
use Modules\Order\Http\Controllers\Admin\PosController;
use Modules\Order\Http\Controllers\Admin\WhatsAppInboxController;
use Illuminate\Support\Facades\Route;

// ── Commande rapide (sans authentification) ────────────────────────────────
Route::post('quick-order', [QuickOrderController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders',                    [OrderController::class, 'index']);
    Route::post('orders',                   [OrderController::class, 'store']);
    Route::get('orders/{number}',           [OrderController::class, 'show']);
    Route::get('orders/{number}/invoice',   [OrderController::class, 'invoice']);
    Route::post('orders/{number}/pay',      [OrderController::class, 'pay']);
    Route::post('orders/{number}/cancel',   [OrderController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'role:admin|staff'])->prefix('admin')->group(function () {
    // ⚠️ Les routes statiques (export, stats) AVANT les routes paramétrées ({order})
    Route::get('orders/stats',            [AdminOrderController::class, 'stats']);
    // File « À traiter » : ce qui a dépassé son délai.
    Route::get('orders/attention',        [AdminOrderController::class, 'attention']);
    // POST accepté : l'aperçu d'une sélection cochée transporte ses identifiants.
    Route::match(['get', 'post'], 'orders/export-preview', [AdminOrderController::class, 'exportPreview']);

    /*
     * Exports : GET et POST sur la même action.
     *
     * Une sélection se compte en centaines de commandes, et `order_ids[]=…`
     * répété produisait une ligne de requête de 14 Ko — au-delà des 8 Ko que
     * nginx accepte, d'où un 414 et une feuille de livraison impossible à
     * sortir sur un gros lot. Le front poste donc la sélection dans le corps.
     *
     * Le GET reste ouvert : il sert les petites sélections, les liens déjà
     * partagés et le débogage à la main.
     */
    Route::match(['get', 'post'], 'orders/export',             [AdminOrderController::class, 'export']);
    // Extraction de l'écran des expéditions : population différente de
    // `orders/export`, qui écarte les commandes non payées hors Abidjan.
    Route::match(['get', 'post'], 'orders/export-shipments',   [AdminOrderController::class, 'exportShipments']);
    Route::match(['get', 'post'], 'orders/export-by-zone',     [AdminOrderController::class, 'exportByZone']);
    Route::match(['get', 'post'], 'orders/export-by-zone-txt', [AdminOrderController::class, 'exportByZoneTxt']);
    Route::get('orders/by-zone',          [AdminOrderController::class, 'byZone']);
    // Expédition en masse sans passer par une tournée : mêmes garde-fous.
    Route::post('orders/bulk-ship',          [AdminOrderController::class, 'bulkShip']);
    Route::post('orders/bulk-process',       [AdminOrderController::class, 'bulkProcess']);
    Route::post('orders/bulk-mark-exported', [AdminOrderController::class, 'bulkMarkExported']);
    Route::post('orders/bulk-mark-paid',     [AdminOrderController::class, 'bulkMarkPaid']);
    Route::post('orders/bulk-deliver',       [AdminOrderController::class, 'bulkDeliver']);
    // Archivage : ranger ce qui est réglé, sans rien supprimer.
    Route::post('orders/bulk-archive',       [AdminOrderController::class, 'bulkArchive']);
    Route::post('orders/bulk-unarchive',     [AdminOrderController::class, 'bulkUnarchive']);

    // Extractions par transporteur, selon leur calendrier de passage. Déclarées
    // avant les routes `orders/{order}/…` pour ne pas être prises pour un id.
    Route::get('orders/extractions',         [ExtractionController::class, 'index']);
    Route::post('orders/extractions/{type}', [ExtractionController::class, 'store']);
    Route::post('orders/bulk-cancel',        [AdminOrderController::class, 'bulkCancel']);
    Route::apiResource('orders', AdminOrderController::class)->only(['index', 'show', 'update']);
    Route::get('orders/{order}/invoice',            [AdminOrderController::class, 'invoice']);
    Route::get('orders/{order}/whatsapp-message',   [AdminOrderController::class, 'whatsappMessage']);
    Route::post('orders/{order}/notify',            [AdminOrderController::class, 'notify']);
    Route::post('orders/{order}/mark-paid',         [AdminOrderController::class, 'markPaid']);
    Route::post('orders/{order}/payment-link',      [AdminOrderController::class, 'paymentLink']);
    Route::post('orders/{order}/generate-tracking', [AdminOrderController::class, 'generateTracking']);

    // ── Tournées de livraison ───────────────────────────────────────────────
    // `sheet` avant `{code}` : sans cela « TR-2808-A/sheet » serait pris pour
    // un code. Le paramètre accepte les tirets du code.
    Route::middleware('module:rounds')->group(function () {
        Route::get ('delivery-rounds',                [DeliveryRoundController::class, 'index']);
        Route::get ('delivery-rounds/couriers',       [DeliveryRoundController::class, 'couriers']);
        // Créer un livreur sans passer par l'écran des utilisateurs : le rôle
        // est imposé, l'habilitation reste celle des tournées.
        Route::post('delivery-rounds/couriers',       [DeliveryRoundController::class, 'storeCourier']);
        Route::post('delivery-rounds',                [DeliveryRoundController::class, 'store']);
        Route::get ('delivery-rounds/{code}/sheet',   [DeliveryRoundController::class, 'sheet']);
        // Confirmation du départ d'une tournée préparée la veille : c'est ici
        // que le livreur est affecté et que les colis quittent la boutique.
        Route::post('delivery-rounds/{code}/dispatch', [DeliveryRoundController::class, 'dispatch']);
        // Pointage au retour, puis clôture des comptes.
        Route::patch('delivery-rounds/{code}/lines/{line}', [DeliveryRoundController::class, 'pointLine']);
        Route::post ('delivery-rounds/{code}/close',        [DeliveryRoundController::class, 'close']);
        Route::get   ('delivery-rounds/{code}',       [DeliveryRoundController::class, 'show']);
        // Abandon d'un brouillon : libère les commandes qu'il retenait.
        Route::delete('delivery-rounds/{code}',       [DeliveryRoundController::class, 'destroy']);
    });

    // ── POS admin (caisse / commande manuelle) ──────────────────────────────
    Route::post('pos/parse',  [PosController::class, 'parse']);
    Route::post('pos/orders', [PosController::class, 'store']);

    // ── File WhatsApp Business (double saisie) ───────────────────────────────
    // Statique avant paramétrée, sinon « reconciliation » serait pris pour un id.
    Route::middleware('module:whatsapp')->group(function () {
        Route::get('whatsapp/reconciliation',              [WhatsAppInboxController::class, 'reconciliation']);
        Route::get('whatsapp/messages',                    [WhatsAppInboxController::class, 'index']);
        Route::get('whatsapp/messages/{message}',          [WhatsAppInboxController::class, 'show']);
        Route::post('whatsapp/messages/{message}/parse',   [WhatsAppInboxController::class, 'parse']);
        Route::post('whatsapp/messages/{message}/link',    [WhatsAppInboxController::class, 'link']);
        Route::post('whatsapp/messages/{message}/ignore',  [WhatsAppInboxController::class, 'ignore']);
        Route::post('whatsapp/messages/{message}/reopen',  [WhatsAppInboxController::class, 'reopen']);
    });
});

// ── Espace livreur ──────────────────────────────────────────────────────────
// Hors du groupe `role:admin|staff` : un livreur n'est ni l'un ni l'autre, et
// n'entre pas dans le back-office. La permission autorise le pointage ; ce sont
// les requêtes du contrôleur, bornées à `courier_id`, qui disent lesquelles.
Route::middleware(['auth:sanctum', 'permission:rounds.point', 'module:rounds'])->prefix('livreur')->group(function () {
    Route::get  ('rounds',                     [CourierRoundController::class, 'index']);
    Route::get  ('rounds/{code}',              [CourierRoundController::class, 'show']);
    Route::patch('rounds/{code}/lines/{line}', [CourierRoundController::class, 'pointLine']);
});
