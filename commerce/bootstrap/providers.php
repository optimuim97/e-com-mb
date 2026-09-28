<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Modules\Cart\Providers\CartServiceProvider::class,
    Modules\Wishlist\Providers\WishlistServiceProvider::class,
    Modules\Blog\Providers\BlogServiceProvider::class,
    Modules\Program\Providers\ProgramServiceProvider::class,
    Modules\Review\Providers\ReviewServiceProvider::class,
    Modules\Promo\Providers\PromoServiceProvider::class,
    Modules\Newsletter\Providers\NewsletterServiceProvider::class,
];
