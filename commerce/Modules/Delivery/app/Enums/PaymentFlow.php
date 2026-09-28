<?php

namespace Modules\Delivery\Enums;

enum PaymentFlow: string
{
    case COD     = 'COD';
    case PREPAID = 'PREPAID';
    case MIXED   = 'MIXED';
}
