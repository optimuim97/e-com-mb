<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Support\HomeStatsCache;
use Modules\Delivery\Database\Seeders\DeliveryZoneSeeder;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\DeliveryRoundOrder;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * Jeu de données de démonstration : commandes et tournées.
 *
 * De quoi exercer chaque écran sans attendre de vraies ventes : le tableau des
 * commandes et ses filtres de date, l'écran des expéditions, les extractions,
 * et les tournées dans tous leurs états — préparée, partie, pointée, clôturée
 * avec un écart de caisse.
 *
 * Lancement : `php artisan shop:demo-data` (voir DemoDataCommand).
 *
 * Trois garde-fous :
 *   · refusé en production — ces commandes fausseraient le chiffre d'affaires,
 *     les quotas de la semaine et le best-seller de l'accueil ;
 *   · tout est reconnaissable : numéros `ORD-DEMO-…`, adresses en
 *     `@demo.boutique.test` (le TLD `.test` est réservé, aucun courriel ne
 *     peut partir vers une vraie personne), tournées annotées ;
 *   · relancer remplace le jeu précédent au lieu de l'empiler, et `--purge`
 *     le retire sans toucher au reste.
 *
 * Les données sont écrites directement, sans passer par les services de
 * commande : ceux-ci préviennent les clientes, décrémentent le stock et
 * notifient la boutique, et rien de tout cela ne doit se produire pour une
 * commande fictive.
 */
class DemoOrdersSeeder extends Seeder
{
    public const NUMBER_PREFIX  = 'ORD-DEMO-';
    public const EMAIL_DOMAIN   = 'demo.boutique.test';
    public const ROUND_MARKER   = '[Démo] Jeu de données de démonstration';
    public const COURIER_PASSWORD  = 'livreur-demo';
    public const CUSTOMER_PASSWORD = 'demo1234';

    /** Graine fixe : le même jeu d'une exécution à l'autre, à la date près. */
    private const SEED = 2026;

    /** @var array<string, int> */
    private array $bilan = [];

    /**
     * Clientes : ville, commune, et si elles ont un compte. Les autres commandent
     * sans compte, comme la plupart des commandes rapides.
     *
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: bool}>
     *      prénom, nom, ville, commune, pays, compte
     */
    private const CUSTOMERS = [
        ['Awa',       'Koné',       'Abidjan', 'Cocody',      'CI', true],
        ['Fatou',     'Konaté',     'Abidjan', 'Yopougon',    'CI', true],
        ['Aminata',   'Traoré',     'Abidjan', 'Marcory',     'CI', false],
        ['Adjoua',    'Kouamé',     'Abidjan', 'Abobo',       'CI', false],
        ['Mariam',    'Cissé',      'Abidjan', 'Treichville', 'CI', true],
        ['Nadège',    'Assi',       'Abidjan', 'Plateau',     'CI', false],
        ['Salimata',  'Diarra',     'Abidjan', 'Bingerville', 'CI', false],
        ['Chantal',   "N'Guessan",  'Abidjan', 'Riviera',     'CI', true],
        ['Grâce',     'Ouattara',   'Abidjan', 'Koumassi',    'CI', false],
        ['Estelle',   'Yao',        'Abidjan', 'Angré',       'CI', false],
        ['Kadi',      'Bamba',      'Abidjan', 'Port-Bouët',  'CI', true],
        ['Rokia',     'Sanogo',     'Abidjan', 'Adjamé',      'CI', false],  // commune sans zone : tarif de base
        ['Prisca',    'Kacou',      'Bouaké',  'Bouaké',      'CI', true],
        ['Linda',     'Gnahoré',    'Yamoussoukro', 'Yamoussoukro', 'CI', false],
        ['Sita',      'Coulibaly',  'Korhogo', 'Korhogo',     'CI', false],
        ['Inès',      'Zadi',       'San Pedro', 'San Pedro', 'CI', true],
        ['Djeneba',   'Fofana',     'Bondoukou', 'Bondoukou', 'CI', false],  // ville sans zone : à tarifer
        ['Clarisse',  'Bléhoué',    'Paris',   'Paris',       'FR', true],
        ['Oumou',     'Sow',        'Dakar',   'Dakar',       'SN', false],
    ];

    private const QUARTIERS = [
        'Rue des Jardins', 'Cité des Arts', 'Carrefour Siporex', 'Rue du Commerce',
        'Résidence Les Palmiers', 'Boulevard de Marseille', 'Cité SIR', 'Rue Lepic',
        'Avenue 16', 'Quartier Belle-Ville', 'Cité Verte', 'Lot 42, îlot 7',
    ];

