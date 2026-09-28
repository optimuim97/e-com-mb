<?php

use App\Http\Controllers\SeoController;
use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

// ── SEO : générés côté serveur (dépendent du catalogue et de l'environnement) ─
Route::get('/robots.txt',  [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

// ── Pages légales (requises par Facebook / RGPD) ────────────────────────────
Route::view('/politique-de-confidentialite', 'legal.privacy')->name('legal.privacy');
Route::view('/suppression-donnees', 'legal.data-deletion')->name('legal.data-deletion');

/*
 * Coquille du SPA avec balises SEO injectées.
 *
 * `fallback` et non `get('/{any?}')` : « déclarée en dernier » ne voulait rien
 * dire ici. Les modules enregistrent leurs routes depuis leur `boot()`, donc
 * après ce fichier — une route attrape-tout posée ici passait devant elles et
 * avalait `/api/wishlist`, `/api/orders` et le reste. Une route de repli, elle,
 * n'est consultée que lorsque plus aucune autre ne correspond, quel que soit
 * l'ordre d'enregistrement.
 *
 * nginx ne lui envoie de toute façon que les chemins qui pèsent en
 * référencement ; le tunnel d'achat et l'espace client restent servis en
 * statique, sans PHP.
 */
Route::fallback(SpaController::class);
