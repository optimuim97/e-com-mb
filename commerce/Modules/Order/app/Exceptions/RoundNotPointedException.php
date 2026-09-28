<?php

namespace Modules\Order\Exceptions;

/**
 * On a tenté de clôturer une tournée dont des livraisons ne sont pas pointées.
 *
 * La clôture arrête les comptes : livré / non livré, attendu / encaissé. Tant
 * qu'une ligne reste en attente, l'écart de caisse est illisible — on ne sait
 * pas si l'argent manque ou si la ligne n'a pas été regardée.
 */
class RoundNotPointedException extends \RuntimeException
{
    public function __construct(public readonly int $remaining)
    {
        parent::__construct(
            $remaining === 1
                ? 'Une livraison n\'est pas encore pointée.'
                : "{$remaining} livraisons ne sont pas encore pointées.",
        );
    }
}