    private const REPERES = [
        'Derrière la pharmacie', 'Face à la station Total', 'Portail bleu',
        "À côté de l'école primaire", 'Immeuble jaune, 2e étage', null, null, null,
    ];

    private const COURIERS = [
        ['Koffi Yao',     'demo_kyao',    '0700000101'],
        ['Moussa Diallo', 'demo_mdiallo', '0700000102'],
        ['Serge Aka',     'demo_saka',    '0700000103'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Refusé en production : ce jeu de données fausserait les ventes réelles.');

            return;
        }

        $this->prerequis();

        mt_srand(self::SEED);

        // Sans journal : des centaines d'entrées « Automatique » noieraient
        // les vraies actions dans le journal de l'équipe.
        activity()->withoutLogs(function () {
            DB::transaction(function () {
                $this->bilan = ['purge' => array_sum(self::purge())];

                $livreurs = $this->livreurs();
                $clientes = $this->clientes();
                $commandes = $this->commandes($clientes);

                $this->tournees($commandes, $livreurs);
            });
        });

        HomeStatsCache::forget();
    }

    /** Ce qui a été créé, pour le compte rendu de la commande artisan. */
    public function bilan(): array
    {
        return $this->bilan;
    }

    // ── Purge ──────────────────────────────────────────────────────────────

    /**
     * Retire le jeu de démonstration, et lui seul.
     *
     * Les tournées d'abord : leurs lignes retiennent les commandes. Une tournée
     * n'est effacée que si elle porte le marqueur — une tournée réelle dans
     * laquelle quelqu'un aurait glissé une commande de démonstration garde ses
     * autres lignes.
     *
     * @return array{rounds: int, lines: int, orders: int, users: int}
     */
    public static function purge(): array
    {
        $commandes = Order::withTrashed()->where('number', 'like', self::NUMBER_PREFIX . '%')->pluck('id');
        $tournees  = DeliveryRound::where('notes', self::ROUND_MARKER)->pluck('id');

        $lignes = DeliveryRoundOrder::query()
            ->where(fn ($q) => $q->whereIn('delivery_round_id', $tournees)->orWhereIn('order_id', $commandes))
            ->delete();

        $nbTournees = DeliveryRound::whereIn('id', $tournees)->delete();

        OrderItem::whereIn('order_id', $commandes)->delete();
        DB::table('payments')->whereIn('order_id', $commandes)->delete();
        Activity::where('subject_type', Order::class)->whereIn('subject_id', $commandes)->delete();
        $nbCommandes = Order::withTrashed()->whereIn('id', $commandes)->forceDelete();

        $comptes = User::where('email', 'like', '%@' . self::EMAIL_DOMAIN)->pluck('id');
        $morph   = (new User)->getMorphClass();
        DB::table('personal_access_tokens')->where('tokenable_type', $morph)->whereIn('tokenable_id', $comptes)->delete();
        // Les rôles sont rattachés sans clé étrangère : ils survivraient au compte.
        DB::table(config('permission.table_names.model_has_roles'))->where('model_type', $morph)->whereIn('model_id', $comptes)->delete();
        $nbComptes = User::whereIn('id', $comptes)->delete();

        return ['rounds' => $nbTournees, 'lines' => $lignes, 'orders' => $nbCommandes, 'users' => $nbComptes];
    }

    // ── Prérequis ──────────────────────────────────────────────────────────

