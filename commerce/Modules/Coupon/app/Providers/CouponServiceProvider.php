<?php

namespace Modules\Coupon\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Modules\Coupon\Services\CouponService;

class CouponServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CouponService::class);
    }

    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
