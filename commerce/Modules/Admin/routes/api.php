<?php
use Modules\Admin\Http\Controllers\DashboardController;
use Modules\Admin\Http\Controllers\SettingController;
use Modules\Admin\Http\Controllers\ContactController;
use Modules\Admin\Http\Controllers\UserController;
use Modules\Admin\Http\Controllers\RoleController;
use Modules\Admin\Http\Controllers\PermissionController;
use Modules\Admin\Http\Controllers\GalleryPhotoController;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\ActivityLogController;

Route::get('settings', [SettingController::class, 'public']);

// Galerie de la page d'accueil — endpoint public (home)
Route::get('gallery', [GalleryPhotoController::class, 'public']);

// Contact form — public endpoint
Route::post('contact', [ContactController::class, 'store']);

Route::middleware(['auth:sanctum', 'role:admin|staff'])->prefix('admin')->group(function () {
    Route::get('dashboard', DashboardController::class);
    Route::get('settings',  [SettingController::class, 'index']);
    Route::get('modules',   [SettingController::class, 'modules']);
    Route::put('settings',  [SettingController::class, 'update']);

    // ── Gestion des utilisateurs ──────────────────────────────────────────
    Route::get   ('users',         [UserController::class, 'index']);
    // Journal des actions du tableau de bord
    Route::get('activity',                    [ActivityLogController::class, 'index']);
    Route::get('activity/{type}/{id}',        [ActivityLogController::class, 'forSubject']);

    Route::post  ('users',         [UserController::class, 'store']);
    Route::get   ('users/{user}',  [UserController::class, 'show']);
    Route::put   ('users/{user}',  [UserController::class, 'update']);
    Route::delete('users/{user}',  [UserController::class, 'destroy']);

    // ── Gestion des rôles (admin seulement) ───────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get   ('roles',         [RoleController::class, 'index']);
        Route::post  ('roles',         [RoleController::class, 'store']);
        Route::put   ('roles/{role}',  [RoleController::class, 'update']);
        Route::delete('roles/{role}',  [RoleController::class, 'destroy']);

        Route::get   ('permissions',              [PermissionController::class, 'index']);
        Route::post  ('permissions',              [PermissionController::class, 'store']);
        Route::put   ('permissions/{permission}', [PermissionController::class, 'update']);
        Route::delete('permissions/{permission}', [PermissionController::class, 'destroy']);
    });

    // ── Galerie de la page d'accueil ──────────────────────────────────────
    Route::middleware('module:gallery')->group(function () {
        Route::get   ('gallery',          [GalleryPhotoController::class, 'index']);
        Route::post  ('gallery',          [GalleryPhotoController::class, 'store']);
        Route::put   ('gallery-reorder',  [GalleryPhotoController::class, 'reorder']);
        Route::put   ('gallery/{photo}',  [GalleryPhotoController::class, 'update']);
        Route::delete('gallery/{photo}',  [GalleryPhotoController::class, 'destroy']);
    });

    // Contact inquiries management
    Route::get ('contact',           [ContactController::class, 'index']);
    Route::get ('contact/unread',    [ContactController::class, 'unreadCount']);
    Route::get ('contact/{inquiry}', [ContactController::class, 'show']);
    Route::put ('contact/{inquiry}', [ContactController::class, 'update']);
    Route::delete('contact/{inquiry}', [ContactController::class, 'destroy']);

    // ── Routes de diagnostic (admin uniquement) ──────────────────────────────
    Route::prefix('test')->group(function () {
        Route::get('smtp',         [\Modules\Admin\Http\Controllers\DiagnosticController::class, 'smtp']);
        Route::get('mail-order',   [\Modules\Admin\Http\Controllers\DiagnosticController::class, 'mailOrder']);
        Route::get('notifications',[\Modules\Admin\Http\Controllers\DiagnosticController::class, 'notifications']);
        Route::get('config',       [\Modules\Admin\Http\Controllers\DiagnosticController::class, 'config']);
    });
});