    /** Rôles, zones et catalogue : posés s'ils manquent, jamais écrasés. */
    private function prerequis(): void
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'livreur', 'guard_name' => 'web']);

        if (DeliveryZone::count() === 0) {
            $this->call(DeliveryZoneSeeder::class);
        }

        if (Product::where('is_active', true)->where('price', '>', 0)->count() === 0) {
            $this->call(DemoCatalogSeeder::class);
        }
    }

    // ── Comptes ────────────────────────────────────────────────────────────

    /** @return Collection<int, User> */
    private function livreurs(): Collection
    {
        return collect(self::COURIERS)->map(function (array $l) {
            [$nom, $identifiant, $telephone] = $l;

            $user = User::create([
                'name'     => $nom,
                'username' => $identifiant,
                'email'    => $identifiant . '@' . self::EMAIL_DOMAIN,
                'phone'    => $telephone,
                'password' => Hash::make(self::COURIER_PASSWORD),
            ]);
            $user->syncRoles(['livreur']);

            return $user;
        })->tap(fn ($c) => $this->bilan['couriers'] = $c->count());
    }

    /** @return Collection<int, array<string, mixed>> profils, avec le compte s'il existe */
    private function clientes(): Collection
    {
        $comptes = 0;

        $profils = collect(self::CUSTOMERS)->map(function (array $c, int $i) use (&$comptes) {
            [$prenom, $nom, $ville, $commune, $pays, $aUnCompte] = $c;
            $telephone = '07000' . str_pad((string) (10 + $i), 5, '0', STR_PAD_LEFT);

            $user = null;
            if ($aUnCompte) {
                $slug = \Illuminate\Support\Str::slug($prenom . ' ' . $nom, '.');
                $user = User::create([
                    'name'     => $prenom . ' ' . $nom,
                    'email'    => $slug . '@' . self::EMAIL_DOMAIN,
                    'phone'    => $telephone,
                    'password' => Hash::make(self::CUSTOMER_PASSWORD),
                ]);
                $user->syncRoles(['customer']);
                $comptes++;
            }

            return [
                'user'       => $user,
                'first_name' => $prenom,
                'last_name'  => $nom,
                'phone'      => $telephone,
                'city'       => $ville,
                'commune'    => $commune,
                'country'    => $pays,
            ];
        });

        $this->bilan['customers'] = $comptes;

        return $profils;
    }

    // ── Commandes ──────────────────────────────────────────────────────────

    /** @return Collection<int, Order> triées de la plus ancienne à la plus récente */
    private function commandes(Collection $clientes): Collection
    {
        $produits = Product::where('is_active', true)->where('price', '>', 0)->get();
        $brouillons = [];

        // Rythme d'un démarrage : peu de ventes le premier mois, puis une
        // dizaine par jour sur la période récente, celle que les écrans
        // montrent d'abord.
        for ($jours = 29; $jours >= 0; $jours--) {
            $nombre = match (true) {
                $jours <= 1 => 14,
                $jours <= 9 => mt_rand(12, 17),
                default     => mt_rand(2, 4),
            };

            for ($n = 0; $n < $nombre; $n++) {
                $brouillons[] = $this->brouillon($clientes, $produits, $jours);
            }
        }

        // Numérotées dans l'ordre chronologique, comme en boutique.
        usort($brouillons, fn ($a, $b) => $a['created_at'] <=> $b['created_at']);

        $commandes = collect();
        foreach ($brouillons as $i => $b) {
            $lignes = $b['lignes'];
            unset($b['lignes']);

            $order = new Order($b + ['number' => self::NUMBER_PREFIX . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT)]);
            $order->created_at = $b['created_at'];
            $order->updated_at = $b['delivered_at'] ?? $b['shipped_at'] ?? $b['created_at'];
            $order->save();

            foreach ($lignes as $ligne) {
                $item = new OrderItem($ligne + ['order_id' => $order->id]);
                $item->created_at = $order->created_at;
                $item->save();
            }

            $commandes->push($order);
        }

        $this->ensureStatusCoverage($commandes);

        $this->bilan['orders'] = $commandes->count();

        return $commandes;
    }

    /**
     * Statuses the admin screens each have a tab for.
     *
     * @var array<int, string>
     */
    private const COVERED_STATUSES = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    /**
     * Makes sure every screen has something to show, whatever the hour the set
     * is built at.
     *
     * The generator is faithful rather than exhaustive: a parcel leaves the shop
     * at 9:30 the day after the order, so before 9:30 nothing ordered yesterday
     * can be « expédiée ». A set created in the morning could therefore hold no
     * shipped order at all, and the screen that lists them opened empty — which
     * is precisely what a demonstration set exists to avoid.
     *
     * Rather than bend the generator, the missing statuses are filled in
     * afterwards by moving one order back or forward a step, timestamps kept
     * coherent: no delivery without a departure, no departure in the future.
     *
     * @param \Illuminate\Support\Collection<int, Order> $orders
     */
    private function ensureStatusCoverage(Collection $orders): void
    {
        foreach (self::COVERED_STATUSES as $status) {
            if ($orders->contains('status', $status)) {
                continue;
            }

            $order = $this->coverageCandidate($orders, $status);

            if ($order === null) {
                continue;
            }

            $this->moveTo($order, $status);
        }
    }

    /**
     * An order that can carry the missing status without becoming absurd.
     *
     * Oldest first for the late stages — an order placed three days ago can
     * plausibly have been delivered, one placed an hour ago cannot.
     *
     * @param \Illuminate\Support\Collection<int, Order> $orders
     */
    private function coverageCandidate(Collection $orders, string $status): ?Order
    {
        $settled = ['cancelled', 'refunded'];

        // Kept as they are: they are the only witnesses of their own status.
        $usable = $orders->reject(fn (Order $o) => in_array($o->status, $settled, true)
            && $orders->where('status', $o->status)->count() === 1);

        return match ($status) {
            // A departure must already have happened: yesterday 9:30 at the
            // latest, so the order has to be at least two days old.
            'shipped', 'delivered' => $usable
                ->filter(fn (Order $o) => $o->created_at->lte(now()->subDays(2)))
                ->sortBy('created_at')
                ->first(),
            default => $usable->sortByDesc('created_at')->first(),
        };
    }

    /** Moves an order to that status, and rewrites only the dates it implies. */
    private function moveTo(Order $order, string $status): void
    {
        $shipped   = $order->created_at->copy()->addDay()->setTime(9, 30);
        $delivered = $shipped->copy()->addHours(6);

        $dates = match ($status) {
            'pending' => ['paid_at' => null, 'shipped_at' => null, 'delivered_at' => null, 'exported_at' => null],
            // Extraite la veille au soir, elle sort ce matin : l'extraction se
            // garde, le départ n'a pas encore eu lieu.
            'processing' => ['shipped_at' => null, 'delivered_at' => null],
            'shipped'    => ['shipped_at' => $shipped, 'delivered_at' => null],
            'delivered'  => [
                'shipped_at'   => $shipped,
                'delivered_at' => $delivered,
                // Réglée à la porte, comme le reste des livraisons à Abidjan.
                'paid_at'      => $order->paid_at ?? $delivered,
            ],
            // Annulée ou remboursée : rien n'est parti, rien n'a été remis.
            default => ['shipped_at' => null, 'delivered_at' => null],
        };

        $order->fill($dates + ['status' => $status]);
        $order->updated_at = $order->delivered_at ?? $order->shipped_at ?? $order->created_at;
        $order->save();
    }

    /** Une commande cohérente : montants, statut et horodatages qui se suivent. */
    private function brouillon(Collection $clientes, Collection $produits, int $jours): array
    {
        // Abidjan pèse l'essentiel des ventes, l'intérieur un quart, l'étranger
        // le reste.
        $tirage  = mt_rand(1, 100);
        $bassin  = $tirage <= 74 ? 'CI-ABJ' : ($tirage <= 94 ? 'CI-INT' : 'INTL');
        $cliente = $clientes
            ->filter(fn ($c) => match ($bassin) {
                'CI-ABJ' => $c['country'] === 'CI' && $c['city'] === 'Abidjan',
                'CI-INT' => $c['country'] === 'CI' && $c['city'] !== 'Abidjan',
                default  => $c['country'] !== 'CI',
            })
            ->values()
            ->pipe(fn ($liste) => $liste[mt_rand(0, $liste->count() - 1)]);

        $creee = $this->horodatage($jours);

        $adresse = [
            'first_name'    => $cliente['first_name'],
            'last_name'     => $cliente['last_name'],
            'phone'         => $cliente['phone'],
            'address_line1' => self::QUARTIERS[mt_rand(0, count(self::QUARTIERS) - 1)],
            'landmark'      => self::REPERES[mt_rand(0, count(self::REPERES) - 1)],
            'city'          => $cliente['city'],
            'commune'       => $cliente['commune'],
            'country'       => $cliente['country'],
        ];

        // ── Lignes ─────────────────────────────────────────────────────────
        $lignes = [];
        foreach ($produits->shuffle(mt_rand())->take(mt_rand(1, 3)) as $produit) {
            $qte = mt_rand(1, 100) <= 70 ? 1 : mt_rand(2, 3);
            $lignes[] = [
                'product_id'   => $produit->id,
                'product_name' => $produit->name,
                'sku'          => $produit->sku,
                'quantity'     => $qte,
                'unit_price'   => (float) $produit->price,
                'total_price'  => (float) $produit->price * $qte,
            ];
        }
        $sousTotal = array_sum(array_column($lignes, 'total_price'));
        $remise    = mt_rand(1, 100) <= 10 ? 1000.0 : 0.0;

        // ── Canal et paiement ──────────────────────────────────────────────
        $abidjan  = $bassin === 'CI-ABJ';
        $canal    = 'site';
        if ($abidjan) {
            $t = mt_rand(1, 100);
            $paiement = match (true) { $t <= 47 => 'cod', $t <= 74 => 'wave', $t <= 91 => 'orange_money', default => 'cash' };
            if ($paiement === 'cash') {
                $canal = 'pos';
            } elseif (mt_rand(1, 100) <= 15) {
                $canal = 'whatsapp';
            }
        } else {
            // Hors Abidjan, on n'expédie que ce qui est réglé d'avance.
            $paiement = mt_rand(1, 100) <= 60 ? 'wave' : (mt_rand(0, 1) ? 'orange_money' : 'card');
        }

        // ── Frais de livraison ─────────────────────────────────────────────
        $aTarifer = false;
        if ($canal === 'pos') {
            $frais = 0.0; // vente au comptoir : la cliente repart avec
        } else {
            $devis = DeliveryZone::quoteWithAbidjanBase($adresse['city'], $adresse['country'], (int) $sousTotal, $adresse['commune']);
            $inconnu = $devis === null || ($bassin === 'INTL' && mt_rand(1, 100) <= 40);
            $aTarifer = $inconnu;
            $frais = $inconnu ? 0.0 : (float) $devis['price'];
        }

        // ── Statut, selon l'âge de la commande ─────────────────────────────
        $statut = $this->statut($jours, $abidjan, $aTarifer, $canal);

        // ── Horodatages ────────────────────────────────────────────────────
        //
        // Chaque étape suit la précédente. Une étape qui n'a pas encore pu se
        // produire — la livraison d'un colis parti il y a dix minutes — n'est
        // pas antidatée : la commande en reste au stade précédent.
        $expediee = null;
        $livree   = null;
        if (in_array($statut, ['shipped', 'delivered'], true)) {
            // Les colis partent à 9 h 30, le lendemain de la commande. Un départ
            // pas encore arrivé n'a pas eu lieu : la commande attend en préparation.
            $depart = $creee->copy()->addDay()->setTime(9, 30);
            $expediee = $canal === 'pos' ? $creee->copy() : ($depart->isFuture() ? null : $depart);
            if ($expediee === null) {
                $statut = 'processing';
            }
        }
        if ($statut === 'delivered') {
            $livree = $canal === 'pos'
                ? $creee->copy()
                : $this->deja($expediee->copy()->addHours($abidjan ? mt_rand(2, 7) : mt_rand(24, 60)), $expediee->copy()->addMinutes(30));
            if ($livree === null) {
                $statut = 'shipped';
            }
        }

        $enLigne = in_array($paiement, ['wave', 'orange_money', 'card'], true);
        $payee   = match (true) {
            $canal === 'pos'       => true,
            $statut === 'refunded' => true,
            $enLigne               => ! ($statut === 'pending' && mt_rand(1, 100) <= 60),
            default                => $statut === 'delivered', // réglée à la porte
        };
        // Hors Abidjan, rien ne se prépare ni ne part sans règlement.
        if (! $abidjan && in_array($statut, ['processing', 'shipped', 'delivered'], true)) {
            $payee = true;
        }

        $payeeLe = null;
        if ($payee) {
            $payeeLe = match (true) {
                $canal === 'pos' => $creee->copy(),
                $enLigne         => $this->deja($creee->copy()->addMinutes(mt_rand(2, 20)), $creee) ?? $creee->copy(),
                default          => $livree?->copy() ?? $creee->copy(),
            };
        }

        // Extraite : tout ce qui a passé une extraction du soir, sauf un oubli
        // de temps en temps.
        $extraite = null;
        if ($canal !== 'pos' && $jours >= 1 && in_array($statut, ['processing', 'shipped', 'delivered'], true) && mt_rand(1, 100) <= 80) {
            $extraite = $this->deja($creee->copy()->setTime(21, 0), $creee);
        }

        $metadata = array_filter([
            'channel'          => $canal === 'site' ? null : $canal,
            'shipping_unknown' => $aTarifer ?: null,
            'demo'             => true,
        ], fn ($v) => $v !== null);

        return [
            'user_id'          => $cliente['user']?->id,
            'status'           => $statut,
            'subtotal'         => $sousTotal,
            'discount_amount'  => $remise,
            'shipping_cost'    => $frais,
            'total'            => max(0, $sousTotal - $remise + $frais),
            'currency'         => 'XOF',
            'shipping_address' => $adresse,
            'billing_address'  => $adresse,
            'shipping_method'  => $canal === 'site' ? 'delivery' : 'pos',
            'payment_method'   => $paiement,
            'tracking_number'  => (! $abidjan && $expediee) ? 'RB-' . $creee->format('Y') . '-' . mt_rand(10000, 99999) : null,
            'customer_note'    => mt_rand(1, 100) <= 15 ? 'Merci de m\'appeler avant de passer.' : null,
            'admin_note'       => match ($statut) {
                'cancelled' => 'Annulée à la demande de la cliente',
                'refunded'  => 'Remboursée : produit indisponible',
                default     => null,
            },
            'metadata'         => $metadata,
            'paid_at'          => $payeeLe,
            'shipped_at'       => $expediee,
            'delivered_at'     => $livree,
            'exported_at'      => $extraite,
            'created_at'       => $creee,
            'lignes'           => $lignes,
        ];
    }

    private function statut(int $jours, bool $abidjan, bool $aTarifer, string $canal): string
    {
        if ($canal === 'pos') {
            return mt_rand(1, 100) <= 95 ? 'delivered' : 'cancelled';
        }

        // Frais inconnus : rien ne part tant qu'ils ne sont pas fixés.
        if ($aTarifer) {
            return mt_rand(1, 100) <= 85 ? 'pending' : 'cancelled';
        }

        $t = mt_rand(1, 100);

        return match (true) {
            $jours === 0 => match (true) { $t <= 55 => 'pending', $t <= 90 => 'processing', default => 'cancelled' },
            // La veille : l'essentiel attend la tournée du jour.
            $jours === 1 => match (true) { $t <= 10 => 'pending', $t <= 55 => 'processing', $t <= 80 => 'shipped', $t <= 95 => 'delivered', default => 'cancelled' },
            $jours <= 4  => match (true) { $t <= 5 => 'pending', $t <= 15 => 'processing', $t <= 30 => ($abidjan ? 'delivered' : 'shipped'), $t <= 85 => 'delivered', $t <= 95 => 'cancelled', default => 'refunded' },
            default      => match (true) { $t <= 4 => 'pending', $t <= 80 => 'delivered', $t <= 93 => 'cancelled', default => 'refunded' },
        };
    }

    /** Heure de commande plausible, jamais dans le futur. */
    private function horodatage(int $jours): Carbon
    {
        // Les trois tirages ont lieu quoi qu'il arrive, même quand le repli ne
        // sert pas. Tirer le troisième seulement en cas de besoin décalait
        // toute la suite aléatoire selon l'heure d'exécution, et de façon
        // invisible : le même code produisait des statuts différents le matin
        // et le soir. Le jeu varie encore avec l'heure, mais seulement là où
        // c'est voulu et lisible — un colis qui n'est pas encore parti n'est
        // pas expédié.
        $heure  = mt_rand(8, 21);
        $minute = mt_rand(0, 59);
        $recul  = mt_rand(5, 600);

        $moment = now()->subDays($jours)->setTime($heure, $minute);

        if ($moment->isFuture()) {
            // Aujourd'hui, avant l'heure : réparties sur la matinée écoulée.
            $ecoule = max(6, (int) now()->startOfDay()->diffInMinutes(now()) - 1);
            $moment = now()->subMinutes(min($recul, $ecoule));
        }

        return $moment;
    }

    /**
     * L'instant voulu s'il est déjà passé ; sinon le plus tard possible, mais
     * jamais avant le plancher. Null quand même le plancher est à venir : l'étape
     * n'a pas encore pu avoir lieu.
     */
    private function deja(Carbon $voulu, Carbon $plancher): ?Carbon
    {
        $limite = now()->subMinutes(2);
        $moment = $voulu->gt($limite) ? $limite : $voulu;

        return $moment->lt($plancher) ? null : $moment;
    }

    // ── Tournées ───────────────────────────────────────────────────────────

    /**
     * Les tournées d'Abidjan, dans tous leurs états.
     *
     *   · clôturées sur les huit derniers jours — les commandes plus anciennes
     *     datent d'avant la mise en service des tournées —, avec un écart de
     *     caisse et des échecs motivés ;
     *   · la tournée du jour de départ en cours : à moitié pointée si elle est
     *     partie ce matin, entièrement pointée mais pas encore clôturée si l'on
     *     lance avant le départ du jour ;
     *   · les brouillons « à faire partir » issus de l'extraction de 21 h, et un
     *     brouillon qui reprend les échecs avec son livreur.
     */
    private function tournees(Collection $commandes, Collection $livreurs): void
    {
        $livrables = $commandes->filter(fn (Order $o) =>
            $o->destination === 'abidjan' && ($o->metadata['channel'] ?? null) !== 'pos');

        $parties = $livrables->filter(fn (Order $o) =>
            in_array($o->status, ['shipped', 'delivered'], true) && $o->shipped_at);

        $jourEnCours = $parties->map(fn (Order $o) => $o->shipped_at->toDateString())->unique()->sort()->last();
        // Huit jours : au-delà, le volume de démarrage ne remplit pas une sortie.
        $fenetre     = now()->subDays(8)->startOfDay();

        $nbTournees = 0;
        $rang = 0;
        $derniereCloturee = null;

        // ── Clôturées ───────────────────────────────────────────────────────
        $parJour = $parties
            ->filter(fn (Order $o) => $o->status === 'delivered'
                && $o->shipped_at->gte($fenetre)
                && $o->shipped_at->toDateString() !== $jourEnCours)
            ->groupBy(fn (Order $o) => $o->shipped_at->toDateString());

        foreach ($parJour->sortKeys() as $jour => $ordres) {
            // Au-delà de dix livraisons, un second livreur prend la suite.
            foreach ($this->parCommune($ordres)->chunk(10) as $lot) {
                $livreur = $livreurs[$rang++ % $livreurs->count()];
                $depart  = Carbon::parse($jour)->setTime(9, 30);

                $tournee = $this->tournee($lot->first(), $livreur, DeliveryRound::STATUS_CLOSED, $depart, 'Abidjan — ' . $depart->translatedFormat('l d/m'));
                $tournee->update(['closed_at' => $depart->copy()->setTime(18, 30)]);

                $position = 0;
                foreach ($lot as $o) {
                    $this->ligne($tournee, $o, ++$position, DeliveryRoundOrder::STATUS_DELIVERED, $livreur, $o->delivered_at);
                }

                $derniereCloturee = $tournee;
                $nbTournees++;
            }
        }

        // Dans la dernière tournée clôturée : un écart de caisse, et deux
        // passages sans livraison — commandes restées depuis en préparation.
        $echecs = collect();
        if ($derniereCloturee) {
            $aEncaisser = $derniereCloturee->lines()->where('amount_expected', '>', 0)->first();
            $aEncaisser?->update(['amount_collected' => (float) $aEncaisser->amount_expected - 1500]);

            $echecs = $livrables
                ->filter(fn (Order $o) => $o->status === 'processing' && $o->created_at->lt($derniereCloturee->dispatched_at))
                ->take(2)
                ->values();

            $motifs = [
                [DeliveryRoundOrder::STATUS_FAILED, 'Cliente injoignable, trois appels sans réponse'],
                [DeliveryRoundOrder::STATUS_POSTPONED, 'Reportée à la demande de la cliente, absente ce jour'],
            ];
            $position = $derniereCloturee->lines()->max('position');
            foreach ($echecs as $i => $o) {
                [$issue, $motif] = $motifs[$i];
                $this->ligne($derniereCloturee, $o, ++$position, $issue, null, null, $motif);
            }
        }

        // ── Le jour de départ en cours ──────────────────────────────────────
        $enRoute = $jourEnCours
            ? $this->parCommune($parties->filter(fn (Order $o) => $o->shipped_at->toDateString() === $jourEnCours))
            : collect();

        if ($enRoute->isNotEmpty()) {
            $depart  = Carbon::parse($jourEnCours)->setTime(9, 30);
            $livreur = $livreurs[0];
            $ceMatin = $depart->isToday();
            $tournee = $this->tournee($enRoute->first(), $livreur, DeliveryRound::STATUS_DISPATCHED, $depart,
                'Abidjan — ' . ($ceMatin ? 'ce matin' : 'à clôturer'));

            foreach ($enRoute as $i => $o) {
                // Partie ce matin : la première moitié est passée, si le temps
                // l'a permis — un arrêt prévu à 11 h ne se pointe pas à 9 h 45.
                // Partie la veille : tout est pointé, il reste à compter la caisse.
                $livreeLe = $ceMatin
                    ? ($i < intdiv($enRoute->count(), 2)
                        ? $this->deja($depart->copy()->addMinutes(40 * ($i + 1)), $depart->copy()->addMinutes(10))
                        : null)
                    : $o->delivered_at;

                if ($livreeLe && ($ceMatin || $o->status === 'delivered')) {
                    $o->update([
                        'status'       => 'delivered',
                        'delivered_at' => $livreeLe,
                        'paid_at'      => $o->paid_at ?? $livreeLe,
                    ]);
                    $this->ligne($tournee, $o, $i + 1, DeliveryRoundOrder::STATUS_DELIVERED, $livreur, $livreeLe);
                } else {
                    // Encore dans la sacoche : un paiement à la porte n'a pas
                    // encore eu lieu.
                    $o->update([
                        'status'       => 'shipped',
                        'delivered_at' => null,
                        'paid_at'      => in_array($o->payment_method, ['cod', 'delivery'], true) ? null : $o->paid_at,
                    ]);
                    $this->ligne($tournee, $o, $i + 1, DeliveryRoundOrder::STATUS_PENDING);
                }
            }
            $nbTournees++;
        }

        // ── À faire partir : ce que l'extraction de 21 h a vu ───────────────
        $extraction = now()->hour >= 21 ? now()->setTime(21, 0) : now()->subDay()->setTime(21, 0);
        $engagees   = DeliveryRoundOrder::query()
            ->join('delivery_rounds', 'delivery_rounds.id', '=', 'delivery_round_orders.delivery_round_id')
            ->where('delivery_round_orders.status', DeliveryRoundOrder::STATUS_PENDING)
            ->pluck('delivery_round_orders.order_id')
            ->all();

        $aPreparer = $this->parCommune($livrables->filter(fn (Order $o) => $o->status === 'processing'
            && $o->created_at->lte($extraction)
            && ! in_array($o->id, $engagees, true)));

        $retentes = $aPreparer->filter(fn (Order $o) => $echecs->contains('id', $o->id))->values();
        $autres   = $aPreparer->reject(fn (Order $o) => $echecs->contains('id', $o->id))->values();

        $brouillons = [
            // Tel que l'extraction le dépose : sans livreur, à affecter le matin.
            [$autres->take(10), null, 'Abidjan — extraction du ' . $extraction->format('d/m')],
            // Les reprises, et ce qui déborde : un livreur est déjà désigné.
            [$retentes->merge($autres->slice(10)), $livreurs[1], 'Abidjan — reprises'],
        ];

        foreach ($brouillons as [$lot, $livreur, $libelle]) {
            if ($lot->isEmpty()) {
                continue;
            }

            $tournee = $this->tournee($lot->first(), $livreur, DeliveryRound::STATUS_DRAFT, null, $libelle, $extraction);
            $position = 0;
            foreach ($lot as $o) {
                $this->ligne($tournee, $o, ++$position, DeliveryRoundOrder::STATUS_PENDING);
            }
            $nbTournees++;
        }

        $this->bilan['rounds'] = $nbTournees;
        $this->bilan['round_lines'] = DeliveryRoundOrder::whereIn('delivery_round_id',
            DeliveryRound::where('notes', self::ROUND_MARKER)->pluck('id'))->count();
    }

    /** Ordre de passage : par commune, comme un livreur enchaîne ses arrêts. */
    private function parCommune(Collection $commandes): Collection
    {
        return $commandes
            ->sortBy(fn (Order $o) => ($o->shipping_address['commune'] ?? '') . '|' . $o->created_at->timestamp)
            ->values();
    }

    private function tournee(Order $reference, ?User $livreur, string $statut, ?Carbon $depart, string $libelle, ?Carbon $creee = null): DeliveryRound
    {
        $jour = $depart ?? $creee ?? now();
        $zone = DeliveryZone::quoteWithAbidjanBase(
            $reference->shipping_address['city'] ?? 'Abidjan', 'CI', null, $reference->shipping_address['commune'] ?? null,
        );

        $tournee = new DeliveryRound([
            'code'          => DeliveryRound::generateCode($jour),
            'label'         => $libelle,
            'zone_group'    => $zone['group'] ?? 'Grand Abidjan',
            'zone_name'     => null, // plusieurs communes : pas d'étiquette de zone
            'courier_id'    => $livreur?->id,
            'status'        => $statut,
            'dispatched_at' => $depart,
            'notes'         => self::ROUND_MARKER,
        ]);
        $tournee->created_at = $creee ?? $depart ?? now();
        $tournee->save();

        return $tournee;
    }

    private function ligne(
        DeliveryRound $tournee,
        Order $o,
        int $position,
        string $issue,
        ?User $pointeePar = null,
        ?Carbon $livreeLe = null,
        ?string $motif = null,
    ): void {
        // Ce que le livreur devait rapporter, figé au départ : rien pour une
        // commande déjà réglée en ligne, le total pour un paiement à la porte.
        $aEncaisser = in_array($o->payment_method, ['cod', 'delivery'], true) ? (float) $o->total : 0.0;
        $livree = $issue === DeliveryRoundOrder::STATUS_DELIVERED;

        DeliveryRoundOrder::create([
            'delivery_round_id'        => $tournee->id,
            'order_id'                 => $o->id,
            'position'                 => $position,
            'status'                   => $issue,
            'amount_expected'          => $aEncaisser,
            'amount_collected'         => $issue === DeliveryRoundOrder::STATUS_PENDING ? null : ($livree ? $aEncaisser : 0),
            'payment_method_collected' => $livree && $aEncaisser > 0 ? (mt_rand(0, 3) ? 'cash' : 'wave') : null,
            'delivered_at'             => $livree ? $livreeLe : null,
            'failure_reason'           => $motif,
            'confirmed_by'             => $issue === DeliveryRoundOrder::STATUS_PENDING ? null : $pointeePar?->id,
        ]);
    }
}
