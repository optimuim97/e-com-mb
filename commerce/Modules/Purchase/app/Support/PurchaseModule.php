<?php

namespace Modules\Purchase\Support;

use App\Support\ModuleRegistry;

/**
 * Interrupteur du module Achats.
 *
 * Éteint tant qu'on ne l'allume pas — contrairement aux réglages de
 * notification, où un réglage absent vaut « actif ». La raison tient au
 * contenu : prix d'achat, marges et fournisseurs n'ont pas à apparaître dans
 * une boutique qui n'en a pas besoin, et surtout pas par défaut.
 */
final class PurchaseModule
{
    public const SETTING = 'module_purchases_enabled';

    /**
     * Délégué au registre des modules : l'interrupteur des Achats est
     * devenu un cas particulier de la même mécanique, et deux lectures
     * indépendantes du même réglage finiraient par diverger.
     */
    public static function enabled(): bool
    {
        return ModuleRegistry::enabled('purchases');
    }
}
