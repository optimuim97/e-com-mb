<?php

namespace Modules\Order\Exceptions;

/**
 * Aucune des commandes cochées ne pouvait partir.
 *
 * Porte le détail des refus : l'agent doit savoir quoi corriger — des frais à
 * saisir, un règlement à encaisser — plutôt que de recevoir un « échec » sans
 * suite et de recommencer la même sélection.
 */
class NoShippableOrdersException extends \RuntimeException
{
    /** @param array<int, array{id: int, number: string, reasons: array<int, string>, forceable: bool}> $rejected */
    public function __construct(public readonly array $rejected)
    {
        parent::__construct('Aucune des commandes sélectionnées ne peut être expédiée.');
    }
}
