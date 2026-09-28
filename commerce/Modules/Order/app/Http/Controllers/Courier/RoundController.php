<?php

namespace Modules\Order\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Order\Models\DeliveryRound;
use Modules\Order\Models\DeliveryRoundOrder;
use Modules\Order\Models\Order;
use Modules\Order\Services\DeliveryRoundService;
use Modules\Order\Support\DeliveryRoundPresenter;

/**
 * L'espace du livreur : ses tournées, et rien d'autre.
 *
 * Le pointage se fait désormais sur le terrain, à la porte de la cliente —
 * c'est là que l'information est juste. L'agent garde le même geste depuis la
 * boutique, pour les livreurs sans téléphone et pour corriger une saisie ; la
 * clôture, elle, reste à la boutique : arrêter les comptes, c'est compter la
 * caisse.
 *
 * Toutes les requêtes sont bornées à `courier_id = utilisateur connecté`.
 * C'est le seul garde-fou qui compte : la permission dit qu'on peut pointer,
 * elle ne dit pas quelles tournées. Un identifiant de tournée deviné ne doit
 * rien ouvrir.
 */
class RoundController extends Controller
{
    public function __construct(
        private readonly DeliveryRoundService $rounds,
    ) {}

    /**
     * GET /livreur/rounds
     * Les tournées du livreur connecté, les non clôturées d'abord.
     */
    public function index(Request $request): JsonResponse
    {
        $rounds = $this->siennes($request)
            ->with('courier:id,name')
            ->withCount('lines')
            ->withSum('lines', 'amount_expected')
            ->withItemsCount()
            // Une tournée en cours passe devant : c'est celle qu'il est en
            // train de faire. Les clôturées restent consultables en dessous.
            ->orderByRaw("CASE WHEN status = '" . DeliveryRound::STATUS_DISPATCHED . "' THEN 0 ELSE 1 END")
            ->latest('dispatched_at')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $rounds->map(fn ($r) => DeliveryRoundPresenter::round($r))->all(),
        ]);
    }

    /** GET /livreur/rounds/{code} */
    public function show(Request $request, string $code): JsonResponse
    {
        return response()->json([
            'data' => DeliveryRoundPresenter::round($this->sienne($request, $code), detailed: true),
        ]);
    }

    /**
     * PATCH /livreur/rounds/{code}/lines/{line}
     * Le geste du terrain : livré, échec ou report, devant la porte.
     */
    public function pointLine(Request $request, string $code, int $line): JsonResponse
    {
        $round = $this->sienne($request, $code);

        if ($round->isClosed()) {
            return response()->json([
                'code'    => 'round_closed',
                'message' => 'Cette tournée est clôturée : voyez avec la boutique.',
            ], 422);
        }

        $data = $request->validate([
            'status'           => 'required|in:' . implode(',', DeliveryRoundOrder::OUTCOMES),
            'amount_collected' => 'nullable|numeric|min:0',
            'payment_method'   => 'nullable|in:' . implode(',', Order::PAYMENT_METHODS_POS),
            'reason'           => 'nullable|required_if:status,failed|string|max:500',
        ]);

        $ligne = $round->lines->firstWhere('id', $line);
        abort_if($ligne === null, 404, 'Cette livraison ne fait pas partie de votre tournée.');

        $this->rounds->pointLine(
            line:          $ligne,
            outcome:       $data['status'],
            collected:     isset($data['amount_collected']) ? (float) $data['amount_collected'] : null,
            paymentMethod: $data['payment_method'] ?? null,
            reason:        $data['reason'] ?? null,
            operatorId:    $request->user()?->id,
        );

        return response()->json([
            'data' => DeliveryRoundPresenter::round($this->sienne($request, $code), detailed: true),
        ]);
    }

    /* ── Portée ──────────────────────────────────────────────────────────── */

    /**
     * Les tournées affectées à l'utilisateur connecté, une fois parties.
     *
     * Un brouillon peut porter un livreur avant son départ. Le lui montrer,
     * c'était lui permettre de pointer « livrée » une commande encore en
     * boutique : la commande passait livrée sans avoir jamais été expédiée.
     */
    private function siennes(Request $request)
    {
        return DeliveryRound::query()
            ->where('courier_id', $request->user()->id)
            ->where('status', '!=', DeliveryRound::STATUS_DRAFT);
    }

    /**
     * Une tournée à lui, par son code.
     *
     * Un 404 et non un 403 : le livreur n'a pas à apprendre qu'une tournée
     * existe ailleurs, et le message serait le même pour un code inventé.
     */
    private function sienne(Request $request, string $code): DeliveryRound
    {
        $nu = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));

        $round = $this->siennes($request)
            ->with(['lines.order.items', 'courier:id,name'])
            ->whereRaw("REPLACE(code, '-', '') = ?", [$nu])
            ->first();

        abort_if($round === null, 404, "Aucune tournée à votre nom ne porte le code « {$code} ».");

        return $round;
    }
}
