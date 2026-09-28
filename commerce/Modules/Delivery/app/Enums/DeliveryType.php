<?php

namespace Modules\Delivery\Enums;

enum DeliveryType: string
{
    case LOCAL_STANDARD        = 'LOCAL_STANDARD';
    case LOCAL_EXTENDED        = 'LOCAL_EXTENDED';
    case NATIONAL_EXPEDITION   = 'NATIONAL_EXPEDITION';
    case REGIONAL_AFRICA       = 'REGIONAL_AFRICA';
    case INTERNATIONAL_GP      = 'INTERNATIONAL_GP';
    case INTERNATIONAL_DHL     = 'INTERNATIONAL_DHL';
}
