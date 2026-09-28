<?php

namespace Modules\Order\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\Order\Console\CacheLogoCommand;
use Modules\Order\Console\DemoDataCommand;
use Modules\Order\Console\SendPaymentRemindersCommand;
use Modules\Order\Console\SendShipmentSheetCommand;
use Modules\Order\Console\TestConfirmationMailCommand;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Events\OrderShipped;
use Modules\Order\Listeners\NotifyAdminsOnOrderCancelled;
use Modules\Order\Listeners\NotifyAdminsOnOrderPlaced;
use Modules\Order\Listeners\NotifyAdminsOnOrderShipped;
use Modules\Order\Listeners\SendOrderConfirmationToCustomer;
use Modules\Order\Listeners\SendOrderConfirmationWhatsAppToCustomer;

class OrderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware('api')->prefix('api')->group(__DIR__ . '/../../routes/api.php');

        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'order');

        // Zone locale : la ville mémorisée tombe dès que la boutique la change.
        \Modules\Admin\Models\Setting::saved(function ($setting) {
            if ($setting->key === \Modules\Order\Support\HomeZone::SETTING) {
                \Modules\Order\Support\HomeZone::forget();
                \Modules\Delivery\Models\DeliveryZone::forgetAbidjanZoneNames();
            }
        });

        // Événements / Listeners
        Event::listen(OrderPlaced::class,    NotifyAdminsOnOrderPlaced::class);
        Event::listen(OrderPlaced::class,    SendOrderConfirmationToCustomer::class);
        Event::listen(OrderPlaced::class,    SendOrderConfirmationWhatsAppToCustomer::class);
        Event::listen(OrderShipped::class,   NotifyAdminsOnOrderShipped::class);
        Event::listen(OrderCancelled::class, NotifyAdminsOnOrderCancelled::class);

        // Commandes artisan
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestConfirmationMailCommand::class,
                CacheLogoCommand::class,
                SendShipmentSheetCommand::class,
                SendPaymentRemindersCommand::class,
                DemoDataCommand::class,
            ]);
        }
    }
}
