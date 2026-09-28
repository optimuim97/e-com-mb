<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Blog\Models\Post;
use Modules\Catalog\Models\Category;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductLine;

/**
 * robots.txt et sitemap.xml.
 *
 * Servis par Laravel plutôt que déposés en fichiers statiques dans le front :
 * leur contenu dépend de l'environnement (la recette ne doit pas être indexée)
 * et du catalogue (produits, gammes, articles changent en continu). L'image
 * front est promue telle quelle de la recette vers la production — y figer un
 * domaine ou une liste d'URL la rendrait non promouvable.
 */
class SeoController extends Controller
{
    /** Pages fixes de la boutique : chemin => [priorité, fréquence]. */
    private const STATIC_PAGES = [
        '/'                            => ['1.0', 'daily'],
        '/products'                    => ['0.9', 'daily'],
        '/gammes'                      => ['0.8', 'weekly'],
        '/blog'                        => ['0.7', 'weekly'],
        '/a-propos'                    => ['0.5', 'monthly'],
        '/contact'                     => ['0.5', 'monthly'],
        '/programme'                   => ['0.4', 'monthly'],
        '/politique-de-confidentialite' => ['0.2', 'yearly'],
    ];

    /**
     * Pages sans intérêt pour un moteur : tunnel d'achat, espace client,
     * administration. Les exclure évite de diluer le budget d'exploration.
     */
    private const DISALLOWED = [
        '/admin', '/api/', '/checkout', '/cart', '/wishlist',
        '/profil', '/orders', '/login', '/register', '/payment/',
        '/auth/', '/reset-password',
    ];

    public function robots(): Response
    {
        $lignes = ['User-agent: *'];

        if ($this->indexable()) {
            foreach (self::DISALLOWED as $chemin) {
                $lignes[] = "Disallow: {$chemin}";
            }
            $lignes[] = '';
            $lignes[] = 'Sitemap: ' . $this->base() . '/sitemap.xml';
        } else {
            // Recette / local : tout bloquer. Sans ça, le contenu dupliqué de
            // la recette concurrence la production sur les mêmes textes.
            $lignes[] = 'Disallow: /';
        }

        return response(implode("\n", $lignes) . "\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $urls = [];

        foreach (self::STATIC_PAGES as $chemin => [$priorite, $frequence]) {
            $urls[] = ['loc' => $this->base() . $chemin, 'priority' => $priorite, 'changefreq' => $frequence];
        }

        // Produits — le cœur du référencement marchand.
        foreach (Product::where('is_active', true)->get(['slug', 'updated_at']) as $produit) {
            $urls[] = [
                'loc'        => $this->base() . '/products/' . $produit->slug,
                'lastmod'    => $produit->updated_at?->toAtomString(),
                'priority'   => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        foreach (Category::where('is_active', true)->get(['slug', 'updated_at']) as $categorie) {
            $urls[] = [
                'loc'        => $this->base() . '/products?category=' . $categorie->slug,
                'lastmod'    => $categorie->updated_at?->toAtomString(),
                'priority'   => '0.6',
                'changefreq' => 'weekly',
            ];
        }

        foreach (ProductLine::where('is_active', true)->get(['slug', 'updated_at']) as $gamme) {
            $urls[] = [
                'loc'        => $this->base() . '/gammes/' . $gamme->slug,
                'lastmod'    => $gamme->updated_at?->toAtomString(),
                'priority'   => '0.7',
                'changefreq' => 'weekly',
            ];
        }

        foreach (Post::where('status', 'published')->get(['slug', 'updated_at']) as $article) {
            $urls[] = [
                'loc'        => $this->base() . '/blog/' . $article->slug,
                'lastmod'    => $article->updated_at?->toAtomString(),
                'priority'   => '0.6',
                'changefreq' => 'monthly',
            ];
        }

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url['loc'], ENT_XML1) . "</loc>\n";
            if (! empty($url['lastmod'])) {
                $xml .= '    <lastmod>' . $url['lastmod'] . "</lastmod>\n";
            }
            $xml .= '    <changefreq>' . $url['changefreq'] . "</changefreq>\n";
            $xml .= '    <priority>' . $url['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }
        $xml .= '</urlset>' . "\n";

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /** Domaine public, sans slash final. */
    private function base(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * Seule la production s'indexe.
     *
     * SEO_INDEXABLE_HOST (le domaine de production de la boutique) est la
     * règle sûre : la recette tourne avec la même image et une configuration
     * très proche, et un APP_ENV oublié à « production » suffirait à la faire
     * indexer. Sans ce réglage, on se rabat sur APP_ENV.
     */
    private function indexable(): bool
    {
        $attendu = strtolower(trim((string) config('app.seo_indexable_host', '')));

        if ($attendu === '') {
            return app()->environment('production');
        }

        $hote = strtolower((string) parse_url($this->base(), PHP_URL_HOST));

        return $hote === $attendu || str_ends_with($hote, '.' . $attendu);
    }
}
