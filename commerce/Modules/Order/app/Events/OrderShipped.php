<?php

namespace Modules\Order\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Models\Order;

class OrderShipped
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}
