<?php

use Illuminate\Support\Facades\Route;
use Modules\Program\Http\Controllers\Shop\ProgramController;
use Modules\Program\Http\Controllers\Shop\ConsultationController;
use Modules\Program\Http\Controllers\Shop\TrackerController;
use Modules\Program\Http\Controllers\Admin\StatsController;
use Modules\Program\Http\Controllers\Admin\MemberController;
use Modules\Program\Http\Controllers\Admin\TierController;
use Modules\Program\Http\Controllers\Admin\BenefitController;
use Modules\Program\Http\Controllers\Admin\ConsultationController as AdminConsultationController;

// ── Public ───────────────────────────────────────────────────────────────────
// Informations publiques sur le programme (niveaux, avantages)
Route::get('program', [ProgramController::class, 'index'])->middleware('module:program');

// ── Membres connectés ────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'module:program'])->prefix('program')->group(function () {
    Route::get ('membership',           [ProgramController::class, 'membership']);
    Route::post('join',                 [ProgramController::class, 'join']);

    Route::get ('consultations',        [ConsultationController::class, 'index']);
    Route::post('consultations',        [ConsultationController::class, 'store']);
    Route::delete('consultations/{id}', [ConsultationController::class, 'destroy']);

    Route::get ('tracker',              [TrackerController::class, 'index']);
    Route::post('tracker/checkin',      [TrackerController::class, 'checkin']);
});

// ── Admin ────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin|staff', 'module:program'])->prefix('admin/program')->group(function () {
    Route::get('stats',                          [StatsController::class,           'index']);

    Route::get('members',                        [MemberController::class,          'index']);
    Route::get('members/{id}',                   [MemberController::class,          'show']);
    Route::patch('members/{id}',                 [MemberController::class,          'update']);

    Route::get('tiers',                          [TierController::class,            'index']);
    Route::patch('tiers/{id}',                   [TierController::class,            'update']);

    Route::get('benefits',                       [BenefitController::class,         'index']);
    Route::post('benefits',                      [BenefitController::class,         'store']);
    Route::patch('benefits/{id}',                [BenefitController::class,         'update']);
    Route::delete('benefits/{id}',               [BenefitController::class,         'destroy']);

    Route::get('consultations',                  [AdminConsultationController::class,'index']);
    Route::patch('consultations/{id}',           [AdminConsultationController::class,'update']);
});
