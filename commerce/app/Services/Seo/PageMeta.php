<?php

namespace App\Services\Seo;

use Modules\Blog\Models\Post;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductLine;
use App\Support\Shop;
use Modules\Admin\Models\Setting;

/**
 * Balises SEO d'une URL publique.
 *
 * Le front pose déjà ces balises (composables/useSeo.js), mais en JavaScript,
 * après le montage de Vue. Les robots qui n'exécutent rien — ceux de WhatsApp
 * et de Facebook en tête, sans exception — ne voient donc que le contenu brut
 * de index.html : le même titre et la même description sur les 34 URL du
 * sitemap. Cette classe fournit à SpaController de quoi corriger le tir avant
 * que la page ne parte.
 *
 * Elle ne remplace pas useSeo.js : les deux coexistent, l'un pour les robots,
 * l'autre pour la navigation interne où aucun rechargement n'a lieu.
 */
class PageMeta
{
    /** Visuel de repli, présent dans le front (public/). */
    private const IMAGE_DEFAUT = '/og-default.jpg';

    /**
     * Pages fixes. Volontairement écrites ici plutôt que déduites d'une vue :
     * une description de page d'accueil se relit et se corrige, elle ne se
     * calcule pas.
     */
    // `%s` reçoit le nom de la boutique (réglage shop_name).
    private const PAGES_FIXES = [
        '/products' => [
            'title'       => 'Tous nos produits',
            'description' => "Le catalogue complet de %s : tous nos produits, disponibles à la commande en ligne.",
        ],
        '/gammes' => [
            'title'       => 'Nos gammes',
            'description' => "Des gammes pensées ensemble : chez %s, chaque gamme réunit des produits qui se complètent.",
        ],
        '/blog' => [
            'title'       => 'Actualités et conseils',
            'description' => "Nouveautés, conseils et coulisses de %s.",
        ],
        '/a-propos' => [
            'title'       => 'Notre histoire',
            'description' => "Découvrez l'histoire et les engagements de %s.",
        ],
        '/contact' => [
            'title'       => 'Nous contacter',
            'description' => "Une question sur un produit ou une commande ? L'équipe de %s vous répond.",
        ],
        '/programme' => [
            'title'       => 'Le club fidélité',
            'description' => "Rejoignez le club fidélité de %s : avantages réservés aux membres.",
        ],
    ];

    /**
     * Balises correspondant à un chemin, ou null si la page n'a rien de
     * particulier à dire — la coquille garde alors ses balises d'origine,
     * ce qui vaut mieux qu'un titre inventé.
     *
     * @return array{title: string, description: string, image: ?string, type: string}|null
     */
    public function pour(string $chemin): ?array
    {
        $chemin = '/' . trim((string) parse_url($chemin, PHP_URL_PATH), '/');

        if (preg_match('#^/products/([^/]+)$#', $chemin, $trouve)) {
            return $this->produit($trouve[1]);
        }

        if (preg_match('#^/gammes/([^/]+)$#', $chemin, $trouve)) {
            return $this->gamme($trouve[1]);
        }

        if (preg_match('#^/blog/([^/]+)$#', $chemin, $trouve)) {
            return $this->article($trouve[1]);
        }

        if ($chemin === '/') {
            return $this->accueil();
        }

        $fixe = self::PAGES_FIXES[$chemin] ?? null;

        return $fixe ? $this->composer($fixe['title'], sprintf($fixe['description'], Shop::name())) : null;
    }

    // ── Contenus dynamiques ──────────────────────────────────────────────────

    /**
     * Accueil : titre et description SEO réglés par la boutique, sinon son
     * nom et son slogan. La coquille ne porte que des valeurs neutres.
     */
    private function accueil(): array
    {
        $titre = trim((string) Shop::setting('seo_meta_title', '')) ?: Shop::name();
        $description = trim((string) Shop::setting('seo_meta_description', ''))
            ?: trim((string) Shop::setting('shop_tagline', ''))
            ?: 'Boutique en ligne ' . Shop::name() . ' : commandez et faites-vous livrer.';

        return [
            'title'       => $titre,
            'description' => $this->extrait($description),
            'image'       => $this->absolu(Shop::setting('seo_og_image') ?: self::IMAGE_DEFAUT),
            'type'        => 'website',
        ];
    }

    private function produit(string $slug): ?array
    {
        $produit = Product::with('coverImage')
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $produit) {
            return null;
        }

        return $this->composer(
            $produit->name,
            $produit->short_description ?: $produit->description,
            $produit->coverImage?->url,
            'product',
        );
    }

    private function gamme(string $slug): ?array
    {
        $gamme = ProductLine::where('slug', $slug)->where('is_active', true)->first();

        if (! $gamme) {
            return null;
        }

        return $this->composer(
            $gamme->name,
            $gamme->tagline ?: $gamme->description,
            $this->urlMedia($gamme->cover_image),
        );
    }

    private function article(string $slug): ?array
    {
        $article = Post::where('slug', $slug)->where('status', 'published')->first();

        if (! $article) {
            return null;
        }

        return $this->composer(
            $article->meta_title ?: $article->title,
            $article->meta_description ?: ($article->excerpt ?: $article->content),
            $article->coverUrl ?: $this->urlMedia($article->cover_image),
            'article',
        );
    }

    // ── Fabrication ──────────────────────────────────────────────────────────

    private function composer(string $titre, ?string $description, ?string $image = null, string $type = 'website'): array
    {
        return [
            'title'       => trim($titre) . ' — ' . Shop::name(),
            'description' => $this->extrait($description),
            'image'       => $this->absolu($image ?: (Setting::get('seo_og_image') ?: self::IMAGE_DEFAUT)),
            'type'        => $type,
        ];
    }

    /**
     * Description ramenée à la longueur utile d'un extrait.
     *
     * Les descriptions produit et le corps des articles sont du HTML saisi
     * dans l'administration : les balises doivent tomber, sans quoi un
     * `<p>` se retrouverait dans l'aperçu WhatsApp.
     */
    private function extrait(?string $texte, int $max = 158): string
    {
        $propre = trim(preg_replace('/\s+/', ' ', strip_tags((string) $texte)));

        if ($propre === '' || mb_strlen($propre) <= $max) {
            return $propre;
        }

        // Coupe au dernier mot entier, pour ne pas trancher au milieu d'un nom.
        $coupe = mb_substr($propre, 0, $max - 1);

        return preg_replace('/[\s,;:.!-]+\S*$/u', '', $coupe) . '…';
    }

    private function urlMedia(?string $chemin): ?string
    {
        if (! $chemin) {
            return null;
        }

        return str_starts_with($chemin, 'http') ? $chemin : asset('storage/' . $chemin);
    }

    /** URL absolue : un `og:image` relatif n'est suivi par aucun robot social. */
    private function absolu(string $url): string
    {
        return str_starts_with($url, 'http') ? $url : url($url);
    }
}
