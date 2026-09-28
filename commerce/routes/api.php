<?php

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Routes are defined in each module's routes/api.php
// They are auto-loaded by nwidart/laravel-modules

// ── Webhook WhatsApp Cloud API ──────────────────────────────────────────────
// Sous /api car seul ce préfixe est routé vers Laravel par Nginx.
//   → https://<domaine-boutique>/api/whatsapp/webhook
// GET  : vérification de l'URL par Meta à l'enregistrement.
// POST : messages entrants et statuts. Publique par nécessité — la
//        signature est contrôlée dans le contrôleur.
Route::get('whatsapp/webhook',  [WhatsAppWebhookController::class, 'verify']);
Route::post('whatsapp/webhook', [WhatsAppWebhookController::class, 'handle']);

// ── Pages légales (requises par Facebook / RGPD) ────────────────────────────
// Exposées sous /api car seul ce préfixe est routé vers Laravel par Nginx.
//   → https://<domaine-boutique>/api/politique-de-confidentialite
//   → https://<domaine-boutique>/api/suppression-donnees
Route::view('politique-de-confidentialite', 'legal.privacy')->name('api.legal.privacy');
Route::view('suppression-donnees', 'legal.data-deletion')->name('api.legal.data-deletion');
