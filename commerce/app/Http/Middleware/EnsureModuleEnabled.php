<?php

namespace App\Http\Middleware;

use App\Support\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ferme les routes d'un module que la boutique a éteint.
 *
 * Posé sur le groupe de routes plutôt que dans chaque contrôleur : un écran
 * ajouté plus tard hériterait sinon d'un accès ouvert sans que personne ne le
 * remarque.
 *
 * Le menu du back-office masque déjà les modules éteints, mais masquer n'est
 * pas fermer : une URL retenue, un onglet resté ouvert ou un appel direct
 * doivent recevoir la même réponse.
 *
 *   Route::middleware('module:blog')->group(...)
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! ModuleRegistry::enabled($module)) {
            return response()->json([
                'code'    => 'module_disabled',
                'module'  => $module,
                'message' => "Ce module n'est pas activé pour cette boutique.",
            ], 403);
        }

        return $next($request);
    }
}
