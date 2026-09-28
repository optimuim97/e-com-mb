<?php

namespace Modules\Wishlist\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class WishlistServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
