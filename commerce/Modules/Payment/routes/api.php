<?php

use Illuminate\Support\Facades\Route;
use Modules\Payment\Http\Controllers\CinetPayController;
use Modules\Payment\Http\Controllers\GeniusPayController;
use Modules\Payment\Http\Controllers\StripeController;

// ── CinetPay ─────────────────────────────────────────────────────────────────
// Webhook public (appelé par les serveurs CinetPay — pas de auth:sanctum)
Route::post('payment/cinetpay/notify', [CinetPayController::class, 'notify']);

// Status check (frontend vérifie après retour)
Route::get('payment/cinetpay/status/{orderNumber}', [CinetPayController::class, 'status']);

// ── GeniusPay ────────────────────────────────────────────────────────────────
// Webhook public (serveurs GeniusPay — signature HMAC vérifiée dans le controller)
Route::post('payment/geniuspay/notify', [GeniusPayController::class, 'notify']);

// Status check après retour client
Route::get('payment/geniuspay/status/{orderNumber}', [GeniusPayController::class, 'status']);

// ── Stripe ───────────────────────────────────────────────────────────────────
// Webhook public (serveurs Stripe — signature vérifiée dans le controller).
Route::post('payment/stripe/webhook', [StripeController::class, 'notify']);

// Status check après retour du Checkout
Route::get('payment/stripe/status/{orderNumber}', [StripeController::class, 'status']);
