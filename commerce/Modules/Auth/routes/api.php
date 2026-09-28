<?php
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\ForgotPasswordController;
use Modules\Auth\Http\Controllers\ForgotPinController;
use Modules\Auth\Http\Controllers\PinController;
use Modules\Auth\Http\Controllers\ProfileController;
use Modules\Auth\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::post('auth/register', [AuthController::class, 'register']);
Route::post('auth/login',    [AuthController::class, 'login']);

// ── Mot de passe / PIN oublié (OTP via email ou WhatsApp) ───────────────────
Route::post('auth/password/forgot', [ForgotPasswordController::class, 'request']);
Route::post('auth/password/reset',  [ForgotPasswordController::class, 'reset']);
Route::post('auth/pin/forgot',      [ForgotPinController::class, 'request']);
Route::post('auth/pin/reset',       [ForgotPinController::class, 'reset']);

// ── Auth sociale (sous /api → atteignable via le proxy front) ───────────────
// State anti-CSRF en cache (pas de session requise) → middleware api OK.
Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', ['facebook', 'google'])->name('api.social.redirect');
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', ['facebook', 'google'])->name('api.social.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout',     [AuthController::class, 'logout']);
    Route::get('auth/me',          [AuthController::class, 'me']);
    Route::post('auth/pin/set',    [PinController::class, 'set']);
    Route::post('auth/pin/verify', [PinController::class, 'verify']);

    // ── Profil utilisateur ─────────────────────────────────────────────────
    Route::get ('profile',                [ProfileController::class, 'show']);
    Route::put ('profile/info',           [ProfileController::class, 'updateInfo']);
    Route::put ('profile/setup-account',  [ProfileController::class, 'setupAccount']);
    Route::put ('profile/password',       [ProfileController::class, 'changePassword']);
});
