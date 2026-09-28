<?php

namespace Modules\Delivery\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CourierMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Non authentifié.'
            ], 401);
        }

        // Vérifier le rôle courier OU l'ability du token
        if (!$user->hasRole('courier') && !$request->user()->tokenCan('courier')) {
            return response()->json([
                'message' => 'Accès réservé aux livreurs.'
            ], 403);
        }

        if ($user->status !== 'ACTIVE') {
            return response()->json([
                'message' => 'Compte livreur inactif.'
            ], 403);
        }

        return $next($request);
    }
}
