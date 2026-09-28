<?php

namespace Modules\Promo\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Promo\Services\PromotionResolver;

class PromoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton : les promotions actives sont lues une seule fois par
        // requête, puis résolues en mémoire pour chaque produit affiché.
        $this->app->singleton(PromotionResolver::class);
    }

    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
