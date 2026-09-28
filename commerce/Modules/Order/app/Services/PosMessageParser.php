<?php

namespace Modules\Order\Services;

use Modules\Catalog\Models\Product;
use Modules\Promo\Support\SellingPrice;

/**
 * Extrait les éléments d'une commande à partir d'un message libre du client
 * (ex. WhatsApp : "Bonjour, je veux 2 eau de rose et une huile pour Awa 0707080910").
 *
 * Implémentation actuelle : parseur interne (gratuit, hors-ligne) basé sur la
 * recherche floue des noms de produits du catalogue + détection des quantités
 * et du téléphone.
 *
 * Hook Claude : si `services.pos.use_claude` est activé et qu'une clé API est
 * configurée, parseWithClaude() pourra prendre le relais (branchement laissé
 * prêt, voir parse()).
 */
class PosMessageParser
{
    public function parse(string $message): array
    {
        // Branche Claude (désactivée par défaut). Quand tu fourniras une clé :
        //   config/services.php → 'pos' => ['use_claude' => env('POS_USE_CLAUDE', false)]
        if (config('services.pos.use_claude') && config('services.anthropic.api_key')) {
            try {
                return $this->parseWithClaude($message);
            } catch (\Throwable $e) {
                \Log::warning('[POS] Claude parse failed, fallback interne: ' . $e->getMessage());
            }
        }

        return $this->parseInternal($message);
    }

    // ── Parseur interne ────────────────────────────────────────────────────────

    private function parseInternal(string $message): array
    {
        $norm = $this->normalize($message);

        // Les colonnes de vente flash font partie du prix effectif : les
        // omettre ferait proposer le tarif plein d'un article soldé.
        $products = Product::where('is_active', true)
            ->get(['id', 'name', 'price', 'sku', 'is_flash_sale', 'flash_price', 'flash_starts_at', 'flash_ends_at']);

        $matched   = [];
        $usedSpans = []; // pour éviter de matcher deux fois le même bout de texte

        foreach ($products as $product) {
            $needle = $this->normalize($product->name);
            if ($needle === '') continue;

            $pos = strpos($norm, $needle);
            if ($pos === false) {
                // Essai par mots significatifs (≥4 lettres) : au moins 60 % présents
                if (! $this->fuzzyContains($norm, $needle)) continue;
                $pos = $this->approxPosition($norm, $needle);
            }

            $qty = $this->extractQuantityNear($norm, $pos, $needle);

            $matched[] = [
                'product_id' => $product->id,
                'name'       => $product->name,
                'sku'        => $product->sku,
                // Prix effectif : l'agente doit lire à l'écran le montant qui
                // sera réellement enregistré à la validation.
                'price'      => SellingPrice::for($product),
                'quantity'   => $qty,
            ];
        }

        return [
            'customer' => [
                'name'  => $this->extractName($message),
                'phone' => $this->extractPhone($message),
            ],
            'items'  => $matched,
            'raw'    => $message,
            'engine' => 'internal',
        ];
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        // retire les accents
        $s = preg_replace('/\p{Mn}/u', '', \Normalizer::isNormalized($s) ? $s : \Normalizer::normalize($s, \Normalizer::FORM_D));
        // ne garde que lettres/chiffres/espaces
        $s = preg_replace('/[^a-z0-9 ]+/u', ' ', $s);
        return preg_replace('/\s+/', ' ', $s);
    }

    /** Au moins 60 % des mots significatifs du nom produit présents dans le message. */
    private function fuzzyContains(string $haystack, string $needle): bool
    {
        $words = array_filter(explode(' ', $needle), fn($w) => mb_strlen($w) >= 4);
        if (empty($words)) return false;
        $hits = 0;
        foreach ($words as $w) {
            if (str_contains($haystack, $w)) $hits++;
        }
        return $hits / count($words) >= 0.6;
    }

    private function approxPosition(string $haystack, string $needle): int
    {
        foreach (explode(' ', $needle) as $w) {
            if (mb_strlen($w) >= 4 && ($p = strpos($haystack, $w)) !== false) return $p;
        }
        return 0;
    }

    /** Cherche un nombre juste avant/après le nom du produit. Défaut 1. */
    private function extractQuantityNear(string $norm, int $pos, string $needle): int
    {
        $before = substr($norm, max(0, $pos - 12), min(12, $pos));
        // "...2 " ou "...x2 "
        if (preg_match('/(\d{1,3})\s*x?\s*$/', $before, $m)) {
            return max(1, (int) $m[1]);
        }
        $after = substr($norm, $pos + strlen($needle), 8);
        if (preg_match('/^\s*x?\s*(\d{1,3})/', $after, $m)) {
            return max(1, (int) $m[1]);
        }
        // mots-quantité courants
        if (preg_match('/\b(deux|trois|quatre|cinq)\b\s*$/', $before, $m)) {
            return ['deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5][$m[1]];
        }
        return 1;
    }

    /** Détecte un numéro ivoirien (8 à 10 chiffres, +225 optionnel). */
    private function extractPhone(string $message): ?string
    {
        if (preg_match('/(\+?225)?\s*((?:\d[\s.\-]?){8,10})/', $message, $m)) {
            $digits = preg_replace('/\D+/', '', $m[2]);
            if (strlen($digits) >= 8) return $digits;
        }
        return null;
    }

    /** Heuristique : "pour <Nom>" / "client <Nom>" / "nom <Nom>". */
    private function extractName(string $message): ?string
    {
        if (preg_match('/\b(?:pour|client(?:e)?|nom)\s*:?\s*([A-ZÀ-Ý][\p{L}\'\-]+(?:\s+[A-ZÀ-Ý][\p{L}\'\-]+)?)/u', $message, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    // ── Branche Claude (placeholder prêt) ───────────────────────────────────────

    private function parseWithClaude(string $message): array
    {
        // À implémenter quand une clé Anthropic sera fournie : appel à l'API
        // Messages avec un prompt qui demande un JSON {customer, items[]}.
        // Pour l'instant on retombe sur l'interne.
        return $this->parseInternal($message);
    }
}
