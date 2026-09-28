<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Auth\Services\Social\SocialAuthManager;
use Modules\Auth\Services\Social\SocialAuthService;

/**
 * Authentification sociale (popup OAuth).
 *
 * Flux :
 *   1. Le frontend ouvre une popup vers GET /auth/{provider}/redirect
 *   2. On redirige vers le fournisseur (Facebook…)
 *   3. Le fournisseur revient sur GET /auth/{provider}/callback?code=...&state=...
 *   4. On crée/lie l'utilisateur, on émet un token Sanctum
 *   5. On renvoie une page HTML qui postMessage le résultat à la fenêtre
 *      parente (le frontend) puis se ferme.
 */
class SocialAuthController extends Controller
{
    public function __construct(
        private readonly SocialAuthManager $manager,
        private readonly SocialAuthService $service,
    ) {}

    /** Étape 1-2 : redirection vers le fournisseur. */
    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless($this->manager->supports($provider), 404);

        $driver = $this->manager->driver($provider);
        abort_unless($driver->isConfigured(), 503, "Le fournisseur {$provider} n'est pas configuré.");

        // Anti-CSRF : state aléatoire stocké en cache (sans cookie → robuste
        // même quand les cookies de session cross-domain ne passent pas).
        $state = Str::random(40);
        Cache::put($this->stateKey($provider, $state), true, now()->addMinutes(10));

        return redirect()->away($driver->authorizationUrl($state));
    }

    /** Étape 3-5 : retour du fournisseur → token → postMessage. */
    public function callback(Request $request, string $provider)
    {
        if (!$this->manager->supports($provider)) {
            return $this->respond($provider, ['error' => 'unsupported_provider']);
        }

        // L'utilisateur a refusé / erreur côté fournisseur
        if ($request->filled('error')) {
            return $this->respond($provider, ['error' => $request->get('error_description', 'access_denied')]);
        }

        // Vérification du state (anti-CSRF) via cache
        $state = (string) $request->get('state');
        if (!$state || !Cache::pull($this->stateKey($provider, $state))) {
            return $this->respond($provider, ['error' => 'invalid_state']);
        }

        try {
            $social = $this->manager->driver($provider)->userFromCode((string) $request->get('code'));
            $result = $this->service->authenticate($provider, $social);

            return $this->respond($provider, [
                'token' => $result['token'],
                'user'  => [
                    'id'     => $result['user']->id,
                    'name'   => $result['user']->name,
                    'email'  => $result['user']->email,
                    'avatar' => $result['user']->avatar_url,
                ],
                'created' => $result['created'],
            ]);
        } catch (\Throwable $e) {
            Log::error("[SocialAuth:{$provider}] " . $e->getMessage());
            return $this->respond($provider, ['error' => 'auth_failed']);
        }
    }

    private function stateKey(string $provider, string $state): string
    {
        return "social_state:{$provider}:{$state}";
    }

    /**
     * Renvoie une page HTML qui transmet le résultat à la fenêtre parente
     * via postMessage, puis se ferme. Fallback : redirection si pas de popup.
     */
    private function respond(string $provider, array $payload)
    {
        $frontend = rtrim((string) config('services.social_auth.frontend_url'), '/');
        $json     = json_encode([
            'source'   => 'social-auth',
            'provider' => $provider,
            ...$payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Fallback (si la fenêtre n'a pas d'opener) : redirige le front avec le résultat
        $fallback = $frontend . '/auth/social/callback#' . rawurlencode($json);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><title>Connexion…</title></head>
<body style="font-family:system-ui;background:#fdf4f5;color:#3d2731;display:flex;align-items:center;justify-content:center;height:100vh;margin:0">
<p>Connexion en cours…</p>
<script>
  (function () {
    var data = {$json};
    var target = "{$frontend}";
    try {
      if (window.opener && !window.opener.closed) {
        window.opener.postMessage(data, target);
        window.close();
        return;
      }
    } catch (e) {}
    // Pas de popup : on redirige
    window.location.replace("{$fallback}");
  })();
</script>
</body></html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }
}
