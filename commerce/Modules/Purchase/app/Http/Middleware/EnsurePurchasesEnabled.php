<?php

namespace Modules\Purchase\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Purchase\Support\PurchaseModule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme les routes du module Achats tant qu'il n'est pas activé.
 *
 * Posé sur les routes plutôt que dans chaque contrôleur : un écran ajouté plus
 * tard hériterait sinon d'un accès ouvert sans que personne ne le remarque.
 */
class EnsurePurchasesEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! PurchaseModule::enabled()) {
            return response()->json([
                'message' => "Le module Achats n'est pas activé pour cette boutique.",
            ], 403);
        }

        return $next($request);
    }
}
