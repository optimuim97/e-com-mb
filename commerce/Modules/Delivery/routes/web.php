<?php

use Illuminate\Support\Facades\Route;
use Modules\Delivery\Http\Controllers\DeliveryController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('deliveries', DeliveryController::class)->names('delivery');
});
