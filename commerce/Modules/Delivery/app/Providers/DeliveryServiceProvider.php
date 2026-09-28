<?php

namespace Modules\Delivery\Providers;

use Illuminate\Routing\Router;
use Modules\Delivery\Http\Middleware\CourierMiddleware;
use Nwidart\Modules\Support\ModuleServiceProvider;

class DeliveryServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Delivery';

    protected string $nameLower = 'delivery';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('courier', CourierMiddleware::class);
    }
}
