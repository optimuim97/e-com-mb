<?php

namespace Modules\Delivery\Enums;

enum DeliveryStatus: string
{
    case PENDING     = 'PENDING';
    case IN_PROGRESS = 'IN_PROGRESS';
    case DELIVERED   = 'DELIVERED';
    case FAILED      = 'FAILED';
    case CANCELLED   = 'CANCELLED';
}
