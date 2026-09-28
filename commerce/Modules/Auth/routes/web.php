<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\SocialAuthController;

/*
|--------------------------------------------------------------------------
| Authentification sociale (OAuth popup)
|--------------------------------------------------------------------------
| /auth/facebook/redirect  → redirige vers Facebook
| /auth/facebook/callback  → retour Facebook → token → postMessage au front
*/
Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['facebook', 'google'])
    ->name('social.redirect');

Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['facebook', 'google'])
    ->name('social.callback');
