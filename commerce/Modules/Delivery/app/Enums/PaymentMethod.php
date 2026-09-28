<?php

namespace Modules\Delivery\Enums;

enum PaymentMethod: string
{
    case CASH         = 'CASH';
    case MOBILE_MONEY = 'MOBILE_MONEY';
}
