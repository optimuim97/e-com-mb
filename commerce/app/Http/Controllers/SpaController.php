<?php

namespace App\Http\Controllers;

use App\Services\Seo\PageMeta;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Support\Shop;
use Modules\Admin\Models\Setting;

/**
 * Sert la coquille du SPA en y injectant les balises SEO de l'URL demandée.
 *
 * Pourquoi faire passer une page statique par PHP : le front pose ses balises
 * en JavaScript, après le montage de Vue. Les robots de WhatsApp et de
 * Facebook n'en exécutent jamais ; Google le fait en seconde passe, avec
 * retard. Constat mesuré avant ce correctif : les 34 URL du sitemap
 * renvoyaient toutes le titre et la description de la page d'accueil.
 *
 * La coquille n'est pas dans cette image — le front est un container séparé —
 * donc on la récupère auprès de nginx, qui la sert depuis son disque. Le
 * chemin `/index.html` existe chez lui : il le rend directement sans repasser
 * par ici, il n'y a donc pas de boucle.
 */
class SpaController extends Controller
{
    private const CLE_CACHE = 'spa:shell';

    /**
     * Durée de vie courte et assumée.
     *
     * La coquille référence les assets Vite par leur nom haché. Après un
     * déploiement du front, les anciens fichiers n'existent plus : une
     * coquille périmée servirait des scripts en 404, autrement dit une page
     * blanche. Une minute borne cette fenêtre sans avoir à prévenir le
     * script de déploiement de l'existence de ce cache.
     */
    private const TTL_CACHE = 60;

    public function __construct(private readonly PageMeta $meta) {}

    public function __invoke(Request $request): Response
    {
        // Route de repli : elle reçoit aussi les URL d'API inconnues. Leur
        // répondre une page HTML transformerait une erreur d'appel en écran
        // blanc côté client, là où un 404 se diagnostique en une seconde.
        if ($request->is('api/*') || $request->expectsJson()) {
            abort(404);
        }

        $coquille = $this->coquille();

        if ($coquille === null) {
            // Sans coquille, rien à servir : mieux vaut un 503 franc, que les
            // robots réessaieront, qu'une page à moitié construite qu'ils
            // indexeraient telle quelle.
            return response('Service momentanément indisponible', 503);
        }

        // Marque blanche : la coquille est commune à toutes les boutiques.
        // Nom et couleur de la boutique sont posés à chaque réponse, y compris
        // pour les pages sans balises propres.
        $coquille = $this->injecterMarque($coquille);

        $balises = $this->meta->pour($request->path());

        if ($balises === null) {
            return $this->html($coquille);
        }

        return $this->html($this->injecter($coquille, $balises, $request->url()));
    }

    // ── Coquille ─────────────────────────────────────────────────────────────

    private function coquille(): ?string
    {
        $cache = Cache::get(self::CLE_CACHE);

        if (is_string($cache)) {
            return $cache;
        }

        try {
            $reponse = Http::timeout(3)->get(config('app.spa_shell_url'));

            if ($reponse->failed()) {
                Log::warning('[SPA] Coquille inaccessible', ['status' => $reponse->status()]);

                return null;
            }

            $html = $reponse->body();
            Cache::put(self::CLE_CACHE, $html, self::TTL_CACHE);

            return $html;
        } catch (\Throwable $e) {
            Log::warning('[SPA] Coquille injoignable : ' . $e->getMessage());

            return null;
        }
    }

    // ── Injection ────────────────────────────────────────────────────────────

    /**
     * Remplace les balises existantes plutôt que d'en ajouter.
     *
     * En ajouter laisserait deux `og:title` dans le document ; les robots
     * sociaux retiennent alors le premier rencontré, c'est-à-dire celui de la
     * page d'accueil — le correctif n'aurait servi à rien.
     *
     * @param  array{title: string, description: string, image: ?string, type: string}  $balises
     */
    private function injecter(string $html, array $balises, string $url): string
    {
        $titre = e($balises['title']);
        $desc  = e($balises['description']);
        $image = e((string) $balises['image']);

        $html = preg_replace(
            '#<title>.*?</title>#is',
            '<title>' . $titre . '</title>',
            $html,
            1,
        );

        $html = $this->remplacerMeta($html, 'name', 'description', $desc);

        $html = $this->remplacerMeta($html, 'property', 'og:title', $titre);
        $html = $this->remplacerMeta($html, 'property', 'og:description', $desc);
        $html = $this->remplacerMeta($html, 'property', 'og:image', $image);
        $html = $this->remplacerMeta($html, 'property', 'og:type', e($balises['type']));
        $html = $this->remplacerMeta($html, 'property', 'og:url', e($url));

        $html = $this->remplacerMeta($html, 'name', 'twitter:title', $titre);
        $html = $this->remplacerMeta($html, 'name', 'twitter:description', $desc);
        $html = $this->remplacerMeta($html, 'name', 'twitter:image', $image);

        return $this->poserCanonique($html, e($url));
    }

    /**
     * Identité de la boutique dans la coquille : nom affiché par les robots
     * sociaux et sur l'écran d'accueil iOS, couleur de la barre d'adresse.
     */
    private function injecterMarque(string $html): string
    {
        $nom = e(Shop::name());

        $html = $this->remplacerMeta($html, 'property', 'og:site_name', $nom);
        $html = $this->remplacerMeta($html, 'name', 'apple-mobile-web-app-title', $nom);

        $couleur = (string) Shop::setting('brand_primary_color', '');
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $couleur)) {
            $html = $this->remplacerMeta($html, 'name', 'theme-color', '#' . ltrim($couleur, '#'));
        }

        $favicon = trim((string) Shop::setting('shop_favicon_url', ''));
        if ($favicon !== '') {
            $html = preg_replace(
                '#(<link\s+rel="(?:icon|apple-touch-icon)"[^>]*href=")[^"]*(")#i',
                '${1}' . e($favicon) . '${2}',
                $html,
            );
        }

        return $html;
    }

    /** Met à jour une balise meta, ou la crée si la coquille ne la porte pas. */
    private function remplacerMeta(string $html, string $attribut, string $cle, string $valeur): string
    {
        $motif = '#<meta\s+[^>]*' . preg_quote($attribut, '#') . '\s*=\s*["\']'
               . preg_quote($cle, '#') . '["\'][^>]*>#i';

        $balise = '<meta ' . $attribut . '="' . $cle . '" content="' . $valeur . '" />';

        if (preg_match($motif, $html)) {
            return preg_replace($motif, $balise, $html, 1);
        }

        return $this->insererDansHead($html, $balise);
    }

    private function poserCanonique(string $html, string $url): string
    {
        $motif  = '#<link\s+[^>]*rel\s*=\s*["\']canonical["\'][^>]*>#i';
        $balise = '<link rel="canonical" href="' . $url . '" />';

        if (preg_match($motif, $html)) {
            return preg_replace($motif, $balise, $html, 1);
        }

        return $this->insererDansHead($html, $balise);
    }

    private function insererDansHead(string $html, string $balise): string
    {
        return preg_replace('#</head>#i', '  ' . $balise . "\n</head>", $html, 1);
    }

    private function html(string $contenu): Response
    {
        return response($contenu, 200)
            ->header('Content-Type', 'text/html; charset=UTF-8')
            // La coquille change à chaque déploiement du front : la laisser en
            // cache navigateur ferait réapparaître la panne d'assets que le
            // TTL court cherche justement à borner.
            ->header('Cache-Control', 'no-cache, must-revalidate');
    }
}
