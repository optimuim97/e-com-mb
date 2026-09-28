<?php

namespace Modules\Order\Services;

use App\Support\ExportStamp;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Admin\Models\Setting;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Models\Order;
use Modules\Order\Support\AbidjanZone;
use App\Support\Shop;

class InvoiceService
{
    /**
     * Generate the PDF for an order and return the DomPDF instance.
     */
    /**
     * Largeur de la feuille en texte brut, en caractères.
     *
     * 48 = largeur native d'une imprimante thermique 80 mm. Au-delà, elle
     * réduit la police pour faire tenir la ligne, et le tirage devient flou.
     */
    private const LARGEUR_TXT = 48;

    public function generate(Order $order): \Barryvdh\DomPDF\PDF
    {
        $order->loadMissing(['items', 'items.product', 'payments', 'user']);

        $pdf = Pdf::loadView('order::invoice', [
            'order'      => $order,
            'shop'       => $this->shopSettings(),
            'logoBase64' => $this->logoBase64(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Return the suggested file name for the invoice.
     */
    public function filename(Order $order): string
    {
        return 'facture-' . $order->number . '.pdf';
    }

    /** Libellés de statut — conservés pour l'en-tête « filtre appliqué ». */
    private const STATUS_LABELS = [
        'pending'    => 'En attente',
        'confirmed'  => 'Confirmée',
        'processing' => 'En cours',
        'shipped'    => 'Expédiée',
        'delivered'  => 'Livrée',
        'cancelled'  => 'Annulée',
        'refunded'   => 'Remboursée',
    ];

    /**
     * Génère le PDF "Livraisons par zone".
     * Si `order_ids` est fourni, seules ces commandes sont incluses (export d'un groupe).
     * Sinon toutes les commandes (avec filtres date/status) sont incluses.
     *
     * @param array{date_from:?string, date_to:?string, status:?string, order_ids:?int[], label:?string, show_product_totals:?bool, show_item_counts:?bool} $filters
     */
    public function generateByZoneExport(array $filters = []): \Barryvdh\DomPDF\PDF
    {
        $pdf = Pdf::loadView('order::delivery-zones-export', $this->buildZoneExportData($filters) + [
            'filters'      => $filters,
            'exportLabel'  => $filters['label'] ?? null,
            'statusLabels' => self::STATUS_LABELS,
            'shop'         => $this->shopSettings(),
            'logoBase64'   => $this->logoBase64(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Feuille de livraison en texte brut : même jeu de données que le PDF,
     * mais imprimable partout (bloc-notes, imprimante thermique) et surtout
     * annotable à la main — chaque commande porte une case « [ ] LIVRÉ » que
     * le livreur coche sur le terrain.
     *
     * `round` estampille la feuille au code de la tournée : c'est ce qui
     * permet, au retour du livreur, de rattacher le papier annoté à la sortie
     * enregistrée. Sans lui, la feuille est anonyme et le pointage se fait de
     * mémoire.
     *
     * @param array{date_from:?string, date_to:?string, status:?string, order_ids:?int[], label:?string, show_product_totals:?bool, show_item_counts:?bool, round:?array{code:string, courier:?string, expected_total:?float, count:?int, items:?int}} $filters
     */
    public function generateByZoneTxt(array $filters = []): string
    {
        return $this->renderZoneTxt(
            $this->buildZoneExportData($filters),
            $this->shopSettings(),
            $filters['label'] ?? null,
            $filters['round'] ?? null,
        );
    }

    /**
     * Mise en page de la feuille texte. Séparée de la collecte des données pour
     * rester vérifiable sans base : elle ne dépend que du tableau reçu.
     *
     * Réduite à ce que le livreur lit sur le terrain : lieu, téléphone,
     * produits, montant. Les cumuls par zone, le récapitulatif par produit et
     * le pied de page ont été retirés à la demande ; ils restent dans l'export
     * PDF, qui sert au suivi en boutique.
     */
    /**
     * Feuille de livraison en texte brut.
     *
     * Largeur bloquée à 48 caractères : c'est ce qu'une imprimante thermique
     * 80 mm imprime à sa taille native. Au-delà, elle réduit la police pour
     * faire tenir la ligne — et le tirage devient flou. La feuille tenait
     * auparavant sur 72 colonnes.
     *
     * Les filets pleins ont disparu : une ligne de soixante-douze « = » coûte
     * cher en encre et en temps sur une thermique, sans rien apprendre au
     * livreur. Les livraisons se distinguent par leur numéro d'ordre, un
     * retrait et une ligne vide — ce qu'il cherche vraiment, c'est « où j'en
     * suis », et un numéro le lui dit mieux qu'un trait.
     */
    private function renderZoneTxt(array $d, array $shop, ?string $label, ?array $round = null): string
    {
        $out = [];

        $out[] = mb_strtoupper($shop['name']);
        $out[] = 'FEUILLE DE LIVRAISON' . ($label ? ' - ' . $label : '');

        // En-tête de tournée. Le code d'abord : c'est lui qu'on se dit au
        // téléphone en cours de route, et lui qu'on cherche au retour.
        if ($round) {
            $out[] = 'Tournee : ' . $round['code'];
            if (! empty($round['courier'])) {
                $out[] = 'Livreur : ' . $round['courier'];
            }
            // Le seul cumul conservé : ce que le livreur doit rapporter. Les
            // commandes déjà réglées comptent pour zéro, il n'encaisse rien
            // à la porte.
            if (isset($round['expected_total'])) {
                $out[] = 'A encaisser : ' . $this->xof((float) $round['expected_total'])
                       . (isset($round['count']) ? ' (' . $round['count'] . ' livr.)' : '');
            }
            // Ce qu'il emporte : c'est ce que la boutique compte au chargement,
            // et ce que le livreur vérifie avant de démarrer.
            if (isset($round['items'])) {
                $out[] = 'A emporter : ' . $round['items'] . ' article' . ($round['items'] > 1 ? 's' : '')
                       . (isset($round['count']) ? ', ' . $round['count'] . ' colis' : '');
            }
        }

        $out[] = 'Genere le ' . now()->format('d/m/Y H:i');
        $out[] = $this->filet();

        $numero = 0;

        foreach ($d['groups'] as $group) {
            $out[] = '';
            $out[] = 'ZONE : ' . mb_strtoupper($group['zone'])
                   . ($group['group'] !== '—' ? ' (' . $group['group'] . ')' : '');
            $out[] = '';

            foreach ($group['orders'] as $row) {
                $numero++;

                // Le numéro d'ordre remplace le filet : il situe la livraison
                // dans la tournée, ce qu'un trait ne fait pas.
                $entete = '#' . $numero . '  ' . (mb_strtoupper($row['locality']) ?: '-');

                // Numéro de commande cadré à droite. C'est la seule référence
                // qui relie la feuille papier à l'administration : sans lui, un
                // litige au retour — colis refusé, montant contesté — obligeait
                // à retrouver la commande par l'adresse et le téléphone.
                // La localité cède la place s'il le faut : elle est reprise par
                // l'adresse en dessous, le numéro ne l'est nulle part.
                $reference = (string) ($row['number'] ?? '');

                $out[] = $reference === ''
                    ? $entete
                    : $this->ligne(
                        $this->couper($entete, self::LARGEUR_TXT - mb_strlen($reference) - 2),
                        $reference,
                    );

                if ($row['address'])  { $out[] = '    ' . $row['address']; }
                if ($row['landmark']) { $out[] = '    Repere : ' . $row['landmark']; }

                // Le nom précède le téléphone : c'est ce que le livreur annonce
                // en arrivant, et ce qu'il demande à la porte. Il avait été
                // retiré de la feuille — elle passe de main en main et traîne
                // en véhicule — mais l'absence coûtait plus cher que la
                // discrétion : sans nom, impossible de savoir à qui remettre
                // le colis quand plusieurs foyers partagent une cour.
                if ($row['name']) { $out[] = '    ' . $this->couper($row['name'], self::LARGEUR_TXT - 4); }

                $out[] = '    Tel : ' . $row['phone'];

                foreach ($row['items'] as $item) {
                    $nom = $item['qty'] . ' x ' . $item['name']
                         . ($item['variant'] ? ' (' . $item['variant'] . ')' : '');
                    $montant = $this->xof($item['line_total']);

                    // Sur 48 colonnes, un nom long pousserait le montant à la
                    // ligne suivante et casserait la colonne. On coupe le nom :
                    // le livreur reconnaît le produit, il ne le facture pas.
                    // Deux espaces au minimum : collé au montant, le nom du produit
                    // et le prix se lisent comme un seul mot.
                    $largeurNom = self::LARGEUR_TXT - 4 - mb_strlen($montant) - 2;
                    $out[] = $this->ligne('    ' . $this->couper($nom, $largeurNom), $montant, self::LARGEUR_TXT);
                }

                if (empty($row['items'])) {
                    $out[] = '    -';
                }

                $out[] = '    MONTANT : ' . $this->xof($row['total']);

                // Détail abrégé : sur 48 colonnes, « articles » et « livraison »
                // en toutes lettres ne tiennent plus avec une remise.
                $detail = 'art ' . $this->xof($row['subtotal']);
                if ($row['discount'] > 0) {
                    $detail .= ' | rem -' . $this->xof($row['discount']);
                }
                $detail .= ' | liv ' . $this->xof($row['shipping']);
                $out[] = '    (' . $detail . ')';

                // Case à cocher : c'est la raison d'être de cette sortie papier
                // — le livreur pointe au fur et à mesure, et la feuille annotée
                // sert de preuve au retour.
                // $out[] = '    [ ] LIVRE';
                $out[] = '';
            }
        }

        $out[] = $this->filet();

        // BOM UTF-8 : sans lui, le Bloc-notes Windows casse les accents.
        return "\u{FEFF}" . $this->policeBasique(implode("\r\n", $out));
    }

    /** Unique trait de la feuille : il sépare l'en-tête du corps. */
    private function filet(): string
    {
        return str_repeat('-', self::LARGEUR_TXT);
    }

    /** Coupe sans ajouter de caractère : le point de suspension coûte une colonne. */
    private function couper(string $texte, int $largeur): string
    {
        return mb_strlen($texte) > $largeur ? mb_substr($texte, 0, $largeur) : $texte;
    }

    /**
     * Construit le jeu de données partagé par le PDF et le TXT : commandes
     * groupées par zone, cumuls (montant, livraisons, articles) et récap
     * produits. Tout est pré-calculé ici pour que les deux sorties affichent
     * exactement les mêmes chiffres.
     */
    private function buildZoneExportData(array $filters): array
    {
        $showProductTotals = (bool) ($filters['show_product_totals'] ?? true);
        $showItemCounts    = (bool) ($filters['show_item_counts']    ?? true);

        $orders = Order::with(['items', 'user'])
            ->when(!empty($filters['order_ids']),  fn($q) => $q->whereIn('id', $filters['order_ids']))
            ->when(empty($filters['order_ids']) && !empty($filters['status']),
                fn($q) => $q->where('status', $filters['status']))
            ->when(empty($filters['order_ids']) && !empty($filters['date_from']),
                fn($q) => $q->whereDate('created_at', '>=', $filters['date_from']))
            ->when(empty($filters['order_ids']) && !empty($filters['date_to']),
                fn($q) => $q->whereDate('created_at', '<=', $filters['date_to']))
            ->when(empty($filters['order_ids']), fn($q) => $q->whereNotIn('status', ['cancelled']))
            ->latest()
            ->get()
            // Hors Abidjan, on n'expédie que ce qui est déjà payé : le bon
            // d'expédition écarte donc les impayées de l'intérieur.
            //
            // `include_unpaid` lève la règle. L'écran des expéditions en a
            // besoin : sa raison d'être est justement de travailler ces
            // commandes-là, et une feuille vide n'aiderait personne. Le
            // parcours des tournées, lui, garde le garde-fou — c'est là qu'un
            // colis part réellement.
            ->reject(fn(Order $order) =>
                ! ($filters['include_unpaid'] ?? false)
                && is_null($order->paid_at)
                && ! AbidjanZone::isAbidjan($order->shipping_address)
            )
            ->values();

        // Grouper par zone de livraison résolue
        $groups        = [];
        $productTotals = [];

        foreach ($orders as $order) {
            $addr    = $order->shipping_address ?? [];
            $city    = $addr['city']    ?? '';
            $commune = $addr['commune'] ?? null;
            $country = $addr['country'] ?? 'CI';

            $zone = null;
            if ($city || $commune) {
                $zone = DeliveryZone::quote($city, $country, null, $commune);
            }

            $key   = $zone['zone_name']  ?? '__off_zone__';
            $label = $zone['zone_name']  ?? 'Hors zone';
            $group = $zone['group']      ?? '—';

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'zone'        => $label,
                    'group'       => $group,
                    'count'       => 0,
                    'total'       => 0.0,
                    'shipping'    => 0.0,
                    'items_count' => 0,
                    'orders'      => [],
                ];
            }

            // Lignes d'articles + cumul du récap produits (toutes zones confondues)
            $items      = [];
            $itemsCount = 0;
            foreach ($order->items as $item) {
                $qty       = (int) $item->quantity;
                $lineTotal = (float) $item->total_price ?: (float) $item->unit_price * $qty;
                $itemsCount += $qty;

                $items[] = [
                    'name'       => $item->product_name,
                    'variant'    => $item->variant_label,
                    'qty'        => $qty,
                    'unit_price' => (float) $item->unit_price,
                    'line_total' => $lineTotal,
                ];

                $pk = $item->product_name . ($item->variant_label ? ' (' . $item->variant_label . ')' : '');
                if (!isset($productTotals[$pk])) {
                    $productTotals[$pk] = ['name' => $pk, 'qty' => 0, 'total' => 0.0];
                }
                $productTotals[$pk]['qty']   += $qty;
                $productTotals[$pk]['total'] += $lineTotal;
            }

            $montant  = $this->montantCommande($order, $items);
            $shipping = (float) $order->shipping_cost;

            $groups[$key]['count']++;
            $groups[$key]['total']       += $montant;
            $groups[$key]['shipping']    += $shipping;
            $groups[$key]['items_count'] += $itemsCount;
            $groups[$key]['orders'][] = [
                'number'      => $order->number,
                'date'        => $order->created_at->format('d/m/Y'),
                'phone'       => $addr['phone'] ?? '—',
                // Le nom du compte prime sur celui de l'adresse. Il avait été
                // retiré d'ici pour ne pas promener d'identité sur une feuille
                // qui traîne en véhicule ; la boutique le redemande, parce que
                // c'est par le nom qu'on annonce la livraison au téléphone.
                // Le gabarit PDF, lui, ne l'avait jamais cessé de l'attendre :
                // l'export par zone échouait donc en « Undefined array key ».
                'name'        => $order->user?->name
                    ?: trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')),
                'address'     => $addr['address_line1'] ?? '',
                'locality'    => $commune ?: $city,
                'landmark'    => $addr['landmark'] ?? '',
                'note'        => $order->customer_note,
                'items'       => $items,
                'items_count' => $itemsCount,
                'subtotal'    => (float) $order->subtotal ?: array_sum(array_column($items, 'line_total')),
                'shipping'    => $shipping,
                'discount'    => (float) $order->discount_amount,
                'total'       => $montant,
                'is_paid'     => (bool) $order->paid_at,
            ];
        }

        // "Hors zone" toujours en dernier
        $list = array_values($groups);
        usort($list, function ($a, $b) {
            if ($a['zone'] === 'Hors zone') return 1;
            if ($b['zone'] === 'Hors zone') return -1;
            return strcmp($a['group'] . $a['zone'], $b['group'] . $b['zone']);
        });

        // Récap produits : le plus vendu en premier
        $productTotals = array_values($productTotals);
        usort($productTotals, fn($a, $b) => $b['total'] <=> $a['total']);

        return [
            'groups'            => $list,
            'productTotals'     => $productTotals,
            'totalZones'        => count($list),
            'totalOrders'       => array_sum(array_column($list, 'count')),
            'totalRevenue'      => array_sum(array_column($list, 'total')),
            'totalShipping'     => array_sum(array_column($list, 'shipping')),
            'totalItems'        => array_sum(array_column($list, 'items_count')),
            'showProductTotals' => $showProductTotals,
            'showItemCounts'    => $showItemCounts,
        ];
    }

    /**
     * Montant à encaisser, reconstitué si nécessaire.
     *
     * `orders.total` peut être vide ou à zéro (commande créée avant le calcul
     * des frais de zone, import, total jamais recalculé). Une feuille de
     * livraison qui affiche « 0 XOF » fait perdre de l'argent au livreur : on
     * recalcule donc à partir des lignes plutôt que d'afficher zéro.
     *
     * @param array<int, array{line_total: float}> $items
     */
    private function montantCommande(Order $order, array $items): float
    {
        $total = (float) $order->total;
        if ($total > 0) {
            return $total;
        }

        $lignes   = array_sum(array_column($items, 'line_total'));
        $subtotal = (float) $order->subtotal ?: $lignes;

        return max(0.0, $subtotal - (float) $order->discount_amount + (float) $order->shipping_cost);
    }

    /** Montant formaté pour les sorties texte (le PDF a son propre formatage). */
    private function xof(float $montant): string
    {
        return number_format($montant, 0, ',', ' ') . ' XOF';
    }

    /**
     * Ramène le texte au jeu d'une police monospace basique.
     *
     * La feuille est lue au Bloc-notes et sortie sur imprimante thermique.
     * Ces polices n'ont pas le tiret cadratin, le point médian ni l'apostrophe
     * typographique : le caractère manquant est emprunté à une police de
     * repli, proportionnelle, et la colonne des montants se décale à partir
     * de là.
     *
     * Passe sur la feuille assemblée, et pas seulement sur nos libellés : les
     * noms de produits et les points de repère sont saisis à la main, souvent
     * collés depuis Word, et arrivent avec leurs apostrophes courbes.
     *
     * Les accents des mots français sont conservés — le BOM les fait passer,
     * et « TEL » ou « Genere » sur une feuille remise à un livreur ferait
     * négligé.
     *
     * Chaque correspondance va d'un caractère vers un caractère : la
     * substitution ne peut donc pas déplacer une colonne déjà calée.
     */
    private function policeBasique(string $texte): string
    {
        return strtr($texte, [
            '—' => '-',   // tiret cadratin
            '–' => '-',   // tiret demi-cadratin
            '−' => '-',   // signe moins
            '·' => '-',   // point médian
            '•' => '*',   // puce
            '×' => 'x',   // signe multiplié
            '’' => "'",   // apostrophe typographique
            '‘' => "'",
            '“' => '"',
            '”' => '"',
            '«' => '"',
            '»' => '"',
            '…' => '.',   // points de suspension
            "\u{00A0}" => ' ',   // espace insécable
            "\u{202F}" => ' ',   // espace fine insécable
            "\u{2007}" => ' ',   // espace chiffre
        ]);
    }

    /** Ligne à deux colonnes : libellé à gauche, montant aligné à droite. */
    private function ligne(string $gauche, string $droite, int $largeur = self::LARGEUR_TXT): string
    {
        $espaces = max(1, $largeur - mb_strlen($gauche) - mb_strlen($droite));

        return $gauche . str_repeat(' ', $espaces) . $droite;
    }

    public function filenameByZone(): string
    {
        return 'livraisons-par-zone-' . ExportStamp::now() . '.pdf';
    }

    public function filenameByZoneTxt(): string
    {
        return 'livraisons-par-zone-' . ExportStamp::now() . '.txt';
    }

    /**
     * Feuille de tournée : le fichier porte le code, comme la feuille imprimée.
     * Retrouver le bon fichier dans un dossier de téléchargements se fait ainsi
     * sans l'ouvrir.
     */
    public function filenameByRoundTxt(string $code): string
    {
        return 'tournee-' . strtolower(preg_replace('/[^A-Za-z0-9\-]+/', '-', $code)) . '.txt';
    }

    /** Charge les infos boutique depuis les settings paramétrables. */
    private function shopSettings(): array
    {
        return [
            'name'    => Shop::name(),
            'tagline' => Setting::get('shop_tagline', ''),
            'email'   => Shop::email(),
            'phone'   => Setting::get('shop_phone',   ''),
            'address' => Setting::get('shop_address', ''),
            'url'     => config('app.url'),
        ];
    }

    /**
     * Retourne le logo encodé en base64 (pour DomPDF sans dépendance réseau).
     * Priorité :
     *   1. shop_logo_url → fichier local (même domaine)
     *   2. shop_logo_url → URL externe, mis en cache dans storage/app/
     *   3. public/logos/logo.png (logo déposé avec l'instance)
     *
     * Sans logo, la facture affiche le nom de la boutique : jamais le logo
     * d'une autre marque.
     */
    private function logoBase64(): ?string
    {
        $logoUrl = Setting::get('shop_logo_url', '');

        // 1. URL locale (même domaine) → lecture directe
        if ($logoUrl) {
            $appUrl = rtrim(config('app.url'), '/');
            if (str_starts_with($logoUrl, $appUrl)) {
                $path = public_path(ltrim(parse_url($logoUrl, PHP_URL_PATH), '/'));
                if (file_exists($path)) {
                    return base64_encode(file_get_contents($path));
                }
            }
        }

        // 2. URL externe → fetch + cache
        if (filter_var($logoUrl, FILTER_VALIDATE_URL) && !str_starts_with($logoUrl, config('app.url'))) {
            $b64 = $this->fetchExternalLogoBase64($logoUrl);
            if ($b64) return $b64;
        }

        // 3. Fichier local de secours
        foreach (['logos/logo.png'] as $rel) {
            $path = public_path($rel);
            if (file_exists($path)) {
                return base64_encode(file_get_contents($path));
            }
        }

        return null;
    }

    /**
     * Télécharge une image externe et la met en cache dans storage/app/.
     * Retourne le contenu en base64 ou null si le fetch échoue.
     */
    private function fetchExternalLogoBase64(string $url): ?string
    {
        // Un fichier par URL : changer de logo ne ressert pas l'ancien.
        $cachePath = storage_path('app/logo-pdf-' . md5($url) . '.cache');

        // Utiliser le cache si présent (valide 30 jours)
        if (file_exists($cachePath) && (time() - filemtime($cachePath)) < 86400 * 30) {
            return base64_encode(file_get_contents($cachePath));
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout'    => 8,
                'user_agent' => 'Mozilla/5.0 (compatible; ShopInvoice/1.0)',
            ],
            'ssl'  => ['verify_peer' => false],
        ]);

        $data = @file_get_contents($url, false, $ctx);

        if ($data !== false && strlen($data) > 100) {
            @file_put_contents($cachePath, $data);
            return base64_encode($data);
        }

        // Cache corrompu / expiré mais fetch échoué → retenter avec l'ancien cache
        if (file_exists($cachePath)) {
            return base64_encode(file_get_contents($cachePath));
        }

        return null;
    }
}
