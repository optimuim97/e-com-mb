<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\Admin\PostController as AdminPostController;
use Modules\Blog\Http\Controllers\Admin\PostCategoryController as AdminPostCategoryController;
use Modules\Blog\Http\Controllers\Shop\PostController;

// ── Public ──────────────────────────────────────────────────────────────────
Route::middleware('module:blog')->group(function () {
    Route::get('blog',                  [PostController::class, 'index'])->name('blog.index');
    Route::get('blog/categories',       [PostController::class, 'categories']);
    Route::get('blog/{slug}',           [PostController::class, 'show'])->name('blog.show');
});

// ── Admin ────────────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin|staff', 'module:blog'])->prefix('admin')->group(function () {
    Route::apiResource('blog/posts',      AdminPostController::class)->names('admin.blog.posts');
    Route::post('blog/posts/{post}/cover', [AdminPostController::class, 'uploadCover']);
    Route::apiResource('blog/categories', AdminPostCategoryController::class)
         ->except(['show'])
         ->names('admin.blog.categories');
});
