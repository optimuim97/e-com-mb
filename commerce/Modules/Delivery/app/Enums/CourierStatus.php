<?php

namespace Modules\Delivery\Enums;

enum CourierStatus: string
{
    case ACTIVE   = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case BLOCKED  = 'BLOCKED';
}
