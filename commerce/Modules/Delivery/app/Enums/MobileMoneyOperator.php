<?php

namespace Modules\Delivery\Enums;

enum MobileMoneyOperator: string
{
    case WAVE         = 'WAVE';
    case ORANGE_MONEY = 'ORANGE_MONEY';
    case MTN_MONEY    = 'MTN_MONEY';
}
