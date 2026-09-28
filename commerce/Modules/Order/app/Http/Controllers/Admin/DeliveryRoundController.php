<?php

namespace Modules\Order\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Modules\Order\Exceptions\NoShippableOrdersException;
use Modules\Order\Exceptions\RoundNotPointedException;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\DeliveryRoundOrder;
use Modules\Order\Models\Order;
use Modules\Order\Services\DeliveryRoundService;
use Modules\Order\Services\InvoiceService;
use Modules\Order\Support\DeliveryRoundPresenter;

/**
 * Les tournées, côté boutique.
 *
 * Une tournée se crée en un geste depuis l'aperçu avant export : l'agent coche
 * ses commandes, et reçoit en retour un code, la feuille de route, et vingt
 * commandes passées en « expédiée ».
 */
class DeliveryRoundController extends Controller implements HasMiddleware
{
    /**
     * Rôle porté par les livreurs. Distinct du rôle `courier` du module
     * Delivery, qui s'authentifie contre une autre table et ne partage pas
     * l'espace d'identifiants de `users`.
     */
    private const COURIER_ROLE = 'livreur';

    /**
     * Domaine des adresses de service, repris de la création d'utilisateur :
     * la colonne est unique et non nulle, rien n'y transite, et `.local` n'est
     * pas routable — aucun message ne peut partir par accident.
     */
    private const INTERNAL_DOMAIN = 'boutique.local';

    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'rounds.view' => ['index', 'show', 'sheet', 'couriers'],
            'rounds.edit' => ['store', 'dispatch', 'destroy', 'pointLine', 'close', 'storeCourier'],
        ]);
    }

    public function __construct(
        private readonly DeliveryRoundService $rounds,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * POST /admin/delivery-rounds
     * Crée la tournée à partir des commandes cochées et les expédie.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_ids'   => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,id',
            'label'       => 'nullable|string|max:120',
            'courier_id'  => 'nullable|integer|exists:users,id',
            'notes'       => 'nullable|string|max:500',
            // Même échappatoire que l'expédition unitaire : l'agent voit ce
            // qui bloque et assume de passer outre.
            'force'       => 'sometimes|boolean',
            // Prépare sans faire partir : la sélection est figée et le code
            // attribué, les colis restent en boutique.
            'draft'       => 'sometimes|boolean',
        ]);

        if (isset($data['courier_id']) && ! $this->estLivreur((int) $data['courier_id'])) {
            return response()->json([
                'code'    => 'not_a_courier',
                'message' => "Cet utilisateur n'est pas un livreur.",
            ], 422);
        }

        try {
            $resultat = $this->rounds->create(
                orderIds:  $data['order_ids'],
                label:     $data['label'] ?? null,
                courierId: $data['courier_id'] ?? null,
                force:     $request->boolean('force'),
                notes:     $data['notes'] ?? null,
                draft:     $request->boolean('draft'),
            );
        } catch (NoShippableOrdersException $e) {
            // 422 avec le détail : l'agent doit pouvoir corriger sans deviner.
            return response()->json([
                'code'     => 'no_shippable_orders',
                'message'  => $e->getMessage(),
                'rejected' => $e->rejected,
            ], 422);
        }

        return response()->json([
            'data'     => $this->format($resultat['round'], detailed: true),
            'rejected' => $resultat['rejected'],
        ], 201);
    }

    /**
     * POST /admin/delivery-rounds/{code}/dispatch
     * Fait partir une tournée préparée : affecte le livreur et expédie.
     */
    public function dispatch(Request $request, string $code): JsonResponse
    {
        $data = $request->validate([
            'courier_id' => 'nullable|integer|exists:users,id',
            'force'      => 'sometimes|boolean',
        ]);

        $round = $this->parCode($code);

        if (isset($data['courier_id']) && ! $this->estLivreur((int) $data['courier_id'])) {
            return response()->json([
                'code'    => 'not_a_courier',
                'message' => "Cet utilisateur n'est pas un livreur.",
            ], 422);
        }

        try {
            $resultat = $this->rounds->dispatch(
                round:     $round,
                courierId: $data['courier_id'] ?? null,
                force:     $request->boolean('force'),
            );
        } catch (NoShippableOrdersException $e) {
            return response()->json([
                'code'     => 'no_shippable_orders',
                'message'  => $e->getMessage(),
                'rejected' => $e->rejected,
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'code'    => 'round_already_dispatched',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data'     => $this->format($resultat['round'], detailed: true),
            'rejected' => $resultat['rejected'],
        ]);
    }

    /**
     * GET /admin/delivery-rounds
     * Liste des tournées, la plus récente d'abord. `search` accepte le code
     * (« TR-2808-A », « tr2808a ») ou le libellé.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:60',
            'status' => 'nullable|in:draft,dispatched,closed',
            'limit'  => 'nullable|integer|min:5|max:100',
        ]);

        $rounds = DeliveryRound::query()
            ->with('courier:id,name')
            ->withCount('lines')
            ->withSum('lines', 'amount_expected')
            ->withItemsCount()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('search'), fn ($q) => $this->rechercher($q, $request->search))
            // Un brouillon n'a pas de date de départ : trier sur elle seule le
            // renverrait en fin de liste, alors que c'est la tournée du matin
            // qu'on vient chercher ici.
            ->orderByRaw('COALESCE(dispatched_at, created_at) DESC')
            ->limit((int) $request->get('limit', 30))
            ->get();

        return response()->json([
            'data' => $rounds->map(fn ($r) => $this->format($r))->all(),
        ]);
    }

    /**
     * GET /admin/delivery-rounds/{code}
     * Une tournée et ses livraisons, retrouvée par son code.
     */
    public function show(string $code): JsonResponse
    {
        return response()->json([
            'data' => $this->format($this->parCode($code), detailed: true),
        ]);
    }

    /**
     * GET /admin/delivery-rounds/{code}/sheet
     * Réimpression de la feuille de route — le livreur a perdu la sienne, ou
     * la boutique veut son exemplaire. Rejoue exactement les commandes de la
     * tournée, pas les filtres qui avaient servi à la composer.
     */
    public function sheet(string $code): \Symfony\Component\HttpFoundation\Response
    {
        $round = $this->parCode($code);

        $txt = $this->invoiceService->generateByZoneTxt([
            'order_ids' => $round->lines->pluck('order_id')->all(),
            'label'     => $round->label,
            'round'     => $this->enteteTournee($round),
        ]);

        return response($txt, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'
                . $this->invoiceService->filenameByRoundTxt($round->code) . '"',
        ]);
    }

    /**
     * PATCH /admin/delivery-rounds/{code}/lines/{line}
     * Pointe une livraison au retour : livrée, échouée ou reportée.
     */
    public function pointLine(Request $request, string $code, int $line): JsonResponse
    {
        $round = $this->parCode($code);

        // Une tournée clôturée est un compte arrêté. Rouvrir une ligne après
        // coup ferait diverger l'écran et la caisse déjà comptée.
        if ($round->isClosed()) {
            return response()->json([
                'code'    => 'round_closed',
                'message' => 'Cette tournée est clôturée : son pointage ne peut plus être modifié.',
            ], 422);
        }

        $data = $request->validate([
            'status'           => 'required|in:' . implode(',', DeliveryRoundOrder::OUTCOMES),
            'amount_collected' => 'nullable|numeric|min:0',
            'payment_method'   => 'nullable|in:' . implode(',', Order::PAYMENT_METHODS_POS),
            // Un échec sans motif ne se rattrape pas : trois jours plus tard,
            // personne ne se souvient pourquoi la cliente n'a pas été livrée.
            'reason'           => 'nullable|required_if:status,failed|string|max:500',
        ]);

        // Une tournée qui n'est pas partie n'a rien à pointer : les colis sont
        // encore en boutique.
        if ($round->status === DeliveryRound::STATUS_DRAFT) {
            return response()->json([
                'code'    => 'round_not_dispatched',
                'message' => "Cette tournée n'est pas encore partie : confirmez son départ avant de pointer.",
            ], 422);
        }

        $ligne = $round->lines->firstWhere('id', $line);
        abort_if($ligne === null, 404, 'Cette livraison ne fait pas partie de la tournée.');

        $this->rounds->pointLine(
            line:          $ligne,
            outcome:       $data['status'],
            collected:     isset($data['amount_collected']) ? (float) $data['amount_collected'] : null,
            paymentMethod: $data['payment_method'] ?? null,
            reason:        $data['reason'] ?? null,
            operatorId:    $request->user()?->id,
        );

        return response()->json([
            'data' => $this->format($this->parCode($code), detailed: true),
        ]);
    }

    /**
     * POST /admin/delivery-rounds/{code}/close
     * Arrête les comptes de la tournée.
     */
    public function close(string $code): JsonResponse
    {
        $round = $this->parCode($code);

        if ($round->isClosed()) {
            return response()->json([
                'code'    => 'round_closed',
                'message' => 'Cette tournée est déjà clôturée.',
            ], 422);
        }

        if ($round->status === DeliveryRound::STATUS_DRAFT) {
            return response()->json([
                'code'    => 'round_not_dispatched',
                'message' => "Cette tournée n'est pas partie : elle s'abandonne, elle ne se clôture pas.",
            ], 422);
        }

        try {
            $this->rounds->close($round);
        } catch (RoundNotPointedException $e) {
            return response()->json([
                'code'      => 'round_not_pointed',
                'message'   => $e->getMessage(),
                'remaining' => $e->remaining,
            ], 422);
        }

        return response()->json([
            'data' => $this->format($this->parCode($code), detailed: true),
        ]);
    }

    /**
     * DELETE /admin/delivery-rounds/{code}
     * Abandonne une tournée préparée.
     *
     * Réservé aux brouillons : rien n'est parti, rien n'a été encaissé, il n'y
     * a donc aucune trace comptable à préserver. Le geste rend surtout leur
     * liberté aux commandes, qu'un brouillon réserve — sans quoi une tournée
     * préparée puis oubliée les garderait prisonnières d'une sortie qui n'aura
     * jamais lieu.
     */
    public function destroy(string $code): JsonResponse
    {
        $round = $this->parCode($code);

        if ($round->status !== DeliveryRound::STATUS_DRAFT) {
            return response()->json([
                'code'    => 'round_dispatched',
                'message' => 'Cette tournée est partie : elle se pointe et se clôture, elle ne s\'efface pas.',
            ], 422);
        }

        $round->lines()->delete();
        $round->delete();

        return response()->json(['message' => "Tournée {$round->code} abandonnée."]);
    }

    /**
     * GET /admin/delivery-rounds/couriers
     * Les livreurs qu'on peut affecter à une tournée.
     *
     * Servi ici, et non par la liste des utilisateurs : préparer une tournée
     * relève de `rounds.view`, pas de `users.view` — que l'agent n'a pas, et ne
     * doit pas avoir pour autant.
     */
    public function couriers(): JsonResponse
    {
        $couriers = User::role(self::COURIER_ROLE)
            ->orderBy('name')
            ->get(['id', 'name', 'phone'])
            ->map(fn (User $u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'phone' => $u->phone,
            ]);

        return response()->json(['data' => $couriers]);
    }

    /**
     * POST /admin/delivery-rounds/couriers
     * Crée un livreur sans quitter l'écran des tournées.
     *
     * Un nouveau livreur se présente le matin, la tournée est prête, et la
     * boutique n'a pas les droits sur les comptes : sans ce geste, il faut
     * attendre la gérante pour faire partir des colis déjà emballés.
     *
     * Le rôle n'est pas un paramètre — il vaut `livreur`, toujours. C'est ce
     * qui rend l'ouverture acceptable : `rounds.edit` crée des livreurs, pas
     * des comptes d'administration.
     */
    public function storeCourier(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            // Un livreur n'a pas toujours de boîte aux lettres : il se connecte
            // par identifiant, déduit de son nom.
            'email'    => 'nullable|email|unique:users,email',
            'username' => 'nullable|string|max:50|alpha_dash|unique:users,username',
            'password' => ['required', Password::min(8)],
            'phone'    => 'nullable|string|max:30',
        ]);

        $identifiant = ! empty($data['username'])
            ? mb_strtolower($data['username'])
            : User::generateUsername($data['name']);

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'] ?? $identifiant . '@' . self::INTERNAL_DOMAIN,
            'username' => $identifiant,
            'password' => Hash::make($data['password']),
            'phone'    => $data['phone'] ?? null,
        ]);

        $user->syncRoles([self::COURIER_ROLE]);

        return response()->json([
            'data' => [
                'id'   => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                // Rendu à l'écran : c'est ce que la boutique dicte au livreur
                // pour sa première connexion.
                'username' => $user->username,
            ],
        ], 201);
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    /**
     * Recherche tolérante sur le code : il est lu sur une feuille froissée ou
     * dicté au téléphone. « tr2808a », « TR-2808-A » et « 2808a » doivent
     * tomber sur la même tournée.
     */
    private function rechercher($query, string $terme)
    {
        $nu = preg_replace('/[^A-Za-z0-9]/', '', $terme);

        return $query->where(function ($q) use ($terme, $nu) {
            $q->where('label', 'like', '%' . $terme . '%');

            if ($nu !== '') {
                // REPLACE côté SQL : le code est stocké avec ses tirets, la
                // saisie ne les a pas forcément.
                $q->orWhereRaw("REPLACE(code, '-', '') LIKE ?", ['%' . strtoupper($nu) . '%']);
            }
        });
    }

    /**
     * Cet utilisateur peut-il porter une tournée ?
     *
     * Affecter la gérante ou une vendeuse par un identifiant mal choisi ferait
     * apparaître une tournée dans un espace livreur qui n'est pas le sien.
     */
    private function estLivreur(int $userId): bool
    {
        return User::role(self::COURIER_ROLE)->whereKey($userId)->exists();
    }

    /** Retrouve une tournée par son code, tirets facultatifs. */
    private function parCode(string $code): DeliveryRound
    {
        $nu = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        $round = DeliveryRound::with(['lines.order.items', 'courier:id,name'])
            ->whereRaw("REPLACE(code, '-', '') = ?", [$nu])
            ->first();

        abort_if($round === null, 404, "Aucune tournée ne porte le code « {$code} ».");

        return $round;
    }

    /** @return array{code: string, courier: ?string, expected_total: float, count: int, items: int} */
    private function enteteTournee(DeliveryRound $round): array
    {
        return [
            'code'           => $round->code,
            'courier'        => $round->courier?->name,
            'expected_total' => (float) $round->lines->sum('amount_expected'),
            'count'          => $round->lines->count(),
            'items'          => (int) $round->lines->sum(fn ($l) => $l->order?->items->sum('quantity') ?? 0),
        ];
    }

    private function format(DeliveryRound $round, bool $detailed = false): array
    {
        return DeliveryRoundPresenter::round($round, $detailed);
    }
}
