<?php

namespace Modules\Order\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ExportStamp;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Maatwebsite\Excel\Excel;
use Modules\Admin\Support\Revenue;
use Illuminate\Support\Str;
use Modules\Order\Exports\OrdersExport;
use Modules\Order\Exports\ShipmentsExport;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Models\Order;
use Modules\Order\Support\OrderGridQuery;
use Modules\Order\Support\OrderStatusLabel;
use Modules\Order\Support\OrderAttention;
use Modules\Order\Support\ShippingBlockers;
use Modules\Order\Support\ShippingFeeRequirement;
use Modules\Order\Services\InvoiceService;
use Modules\Order\Services\OrderService;
use Modules\Payment\Services\CinetPayService;
use Modules\Payment\Services\GeniusPayService;
use Modules\Order\Support\HomeZone;

class OrderController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'orders.view' => ['index', 'show', 'stats', 'attention', 'export', 'exportShipments', 'exportPreview', 'exportByZone', 'exportByZoneTxt', 'byZone', 'invoice', 'whatsappMessage'],
            'orders.edit' => ['update', 'generateTracking', 'markPaid', 'paymentLink', 'notify', 'bulkShip', 'bulkProcess', 'bulkMarkExported', 'bulkMarkPaid', 'bulkDeliver', 'bulkCancel', 'bulkArchive', 'bulkUnarchive'],
        ]);
    }

    public function __construct(
        private readonly OrderService $orderService,
        private readonly InvoiceService $invoiceService,
    ) {}

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $request->validate([
            'format'            => 'nullable|in:xlsx,csv',
            // `date` accepte aussi bien « 2026-09-09 » que l'horodatage rendu
            // par un champ datetime-local : la borne est donc à l'heure près
            // quand l'écran en fournit une.
            'date_from'         => 'nullable|date',
            'date_to'           => 'nullable|date|after_or_equal:date_from',
            'status'            => 'nullable|in:' . implode(',', Order::STATUSES),
            'include_cancelled' => 'nullable|boolean',
            'country'           => 'nullable|string|size:2',
            'order_ids'         => 'nullable|array',
            'order_ids.*'       => 'integer',
        ]);

        $export = new OrdersExport(
            dateFrom: $request->date_from,
            dateTo:   $request->date_to,
            status:   $request->status,
            country:  $request->country ? strtoupper($request->country) : null,
            orderIds: $request->order_ids ?: null,
            includeCancelled: $request->boolean('include_cancelled'),
        );

        $format   = $request->get('format', 'xlsx') === 'csv' ? Excel::CSV : Excel::XLSX;
        $ext      = $format === Excel::CSV ? 'csv' : 'xlsx';
        $filename = 'commandes-' . ExportStamp::now() . '.' . $ext;

        return $export->download($filename, $format);
    }

    /**
     * GET /admin/orders/export-shipments
     * Extraction propre à l'écran des expéditions.
     *
     * Distincte de `export` parce que la population l'est : celui-ci écarte les
     * commandes non payées hors Abidjan, ce qui vide exactement les onglets
     * qu'on cherche ici à sortir.
     *
     * `columns=minimal` ne rend que le client et le numéro de commande — la
     * liste qu'on emporte pour appeler, pointer ou transmettre au transporteur.
     */
    public function exportShipments(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $data = $request->validate([
            'tab'         => 'nullable|in:a_tarifer,interior,international',
            'columns'     => 'nullable|in:minimal,full',
            'format'      => 'nullable|in:xlsx,csv,txt',
            'order_ids'   => 'nullable|array',
            'order_ids.*' => 'integer',
            'label'       => 'nullable|string|max:120',
        ]);

        $onglets = [
            'a_tarifer'     => 'À tarifer',
            'interior'      => HomeZone::outsideLabel(),
            'international' => 'International',
        ];

        $tab   = $data['tab'] ?? null;
        $label = $data['label'] ?? ($onglets[$tab] ?? 'Expéditions');

        /*
         * Texte brut : même feuille que celle des tournées, donc même mise en
         * page — imprimable sur une thermique et collable dans WhatsApp. La
         * réécrire ici en donnerait une seconde à maintenir, et deux feuilles
         * qui divergent finissent par se contredire sur un montant.
         *
         * `include_unpaid` est indispensable : sans lui, la règle « hors
         * Abidjan, rien d'impayé » viderait la feuille de tout ce que cet
         * écran sert à traiter.
         */
        if (($data['format'] ?? null) === 'txt') {
            $txt = $this->invoiceService->generateByZoneTxt([
                'order_ids'           => ShipmentsExport::resolve($tab, $data['order_ids'] ?? null)
                    ->pluck('id')->all(),
                'label'               => $label,
                'show_product_totals' => false,
                'show_item_counts'    => false,
                'include_unpaid'      => true,
            ]);

            return response($txt, 200, [
                'Content-Type'        => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="expeditions-'
                    . Str::slug($label) . '-' . ExportStamp::now() . '.txt"',
            ]);
        }

        $export = new ShipmentsExport(
            tab:      $tab,
            orderIds: $data['order_ids'] ?? null,
            columns:  $data['columns'] ?? ShipmentsExport::COLUMNS_FULL,
            label:    $label,
        );

        $csv    = ($data['format'] ?? 'xlsx') === 'csv';
        $format = $csv ? Excel::CSV : Excel::XLSX;

        $nom = 'expeditions-' . Str::slug($label) . '-' . ExportStamp::now()
            . ($csv ? '.csv' : '.xlsx');

        return $export->download($nom, $format);
    }

    /**
     * GET /admin/orders/export-preview
     * Aperçu des commandes qui seront exportées (mêmes filtres et mêmes règles
     * métier que l'export réel — OrdersExport::resolve est la source de vérité).
     */
    public function exportPreview(Request $request): JsonResponse
    {
        $request->validate([
            'date_from'         => 'nullable|date',
            'date_to'           => 'nullable|date|after_or_equal:date_from',
            'status'            => 'nullable|in:' . implode(',', Order::STATUSES),
            'include_cancelled' => 'nullable|boolean',
            'country'           => 'nullable|string|size:2',
            // Aperçu d'une sélection cochée dans la liste des commandes.
            'order_ids'         => 'nullable|array',
            'order_ids.*'       => 'integer',
        ]);

        $orders = OrdersExport::resolve([
            'date_from'         => $request->date_from,
            'date_to'           => $request->date_to,
            'status'            => $request->status,
            'include_cancelled' => $request->boolean('include_cancelled'),
            'country'           => $request->country ? strtoupper($request->country) : null,
            'order_ids'         => $request->input('order_ids'),
        ]);

        return response()->json([
            'data'  => OrderResource::collection($orders)->resolve(),
            'total' => $orders->count(),
        ]);
    }

    public function invoice(Order $order): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = $this->invoiceService->generate($order);

        return $pdf->download($this->invoiceService->filename($order));
    }

    /**
     * POST /admin/orders/{order}/notify
     * Renvoie à la cliente le récapitulatif de sa commande par WhatsApp,
     * avec la facture PDF en pièce jointe.
     *
     * L'envoi automatique à la commande peut échouer — numéro invalide, API
     * WhatsApp indisponible, fenêtre de 24 h dépassée. Cette action permet de
     * relancer depuis l'écran de traitement, sans attendre.
     *
     * Renvoie toujours le message rédigé et l'état de chaque envoi : en cas
     * d'échec, l'interface propose à l'agent d'envoyer lui-même via wa.me ou
     * de récupérer la facture. Un 500 muet laisserait la cliente sans rien.
     */
    public function notify(
        Order $order,
        \Modules\Auth\Services\CustomerWhatsAppService $whatsapp,
        \Modules\Order\Services\OrderNotificationMessage $redacteur,
    ): JsonResponse {
        // Le récapitulatif envoyé porte le total. L'expédier avant d'avoir
        // arrêté les frais, c'est annoncer un montant qu'il faudra corriger —
        // et une correction à la hausse, une cliente la refuse.
        if (ShippingFeeRequirement::isPending($order)) {
            return response()->json([
                'code'    => 'shipping_fee_required',
                'message' => ShippingFeeRequirement::message($order),
            ], 422);
        }

        $order = $order->loadMissing(['user', 'items.product']);
        $addr  = $order->shipping_address ?? [];
        $phone = $addr['phone'] ?? $order->user?->phone ?? null;

        $message = $redacteur->build($order);

        if (! $phone) {
            return response()->json([
                'sent'     => false,
                'document' => false,
                'phone'    => null,
                'message'  => $message,
                'reason'   => 'Aucun numéro de téléphone sur cette commande.',
            ], 422);
        }

        // Même arbitrage que l'envoi automatique : texte complet si la cliente
        // a écrit récemment, modèle approuvé sinon. Sans cela le bouton
        // « Notifier » échouerait sur toutes les commandes du site, celles
        // justement passées par des clientes qui n'ont jamais écrit.
        $texteEnvoye = $whatsapp->sendSessionOrTemplate(
            $phone,
            $message,
            'order_confirmation',
            [
                trim((string) ($addr['first_name'] ?? '')) ?: 'cliente',
                $order->number,
                number_format((float) $order->total, 0, ',', ' ') . ' F CFA',
            ],
        );

        // La facture est secondaire : son échec ne doit pas masquer le fait
        // que le récapitulatif, lui, est bien parti.
        $factureEnvoyee = false;
        try {
            $pdf = $this->invoiceService->generate($order);
            $factureEnvoyee = $whatsapp->sendDocument(
                $phone,
                $pdf->output(),
                $this->invoiceService->filename($order),
                "Facture de votre commande {$order->number}",
            );
        } catch (\Throwable $e) {
            \Log::warning("Notification manuelle {$order->number} : facture non envoyée — " . $e->getMessage());
        }

        return response()->json([
            'sent'     => $texteEnvoye,
            'document' => $factureEnvoyee,
            'phone'    => $phone,
            'message'  => $message,
            'reason'   => $texteEnvoye ? null : "WhatsApp n'a pas accepté l'envoi.",
        ], $texteEnvoye ? 200 : 502);
    }

    /**
     * GET /admin/orders/{order}/whatsapp-message
     * Rend le récapitulatif WhatsApp sans l'envoyer.
     *
     * L'écran de détail propose un lien wa.me que l'agent ouvre lui-même. Le
     * texte était jusqu'ici rédigé côté navigateur, et cette seconde version a
     * dérivé : la cliente recevait « commande enregistrée + statut », sans le
     * détail des articles ni les montants que l'envoi automatique, lui,
     * contenait. On sert donc le même texte aux deux chemins.
     */
    public function whatsappMessage(
        Order $order,
        \Modules\Order\Services\OrderNotificationMessage $redacteur,
    ): JsonResponse {
        $addr = $order->shipping_address ?? [];

        return response()->json([
            'message' => $redacteur->build($order),
            'phone'   => $addr['phone'] ?? $order->user?->phone,
        ]);
    }

    /**
     * GET /admin/orders/export-by-zone
     * Export PDF des commandes groupées par zone de livraison.
     */
    public function exportByZone(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = $this->invoiceService->generateByZoneExport($this->zoneExportFilters($request));

        return $pdf->download($this->invoiceService->filenameByZone());
    }

    /**
     * GET /admin/orders/export-by-zone-txt
     * Même feuille de livraison, en texte brut : imprimable partout et
     * annotable à la main (case « [ ] LIVRÉ » par commande).
     */
    public function exportByZoneTxt(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $txt = $this->invoiceService->generateByZoneTxt($this->zoneExportFilters($request));

        return response($txt, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $this->invoiceService->filenameByZoneTxt() . '"',
        ]);
    }

    /** Filtres communs aux deux sorties (PDF et TXT) de la feuille de livraison. */
    private function zoneExportFilters(Request $request): array
    {
        $request->validate([
            'date_from'           => 'nullable|date',
            'date_to'             => 'nullable|date',
            'status'              => 'nullable|in:' . implode(',', Order::STATUSES),
            'order_ids'           => 'nullable|array',
            'order_ids.*'         => 'integer',
            'label'               => 'nullable|string|max:120',
            'show_product_totals' => 'nullable|boolean',
            'show_item_counts'    => 'nullable|boolean',
            'include_unpaid'      => 'nullable|boolean',
        ]);

        return [
            'date_from'           => $request->date_from,
            'date_to'             => $request->date_to,
            'status'              => $request->status,
            'order_ids'           => $request->order_ids ?: null,
            'label'               => $request->label,
            // Cochés par défaut côté aperçu : on garde la même valeur par défaut ici.
            'show_product_totals' => $request->boolean('show_product_totals', true),
            'show_item_counts'    => $request->boolean('show_item_counts', true),
            // Lève le garde-fou « hors Abidjan, rien d'impayé ». Réservé à
            // l'écran des expéditions, dont c'est toute la population.
            'include_unpaid'      => $request->boolean('include_unpaid'),
        ];
    }

    /**
     * GET /admin/orders/stats
     * Vue d'ensemble : counts par statut + CA + activité récente.
     */
    public function stats(Request $request): \Illuminate\Http\JsonResponse
    {
        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $payload = [
            // Statuts
            'pending'    => (int)($counts['pending']    ?? 0),
            'confirmed'  => (int)($counts['confirmed']  ?? 0),
            'processing' => (int)($counts['processing'] ?? 0),
            'shipped'    => (int)($counts['shipped']    ?? 0),
            'delivered'  => (int)($counts['delivered']  ?? 0),
            'cancelled'  => (int)($counts['cancelled']  ?? 0),
            'refunded'   => (int)($counts['refunded']   ?? 0),
            'total'      => array_sum($counts),

            // Activité récente
            'today'      => Order::whereDate('created_at', today())->count(),
            'this_week'  => Order::where('created_at', '>=', now()->startOfWeek())->count(),

            // Finance privée : le CA n'est renvoyé qu'avec la permission finance.view.
            'amounts_hidden' => true,
        ];

        if ($request->user()?->can('finance.view')) {
            // Même règle que le tableau de bord : livrée seulement, articles
            // hors frais de livraison. Cet écran comptait auparavant le
            // confirmé et le « en préparation » comme du chiffre acquis, et
            // sur le total livraison comprise — deux écrans, deux montants
            // pour la même journée.
            $payload['revenue']             = Revenue::sum(Revenue::delivered());
            $payload['revenue_in_progress'] = Revenue::sum(Revenue::inProgress());
            $payload['pending_revenue']     = Revenue::sum(Order::query()->where('status', 'pending'));
            $payload['avg_basket']          = Revenue::delivered()->count() > 0
                ? Revenue::sum(Revenue::delivered()) / Revenue::delivered()->count()
                : 0.0;
            $payload['amounts_hidden'] = false;
        }

        return response()->json($payload);
    }

    /**
     * GET /admin/orders/by-zone
     * Liste les commandes groupées par zone de livraison résolue.
     * Les commandes hors zone forment un groupe "Hors zone".
     * Limité aux N dernières commandes (param ?limit, défaut 200, max 500).
     */
    public function byZone(Request $request): JsonResponse
    {
        $request->validate([
            'limit'  => 'nullable|integer|min:10|max:500',
            'status' => 'nullable|in:'.implode(',', Order::STATUSES),
        ]);
        $limit = (int) $request->get('limit', 200);

        $orders = Order::with(['user', 'items'])
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest()
            ->limit($limit)
            ->get();

        // Regrouper par zone résolue
        $groups = [];
        foreach ($orders as $order) {
            $resource = (new OrderResource($order))->toArray($request);
            $zone     = $resource['shipping_zone'] ?? null;

            $key      = $zone['name']  ?? '__off_zone__';
            $label    = $zone['name']  ?? 'Hors zone';
            $group    = $zone['group'] ?? '—';

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'zone'   => $label,
                    'group'  => $group,
                    'count'  => 0,
                    'total'  => 0.0,
                    'orders' => [],
                ];
            }
            $groups[$key]['count']++;
            $groups[$key]['total'] += (float) $order->total;
            $groups[$key]['orders'][] = $resource;
        }

        // Hors zone toujours en dernier
        $list = array_values($groups);
        usort($list, function ($a, $b) {
            if ($a['zone'] === 'Hors zone') return 1;
            if ($b['zone'] === 'Hors zone') return -1;
            return strcmp($a['group'].$a['zone'], $b['group'].$b['zone']);
        });

        return response()->json([
            'data'  => $list,
            'meta'  => ['limit' => $limit, 'total_orders' => $orders->count()],
        ]);
    }

    public function index(Request $request): ResourceCollection
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = max(5, min($perPage, 100)); // clamp 5..100

        $grille = new OrderGridQuery((bool) $request->user()?->can('finance.view'));

        $query = Order::with(['user', 'items'])
            // Plusieurs valeurs séparées par des virgules : « en attente ou en
            // cours » est la question du matin. Une valeur seule reste acceptée,
            // c'est ce qu'envoient les autres écrans.
            ->when($request->filled('status'), fn ($q) => $q->whereIn('status', $this->liste($request->status)))
            ->when($request->filled('payment_method'), fn ($q) => $q->whereIn('payment_method', $this->liste($request->payment_method)))
            // Destination figée à la création : filtrable en SQL, là où la
            // déduire de l'adresse imposerait de tout charger en mémoire et
            // ferait sauter la pagination.
            ->when($request->filled('destination'), fn ($q) => $q->whereIn('destination', $this->liste($request->destination)))
            ->when($request->filled('paid'), fn ($q) => $request->boolean('paid')
                ? $q->whereNotNull('paid_at')
                : $q->whereNull('paid_at'))
            ->when($request->filled('exported'), fn ($q) => $request->boolean('exported')
                ? $q->whereNotNull('exported_at')
                : $q->whereNull('exported_at'))
            // Archivées : hors de la liste de travail par défaut, et seules
            // visibles quand on les demande. Écartées en SQL et non à l'écran,
            // sans quoi le nombre de pages compterait des lignes invisibles.
            ->when($request->boolean('archived'),
                fn ($q) => $q->whereNotNull('archived_at'),
                fn ($q) => $q->whereNull('archived_at'))
            // Zone non tarifée : les seules commandes qui attendent une
            // saisie humaine, et le vrai motif d'urgence de l'écran.
            ->when($request->boolean('a_tarifer'), fn ($q) =>
                $q->whereJsonContains('metadata->shipping_unknown', true)
            )
            // Journée en cours. L'application tourne en UTC, et Abidjan est
            // à GMT+0 toute l'année : la date du serveur est donc celle de
            // la boutique, sans conversion.
            ->when($request->boolean('du_jour'), fn ($q) =>
                $q->whereDate('created_at', today())
            )
            // Commandes mises de côté par l'agent : écartées ici plutôt que
            // masquées à l'écran, sans quoi le nombre de pages et le total
            // annoncés compteraient des lignes qu'on ne voit pas.
            ->when($request->filled('exclude'), fn ($q) =>
                $q->whereNotIn('id', array_map('intval', (array) $request->input('exclude')))
            )
            ->when($request->filled('search'), function ($q) use ($request) {
                $terms = $this->searchTerms((string) $request->search);

                // Groupé : sans parenthèses, le « ou » s'appliquait à toute la
                // requête, et une recherche combinée à un statut ramenait des
                // commandes de tous les statuts.
                $q->where(function ($w) use ($terms) {
                    foreach ($terms as $term) {
                        $w->orWhere(fn ($t) => $this->matchSearchTerm($t, $term));
                    }
                });
            });

        $grille->filter($query, OrderGridQuery::decode($request->input('filter')));
        $grille->sort($query, OrderGridQuery::decode($request->input('sort')));

        // Copie prise avant la pagination, qui borne la requête à la page.
        $unpaged    = clone $query;
        $collection = OrderResource::collection($query->paginate($perPage));

        // Plusieurs numéros collés d'un coup : dire lesquels manquent, et
        // pourquoi. Sur vingt numéros, repérer à l'œil les deux absents de la
        // liste est précisément ce que la recherche devait épargner.
        $numbers = $this->searchedOrderNumbers((string) $request->input('search', ''));
        if (count($numbers) > 1) {
            $found    = $unpaged->reorder()->whereIn('orders.number', $numbers)->pluck('orders.number')->map(fn ($n) => strtoupper($n))->all();
            $existing = Order::whereIn('number', $numbers)->pluck('number')->map(fn ($n) => strtoupper($n))->all();

            $collection->additional(['search' => [
                'numbers'      => $numbers,
                'not_found'    => array_values(array_diff($numbers, $existing)),
                'filtered_out' => array_values(array_diff(array_intersect($numbers, $existing), $found)),
            ]]);
        }

        return $collection;
    }

    /**
     * Découpe la recherche en termes.
     *
     * Virgules, points-virgules et retours à la ligne séparent toujours :
     * « ORD-2026-00042, ORD-2026-00043 » cherche les deux commandes. L'espace
     * ne sépare que des numéros de commande — sans quoi « Awa Koné » ou un
     * téléphone saisi « 07 00 00 00 20 » deviendraient cinq recherches.
     *
     * @return array<int, string>
     */
    private function searchTerms(string $search): array
    {
        $terms = array_values(array_filter(
            array_map('trim', preg_split('/[,;\r\n]+/', $search)),
            fn ($t) => $t !== '',
        ));

        $terms = collect($terms)->flatMap(function (string $term) {
            $words = preg_split('/\s+/', $term);

            return count($words) > 1 && collect($words)->every(fn ($w) => $this->looksLikeOrderNumber($w))
                ? $words
                : [$term];
        });

        // Borné : une recherche reste une recherche, pas un export.
        return $terms->unique()->take(200)->values()->all();
    }

    /** @return array<int, string> Numéros complets cherchés, en majuscules. */
    private function searchedOrderNumbers(string $search): array
    {
        return collect($this->searchTerms($search))
            ->filter(fn ($t) => $this->looksLikeOrderNumber($t))
            ->map(fn ($t) => strtoupper($t))
            ->unique()
            ->values()
            ->all();
    }

    private function looksLikeOrderNumber(string $term): bool
    {
        return (bool) preg_match('/^ORD-[A-Z0-9]+(-[A-Z0-9]+)*$/i', $term);
    }

    private function matchSearchTerm($query, string $term): void
    {
        $like = '%' . addcslashes($term, '\\%_') . '%';

        $query->where('number', 'like', $like)
            ->orWhere('shipping_address->first_name', 'like', $like)
            ->orWhere('shipping_address->last_name', 'like', $like)
            ->orWhere('shipping_address->phone', 'like', $like)
            ->orWhereHas('user', fn ($u) => $u
                ->where('email', 'like', $like)
                ->orWhere('name', 'like', $like));
    }

    /** « pending,processing » → ['pending', 'processing']. */
    private function liste(mixed $valeur): array
    {
        $valeurs = is_array($valeur) ? $valeur : explode(',', (string) $valeur);

        return array_values(array_filter(array_map('trim', $valeurs), fn ($v) => $v !== ''));
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['user', 'items.product.coverImage', 'items.variant', 'payments']));
    }

    public function update(Request $request, Order $order): OrderResource|JsonResponse
    {
        $data = $request->validate([
            'status'          => 'sometimes|in:'.implode(',', Order::STATUSES),
            'tracking_number' => 'nullable|string',
            'admin_note'      => 'nullable|string',
            'shipping_cost'   => 'nullable|numeric|min:0',
            // Fixé par l'agent sur les commandes hors zone, où la cliente n'a
            // rien pu choisir au moment de commander.
            'payment_method'  => 'sometimes|nullable|in:'.implode(',', Order::PAYMENT_METHODS_POS),
            // Prévenir la cliente du nouveau mode de règlement (défaut : oui)
            'notify_client'   => 'sometimes|boolean',
            // Permet à l'admin d'outrepasser les garde-fous d'expédition
            'force'           => 'sometimes|boolean',
        ]);

        if (isset($data['admin_note'])) {
            $order->update(['admin_note' => $data['admin_note']]);
        }

        // Mode de règlement fixé ou corrigé par l'agent.
        $paiementModifie = array_key_exists('payment_method', $data)
            && $data['payment_method'] !== $order->payment_method;

        if ($paiementModifie) {
            $order->update(['payment_method' => $data['payment_method']]);
            $order->refresh();
        }

        // Frais de livraison saisis par l'admin (cas hors zone). Recalcule le total
        // et lève le marqueur « frais à renseigner » posé à la création.
        if (array_key_exists('shipping_cost', $data) && $data['shipping_cost'] !== null) {
            $shipping = (float) $data['shipping_cost'];
            $total    = max(0, (float) $order->subtotal - (float) $order->discount_amount + $shipping);

            $metadata = $order->metadata ?? [];
            unset($metadata['shipping_unknown']);

            $order->update([
                'shipping_cost' => $shipping,
                'total'         => $total,
                'metadata'      => $metadata ?: null,
            ]);
        }

        if (isset($data['status'])) {
            // Garde-fou : on n'expédie pas une commande dont les frais hors zone
            // ne sont pas réglés, ni une commande impayée (hors paiement à la
            // livraison). L'admin peut passer outre avec force = true.
            if ($data['status'] === 'shipped' && ! ($data['force'] ?? false)) {
                $blockers = $this->shippingBlockers($order->fresh());
                if ($blockers !== []) {
                    return response()->json([
                        'code'     => 'shipping_blocked',
                        'message'  => implode(' ', $blockers),
                        'blockers' => $blockers,
                    ], 422);
                }
            }

            $order = $this->orderService->updateStatus($order, $data['status'], $data['tracking_number'] ?? null);
        }

        // Le mode de règlement vient de changer : sans notification, la cliente
        // ignore comment payer ce que l'agent a décidé pour elle.
        $notification = ($paiementModifie && ($data['notify_client'] ?? true))
            ? $this->notifierChangementPaiement($order->fresh())
            : null;

        $resource = new OrderResource($order->load(['user', 'items']));

        if ($notification === null) {
            return $resource;
        }

        return response()->json([
            'data'         => $resource->resolve($request),
            'notification' => $notification,
        ]);
    }

    /**
     * Envoie le récapitulatif à jour (instructions de règlement comprises).
     *
     * Renvoie toujours le texte rédigé : si WhatsApp refuse l'envoi, l'agent
     * doit pouvoir le transmettre lui-même plutôt que de laisser la cliente
     * sans instructions.
     *
     * @return array{sent:bool, phone:?string, message:string, reason:?string}
     */
    private function notifierChangementPaiement(Order $order): array
    {
        $redacteur = app(\Modules\Order\Services\OrderNotificationMessage::class);
        $message   = $redacteur->build($order);

        $addr  = $order->shipping_address ?? [];
        $phone = $addr['phone'] ?? $order->user?->phone;

        if (! $phone) {
            return [
                'sent'    => false,
                'phone'   => null,
                'message' => $message,
                'reason'  => 'Aucun numéro de téléphone sur cette commande.',
            ];
        }

        $envoye = false;
        try {
            $envoye = app(\Modules\Auth\Services\CustomerWhatsAppService::class)->sendText($phone, $message);
        } catch (\Throwable $e) {
            \Log::warning("Changement de règlement {$order->number} : notification non envoyée — ".$e->getMessage());
        }

        return [
            'sent'    => $envoye,
            'phone'   => $phone,
            'message' => $message,
            'reason'  => $envoye ? null : "WhatsApp n'a pas accepté l'envoi.",
        ];
    }

    /**
     * Raisons qui empêchent d'expédier une commande (liste vide = expédition OK).
     * Hors zone = frais de livraison jamais renseignés (marqueur shipping_unknown).
     *
     * La règle vit dans ShippingBlockers : la préparation d'une tournée
     * l'applique aussi, sur vingt commandes d'un coup.
     */
    private function shippingBlockers(Order $order): array
    {
        return ShippingBlockers::for($order);
    }

    /**
     * POST /admin/orders/bulk-ship
     * Marque un lot de commandes comme expédiées.
     *
     * Le pendant de la tournée pour les envois qui n'en forment pas une : un
     * dépôt chez un transporteur, une série de colis pour l'intérieur du pays.
     * Mêmes garde-fous et même échappatoire que l'expédition unitaire — la
     * différence tient au nombre, pas aux règles, et une expédition en masse
     * plus permissive que l'expédition à l'unité laisserait passer vingt fois
     * ce que l'écran de détail refuse une fois.
     */
    public function bulkShip(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'force'       => 'sometimes|boolean',
        ]);

        $force    = $request->boolean('force');
        $expediees = [];
        $ecartees  = [];

        foreach (Order::whereIn('id', $data['order_ids'])->get() as $order) {
            // Déjà expédiée ou déjà livrée : ce n'est pas un refus, il n'y a
            // simplement rien à faire. La compter comme écartée ferait croire
            // à un problème.
            if (in_array($order->status, ['shipped', 'delivered'], true)) {
                continue;
            }

            if (in_array($order->status, ['cancelled', 'refunded'], true)) {
                $ecartees[] = [
                    'id'        => $order->id,
                    'number'    => $order->number,
                    'reasons'   => ['Commande ' . ($order->status === 'cancelled' ? 'annulée' : 'remboursée') . '.'],
                    'forceable' => false,
                ];
                continue;
            }

            $blockers = $force ? [] : ShippingBlockers::for($order);

            if ($blockers !== []) {
                $ecartees[] = [
                    'id'        => $order->id,
                    'number'    => $order->number,
                    'reasons'   => $blockers,
                    'forceable' => true,
                ];
                continue;
            }

            $this->orderService->updateStatus($order, 'shipped');
            $expediees[] = ['id' => $order->id, 'number' => $order->number];
        }

        return response()->json([
            'shipped'  => $expediees,
            'rejected' => $ecartees,
            'message'  => count($expediees) . ' commande(s) marquée(s) comme expédiée(s).',
        ]);
    }

    /**
     * POST /admin/orders/bulk-process
     * Passe un lot de commandes en préparation.
     *
     * Le geste du matin : la veille a laissé trente commandes en attente, elles
     * partent toutes en préparation d'un coup. Aucun garde-fou de livraison ici
     * — on ne fait qu'accuser réception, rien ne quitte la boutique — mais une
     * commande annulée ne redevient pas à préparer pour autant.
     */
    public function bulkProcess(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $traitees = [];
        $ecartees = [];

        foreach (Order::whereIn('id', $data['order_ids'])->get() as $order) {
            // Déjà en préparation, ou plus loin dans le parcours : il n'y a rien
            // à faire. Revenir en arrière depuis « expédiée » effacerait une
            // information vraie — le colis est parti.
            if (! in_array($order->status, ['pending', 'confirmed'], true)) {
                if (in_array($order->status, ['cancelled', 'refunded'], true)) {
                    $ecartees[] = [
                        'id'        => $order->id,
                        'number'    => $order->number,
                        'reasons'   => ['Commande ' . ($order->status === 'cancelled' ? 'annulée' : 'remboursée') . '.'],
                        'forceable' => false,
                    ];
                }
                continue;
            }

            $this->orderService->updateStatus($order, 'processing');
            $traitees[] = ['id' => $order->id, 'number' => $order->number];
        }

        return response()->json([
            'processed' => $traitees,
            'rejected'  => $ecartees,
            'message'   => count($traitees) . ' commande(s) passée(s) en préparation.',
        ]);
    }

    /**
     * POST /admin/orders/bulk-mark-exported
     * Note que ces commandes viennent d'être extraites.
     *
     * Endpoint distinct des exports plutôt qu'un drapeau sur leur URL : ce sont
     * des GET, qu'un navigateur ou un antivirus peut rejouer, et le CSV est même
     * fabriqué côté client — il n'atteint jamais le serveur. Marquer après coup
     * couvre tous les formats de la même façon et ne fait jamais dépendre une
     * écriture d'un téléchargement.
     */
    public function bulkMarkExported(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $marquees = Order::whereIn('id', $data['order_ids'])->get();

        foreach ($marquees as $order) {
            // Passe par le modèle et non par une mise à jour de masse : le
            // journal des actions retient qui a extrait quoi, et une écriture
            // directe en base ne laisserait aucune trace.
            $order->update(['exported_at' => now()]);
        }

        return response()->json([
            'marked'  => $marquees->pluck('id'),
            'message' => $marquees->count() . ' commande(s) marquée(s) comme extraite(s).',
        ]);
    }

    /**
     * POST /admin/orders/bulk-mark-paid
     * Enregistre le règlement d'un lot de commandes.
     *
     * Le geste du soir : le relevé Wave ou la caisse du livreur à rapprocher,
     * quinze commandes à passer « payées ». Même effet qu'à l'unité — une
     * expédition en attente passe confirmée —, avec un garde-fou de plus : un
     * total dont les frais ne sont pas encore fixés n'est pas un montant
     * encaissé, et l'agent doit le décider commande par commande ou en force.
     */
    public function bulkMarkPaid(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'force'       => 'sometimes|boolean',
        ]);

        $force    = $request->boolean('force');
        $paid     = [];
        $rejected = [];

        foreach (Order::whereIn('id', $data['order_ids'])->get() as $order) {
            // Déjà réglée : rien à faire, et surtout pas écraser la date du
            // premier encaissement.
            if ($order->paid_at) {
                continue;
            }

            if (in_array($order->status, ['cancelled', 'refunded'], true)) {
                $rejected[] = $this->rejection($order, [$this->closedReason($order)], false);
                continue;
            }

            if (! $force && ShippingFeeRequirement::isPending($order)) {
                $rejected[] = $this->rejection($order, [
                    'Frais de livraison encore à fixer : le total n\'est pas définitif.',
                ], true);
                continue;
            }

            $this->recordPayment($order);
            $paid[] = ['id' => $order->id, 'number' => $order->number];
        }

        return response()->json([
            'paid'     => $paid,
            'rejected' => $rejected,
            'message'  => $this->bulkMessage(count($paid), 'marquée(s) comme payée(s)', 'Aucune commande marquée comme payée.'),
        ]);
    }

    /**
     * POST /admin/orders/bulk-deliver
     * Marque un lot de commandes comme livrées.
     *
     * Pour les envois qui ne passent pas par une tournée : colis remis au
     * transporteur de l'intérieur, retraits en boutique. Une commande encore
     * dans une tournée ouverte est refusée sans échappatoire : c'est le
     * pointage de la tournée qui compte l'argent rapporté par le livreur, et la
     * livrer ici laisserait une ligne « à pointer » que plus personne ne verrait.
     *
     * Le règlement n'est pas touché : une livraison n'est pas un encaissement.
     * Les commandes livrées mais toujours impayées sont renvoyées à part, pour
     * que l'agent les passe « payées » une fois l'argent reçu.
     */
    public function bulkDeliver(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'force'       => 'sometimes|boolean',
        ]);

        $force     = $request->boolean('force');
        $delivered = [];
        $toCollect = [];
        $rejected  = [];

        $orders     = Order::whereIn('id', $data['order_ids'])->get();
        $roundCodes = app(\Modules\Order\Services\DeliveryRoundService::class)
            ->openRoundCodes($orders->pluck('id')->all());

        foreach ($orders as $order) {
            if ($order->status === 'delivered') {
                continue;
            }

            if (in_array($order->status, ['cancelled', 'refunded'], true)) {
                $rejected[] = $this->rejection($order, [$this->closedReason($order)], false);
                continue;
            }

            if (isset($roundCodes[$order->id])) {
                $rejected[] = $this->rejection($order, [
                    "Prévue dans la tournée {$roundCodes[$order->id]} : pointez-la depuis l'écran des tournées.",
                ], false);
                continue;
            }

            // Livrer ce qui n'est jamais parti est le plus souvent une erreur de
            // case cochée. Le retrait en boutique en est l'exception assumée.
            if (! $force && $order->status !== 'shipped') {
                $rejected[] = $this->rejection($order, ["Pas encore expédiée."], true);
                continue;
            }

            $order = $this->orderService->updateStatus($order, 'delivered');
            $delivered[] = ['id' => $order->id, 'number' => $order->number];

            if (! $order->paid_at) {
                $toCollect[] = ['id' => $order->id, 'number' => $order->number];
            }
        }

        return response()->json([
            'delivered'  => $delivered,
            'to_collect' => $toCollect,
            'rejected'   => $rejected,
            'message'    => $this->bulkMessage(count($delivered), 'marquée(s) comme livrée(s)', 'Aucune commande marquée comme livrée.'),
        ]);
    }

    /**
     * POST /admin/orders/bulk-cancel
     * Annule un lot de commandes et remet leurs articles en stock.
     *
     * Pour les doublons, les commandes de test, les clientes injoignables
     * depuis une semaine. Refusé sans échappatoire pour tout ce qui a quitté
     * la boutique ou figure dans une tournée : un colis en route ne revient
     * pas en rayon parce qu'une case a été cochée. Une commande déjà payée est
     * refusée mais peut être forcée — l'agent confirme alors qu'un
     * remboursement est à faire.
     */
    /**
     * GET /admin/orders/attention
     *
     * La file « À traiter » : ce qui a dépassé son délai, le plus ancien
     * d'abord. Chaque ligne porte son motif et ce qu'il y a à faire — un
     * compteur sans le pourquoi obligerait à ouvrir les commandes une par une.
     *
     * Les règles vivent dans OrderAttention, la seule à connaître les délais.
     */
    public function attention(Request $request): JsonResponse
    {
        $motifs = [];
        $lignes = [];

        foreach (OrderAttention::query()->get() as $order) {
            $raisons = OrderAttention::for($order);

            if ($raisons === []) {
                continue;
            }

            foreach ($raisons as $raison) {
                $motifs[$raison['code']] = ($motifs[$raison['code']] ?? 0) + 1;
            }

            $lignes[] = [
                'order'     => (new OrderResource($order))->toArray($request),
                'attention' => $raisons,
                // Le plus grave de la commande, pour trier et colorer la ligne.
                'level'     => collect($raisons)->contains('level', 'critical') ? 'critical' : 'warning',
                'since'     => collect($raisons)->min('since'),
            ];
        }

        // Le plus ancien dépassement en tête : c'est lui qui risque de coûter
        // une cliente.
        usort($lignes, fn ($a, $b) => $a['since'] <=> $b['since']);

        return response()->json([
            'data'   => $lignes,
            'counts' => $motifs,
            'total'  => count($lignes),
        ]);
    }

    /**
     * POST /admin/orders/bulk-archive
     *
     * Range les commandes réglées : elles quittent la liste de travail sans
     * quitter la base. Rien n'est supprimé, tout reste consultable par l'onglet
     * « Archivées » et par la recherche.
     *
     * Une commande encore due n'est jamais archivée. La cacher serait le
     * meilleur moyen de l'oublier — et c'est exactement ce que l'écran essaie
     * d'éviter. Le refus est donc ferme, sans « quand même » : il suffit
     * d'achever la commande, puis de l'archiver.
     */
    public function bulkArchive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $archivees = [];
        $ecartees  = [];

        foreach (Order::whereIn('id', $data['order_ids'])->get() as $order) {
            // Déjà archivée : rien à faire, et la compter comme écartée
            // ferait croire à un problème.
            if ($order->archived_at) {
                continue;
            }

            $raisons = $this->archiveBlockers($order);

            if ($raisons !== []) {
                $ecartees[] = [
                    'id'        => $order->id,
                    'number'    => $order->number,
                    'reasons'   => $raisons,
                    'forceable' => false,
                ];
                continue;
            }

            // Par le modèle, comme les autres gestes de masse : le journal
            // retient qui a rangé quoi.
            $order->update(['archived_at' => now()]);
            $archivees[] = $order->id;
        }

        return response()->json([
            'updated'  => $archivees,
            'rejected' => $ecartees,
            'message'  => count($archivees) . ' commande(s) archivée(s).'
                . ($ecartees !== [] ? ' ' . count($ecartees) . ' écartée(s).' : ''),
        ]);
    }

    /**
     * POST /admin/orders/bulk-unarchive
     *
     * Remet des commandes dans la liste de travail. Aucun garde-fou : sortir
     * une commande de l'archive ne fait que la rendre visible.
     */
    public function bulkUnarchive(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
        ]);

        $sorties = [];

        foreach (Order::whereIn('id', $data['order_ids'])->whereNotNull('archived_at')->get() as $order) {
            $order->update(['archived_at' => null]);
            $sorties[] = $order->id;
        }

        return response()->json([
            'updated'  => $sorties,
            'rejected' => [],
            'message'  => count($sorties) . ' commande(s) remise(s) dans la liste.',
        ]);
    }

    /**
     * Ce qui empêche d'archiver une commande.
     *
     * Archiver, c'est déclarer l'affaire close. Tant qu'il reste quelque chose
     * à faire — préparer, livrer, encaisser, fixer des frais — la commande doit
     * rester sous les yeux de la boutique.
     *
     * @return array<int, string>
     */
    private function archiveBlockers(Order $order): array
    {
        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            return [];
        }

        if ($order->status !== 'delivered') {
            return ['Commande encore en cours (' . OrderStatusLabel::for($order) . ').'];
        }

        $raisons = [];

        // Livrée mais pas encaissée : l'argent est encore dû, et une commande
        // rangée ne se réclame plus.
        if (! $order->paid_at) {
            $raisons[] = 'Livrée mais pas encore payée.';
        }

        if (ShippingFeeRequirement::isPending($order)) {
            $raisons[] = 'Frais de livraison pas encore fixés.';
        }

        return $raisons;
    }

    public function bulkCancel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'force'       => 'sometimes|boolean',
        ]);

        $force     = $request->boolean('force');
        $cancelled = [];
        $rejected  = [];

        $orders     = Order::whereIn('id', $data['order_ids'])->get();
        $roundCodes = app(\Modules\Order\Services\DeliveryRoundService::class)
            ->openRoundCodes($orders->pluck('id')->all());

        foreach ($orders as $order) {
            if ($order->status === 'cancelled') {
                continue;
            }

            if ($order->status === 'refunded') {
                $rejected[] = $this->rejection($order, [$this->closedReason($order)], false);
                continue;
            }

            if (in_array($order->status, ['shipped', 'delivered'], true)) {
                $rejected[] = $this->rejection($order, [
                    ($order->status === 'shipped' ? 'Déjà expédiée' : 'Déjà livrée')
                    . " : l'annulation se décide depuis la fiche de la commande.",
                ], false);
                continue;
            }

            if (isset($roundCodes[$order->id])) {
                $rejected[] = $this->rejection($order, [
                    "Prévue dans la tournée {$roundCodes[$order->id]} : à régler depuis l'écran des tournées.",
                ], false);
                continue;
            }

            if (! $force && $order->paid_at) {
                $rejected[] = $this->rejection($order, [
                    'Déjà payée : un remboursement sera à faire.',
                ], true);
                continue;
            }

            // Passe par le service : c'est lui qui remet le stock en rayon et
            // prévient l'équipe.
            $this->orderService->updateStatus($order, 'cancelled');
            $cancelled[] = ['id' => $order->id, 'number' => $order->number];
        }

        return response()->json([
            'cancelled' => $cancelled,
            'rejected'  => $rejected,
            'message'   => $this->bulkMessage(count($cancelled), 'annulée(s), articles remis en stock', 'Aucune commande annulée.'),
        ]);
    }

    /** « 3 commande(s) annulée(s)… », ou une phrase entière quand rien n'a été fait. */
    private function bulkMessage(int $count, string $done, string $none): string
    {
        return $count > 0 ? "{$count} commande(s) {$done}." : $none;
    }

    /** @return array{id:int, number:string, reasons:array<int,string>, forceable:bool} */
    private function rejection(Order $order, array $reasons, bool $forceable): array
    {
        return [
            'id'        => $order->id,
            'number'    => $order->number,
            'reasons'   => $reasons,
            'forceable' => $forceable,
        ];
    }

    private function closedReason(Order $order): string
    {
        return 'Commande ' . ($order->status === 'cancelled' ? 'annulée' : 'remboursée') . '.';
    }

    /**
     * POST /admin/orders/{order}/generate-tracking
     * Génère un numéro de suivi unique pour aider le staff (pas d'enregistrement).
     */
    public function generateTracking(Order $order): JsonResponse
    {
        return response()->json([
            'tracking_number' => Order::generateTrackingNumber(),
        ]);
    }

    /**
     * POST /admin/orders/{order}/mark-paid
     * Marquer la commande comme payée (cash, virement, COD validé, …).
     */
    public function markPaid(Order $order): OrderResource
    {
        $this->recordPayment($order);

        return new OrderResource($order->fresh(['user', 'items']));
    }

    /**
     * Enregistre le règlement d'une commande. Partagé par le marquage à
     * l'unité et le marquage en masse : les deux doivent laisser la commande
     * dans le même état.
     */
    private function recordPayment(Order $order): void
    {
        if ($order->paid_at) {
            return;
        }

        $attributs = ['paid_at' => now()];

        // Sur une expédition, le paiement vaut confirmation : c'est lui
        // qu'on attendait. Les passerelles le font déjà de leur côté ;
        // l'agent qui saisit un virement Wave devait, lui, rebasculer le
        // statut à la main — et l'oubli laissait la commande « en
        // attente » alors que l'argent était encaissé.
        //
        // Abidjan garde son parcours : on y encaisse à la livraison, la
        // confirmation n'y dépend pas du règlement.
        if ($order->status === 'pending' && ShippingFeeRequirement::applies($order)) {
            $attributs['status'] = 'confirmed';
        }

        $order->update($attributs);
    }

    /**
     * POST /admin/orders/{order}/payment-link
     * Génère un lien de paiement à envoyer au client (WhatsApp/email).
     * Préfère GeniusPay (page checkout hébergée) s'il est configuré,
     * sinon retombe sur CinetPay.
     */
    public function paymentLink(Order $order, GeniusPayService $geniuspay, CinetPayService $cinetpay): JsonResponse
    {
        if ($order->paid_at) {
            return response()->json([
                'message' => 'Commande déjà payée.',
            ], 422);
        }

        // Un lien de paiement encaisse le total : même exigence que la
        // notification, sinon la cliente règle un montant incomplet.
        if (ShippingFeeRequirement::isPending($order)) {
            return response()->json([
                'code'    => 'shipping_fee_required',
                'message' => ShippingFeeRequirement::message($order),
            ], 422);
        }

        try {
            if ($geniuspay->isConfigured()) {
                $result = $geniuspay->createCheckoutLink($order);
                return response()->json([
                    'url'      => $result['checkout_url'] ?? $result['payment_url'] ?? null,
                    'provider' => 'geniuspay',
                    'message'  => 'Lien de paiement GeniusPay généré.',
                ]);
            }

            $result = $cinetpay->initiate($order);
            return response()->json([
                'url'      => $result['payment_url'] ?? null,
                'token'    => $result['payment_token'] ?? null,
                'provider' => 'cinetpay',
                'message'  => 'Lien de paiement généré.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Impossible de générer le lien de paiement.',
            ], 500);
        }
    }
}
