<?php

use Illuminate\Support\Facades\Route;
use Modules\Newsletter\Http\Controllers\SubscriptionController;
use Modules\Newsletter\Http\Controllers\Admin\NewsletterController;

// ── Public ──────────────────────────────────────────────────────────────────
Route::post('newsletter/subscribe',           [SubscriptionController::class, 'subscribe'])->middleware('module:newsletter');
// Jamais fermé : un abonné doit pouvoir se désinscrire même module éteint.
Route::get('newsletter/unsubscribe/{token}',  [SubscriptionController::class, 'unsubscribe']);

// ── Admin ───────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin|staff', 'module:newsletter'])->prefix('admin')->group(function () {
    // Abonnés
    Route::get('newsletter/subscribers',                 [NewsletterController::class, 'subscribers']);
    Route::post('newsletter/subscribers',                [NewsletterController::class, 'storeSubscriber']);
    Route::delete('newsletter/subscribers/{subscriber}', [NewsletterController::class, 'destroySubscriber']);

    // Campagnes
    Route::get('newsletter/campaigns',                 [NewsletterController::class, 'campaigns']);
    Route::post('newsletter/campaigns',                [NewsletterController::class, 'storeCampaign']);
    Route::put('newsletter/campaigns/{campaign}',      [NewsletterController::class, 'updateCampaign']);
    Route::delete('newsletter/campaigns/{campaign}',   [NewsletterController::class, 'destroyCampaign']);
    Route::post('newsletter/campaigns/{campaign}/test',[NewsletterController::class, 'test']);
    Route::post('newsletter/campaigns/{campaign}/send',[NewsletterController::class, 'send']);
});
