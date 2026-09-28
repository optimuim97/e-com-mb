<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixe la locale de l'application à partir de la préférence du client.
 *
 * Priorité : en-tête `X-Locale` (envoyé par le SPA) puis `Accept-Language`.
 * Seules les langues supportées sont acceptées ; sinon on garde la locale
 * par défaut (config APP_LOCALE = fr). Détermine la langue des messages de
 * validation / auth renvoyés au front.
 */
class SetLocale
{
    /** Langues prises en charge. */
    private const SUPPORTED = ['fr', 'en'];

    /** Langue par défaut (l'app est francophone en priorité). */
    private const DEFAULT = 'fr';

    public function handle(Request $request, Closure $next): Response
    {
        // Toujours une locale supportée : préférence client, sinon français.
        App::setLocale($this->resolve($request) ?? self::DEFAULT);

        return $next($request);
    }

    private function resolve(Request $request): ?string
    {
        // 1) En-tête explicite du SPA
        $header = strtolower(trim((string) $request->header('X-Locale', '')));
        if (in_array($header, self::SUPPORTED, true)) {
            return $header;
        }

        // 2) Négociation Accept-Language — uniquement si l'en-tête est présent
        //    (sans en-tête, getPreferredLanguage renverrait un défaut trompeur).
        if ($request->hasHeader('Accept-Language')) {
            $short = substr((string) $request->getPreferredLanguage(self::SUPPORTED), 0, 2);
            if (in_array($short, self::SUPPORTED, true)) {
                return $short;
            }
        }

        return null;
    }
}
