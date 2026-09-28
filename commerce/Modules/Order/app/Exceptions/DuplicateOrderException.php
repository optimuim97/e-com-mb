<?php

namespace Modules\Order\Exceptions;

use Modules\Order\Models\Order;
use RuntimeException;

/**
 * The customer already has a similar order on its way.
 *
 * Not an error — a question. The order is held just long enough to show her
 * what she already has coming; confirming places it unchanged.
 */
class DuplicateOrderException extends RuntimeException
{
    public function __construct(public readonly Order $existing)
    {
        parent::__construct('Vous avez déjà une commande similaire en cours.');
    }
}
