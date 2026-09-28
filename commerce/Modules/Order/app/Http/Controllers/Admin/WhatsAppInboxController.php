<?php

namespace Modules\Order\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Modules\Order\Models\Order;
use Modules\Order\Services\PosMessageParser;

/**
 * File des messages WhatsApp Business à traiter.
 *
 * C'est le poste de travail de la double saisie : tant que la boutique vend
 * par WhatsApp, la conversation reste la source de vérité et le système doit
 * la rattraper, message par message. L'écran ne prétend pas remplacer
 * WhatsApp — il garantit qu'aucune commande passée là-bas ne reste absente
 * d'ici, et rend l'écart mesurable au lieu de supposé.
 *
 * Trois issues pour un message, jamais d'entre-deux :
 *   · converti  — une commande a été créée depuis ce message (via la caisse) ;
 *   · rattaché  — la commande existait déjà, on établit le lien ;
 *   · écarté    — ce n'était pas une commande, avec le motif.
 */
class WhatsAppInboxController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'whatsapp.view'    => ['index', 'show', 'parse', 'reconciliation'],
            'whatsapp.convert' => ['ignore', 'link', 'reopen'],
        ]);
    }

    public function __construct(
        private readonly PosMessageParser $parser,
    ) {}

    /**
     * GET /admin/whatsapp/messages
     *
     * Les messages à traiter d'abord, du plus ancien au plus récent : une
     * file d'attente se dépile par le bas, sinon les premiers arrivés sont
     * aussi les derniers servis.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'    => 'nullable|in:' . implode(',', WhatsAppMessage::STATUSES),
            'search'    => 'nullable|string|max:120',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'per_page'  => 'nullable|integer|min:5|max:100',
        ]);

        $query = WhatsAppMessage::query()->with(['order:id,number,total,status', 'handler:id,name']);

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('body', 'like', "%{$search}%")
                    ->orWhere('from_phone', 'like', "%{$search}%")
                    ->orWhere('contact_name', 'like', "%{$search}%");
            });
        }

        if ($from = $request->date('date_from')) {
            $query->where('received_at', '>=', $from);
        }
        if ($to = $request->date('date_to')) {
            $query->where('received_at', '<=', $to->endOfDay());
        }

        $messages = $query
            ->orderByRaw("CASE WHEN status = '" . WhatsAppMessage::STATUS_PENDING . "' THEN 0 ELSE 1 END")
            ->orderBy('received_at')
            ->paginate($request->integer('per_page') ?: 25)
            ->through(fn (WhatsAppMessage $m) => $this->present($m));

        return response()->json([
            'data'  => $messages->items(),
            'meta'  => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
            ],
            'counts' => $this->counts(),
        ]);
    }

    /** GET /admin/whatsapp/messages/{message} */
    public function show(WhatsAppMessage $message): JsonResponse
    {
        $message->load(['order:id,number,total,status', 'handler:id,name']);

        return response()->json($this->present($message, withPayload: true));
    }

    /**
     * POST /admin/whatsapp/messages/{message}/parse
     *
     * Pré-remplit la caisse à partir du texte du message et signale la
     * cliente si son numéro est déjà connu. Aucune écriture : l'agent garde
     * la main, le parseur ne fait que lui épargner la ressaisie.
     */
    public function parse(WhatsAppMessage $message): JsonResponse
    {
        if (! $message->body) {
            return response()->json([
                'message' => 'Ce message ne contient pas de texte à analyser (' . $message->type . ').',
            ], 422);
        }

        $resultat = $this->parser->parse($message->body);
        $cliente  = $message->customer();

        // Le numéro WhatsApp est plus fiable que celui deviné dans le texte :
        // c'est celui depuis lequel la cliente écrit réellement.
        $resultat['customer']['phone'] = $cliente?->phone ?? $message->from_phone;
        $resultat['customer']['name'] ??= $cliente?->name ?? $message->contact_name;

        $resultat['known_customer'] = $cliente ? [
            'id'           => $cliente->id,
            'name'         => $cliente->name,
            'phone'        => $cliente->phone,
            'email'        => $cliente->email,
            'orders_count' => $cliente->orders()->count(),
        ] : null;

        return response()->json($resultat);
    }

    /**
     * POST /admin/whatsapp/messages/{message}/link
     *
     * Rattache un message à une commande déjà saisie. Le cas courant en
     * période de double saisie : la commande a été créée à la caisse avant
     * qu'on ouvre la file, et sans ce lien elle compterait comme non traitée.
     */
    public function link(Request $request, WhatsAppMessage $message): JsonResponse
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
        ]);

        $message->update([
            'status'     => WhatsAppMessage::STATUS_CONVERTED,
            'order_id'   => $data['order_id'],
            'handled_by' => $request->user()->id,
            'handled_at' => now(),
        ]);

        // La commande porte son origine : sans elle, impossible de savoir
        // plus tard quelle part du chiffre venait de WhatsApp.
        $order = Order::find($data['order_id']);
        $order->update([
            'metadata' => array_merge($order->metadata ?? [], [
                'channel'             => 'whatsapp',
                'whatsapp_message_id' => $message->id,
            ]),
        ]);

        return response()->json($this->present($message->fresh(['order', 'handler'])));
    }

    /** POST /admin/whatsapp/messages/{message}/ignore */
    public function ignore(Request $request, WhatsAppMessage $message): JsonResponse
    {
        $data = $request->validate([
            'reason' => 'nullable|string|max:180',
        ]);

        $message->update([
            'status'        => WhatsAppMessage::STATUS_IGNORED,
            'handling_note' => $data['reason'] ?? null,
            'handled_by'    => $request->user()->id,
            'handled_at'    => now(),
        ]);

        return response()->json($this->present($message->fresh(['order', 'handler'])));
    }

    /**
     * POST /admin/whatsapp/messages/{message}/reopen
     *
     * Remet un message dans la file. Écarter par erreur doit rester réparable
     * en un clic, sinon l'agent hésitera à écarter et la file ne se videra pas.
     */
    public function reopen(WhatsAppMessage $message): JsonResponse
    {
        $message->update([
            'status'        => WhatsAppMessage::STATUS_PENDING,
            'handling_note' => null,
            'handled_by'    => null,
            'handled_at'    => null,
            'order_id'      => null,
        ]);

        return response()->json($this->present($message->fresh(['order', 'handler'])));
    }

    /**
     * GET /admin/whatsapp/reconciliation
     *
     * Le tableau de contrôle de la double saisie, jour par jour : combien de
     * messages sont arrivés, combien ont donné une commande, combien
     * attendent encore. C'est ce chiffre — et non une impression — qui dira
     * si le système peut devenir la source de vérité.
     */
    public function reconciliation(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
        ]);

        $from = $request->date('date_from') ?? now()->subDays(13)->startOfDay();
        $to   = ($request->date('date_to') ?? now())->endOfDay();

        $messages = WhatsAppMessage::query()
            ->whereBetween('received_at', [$from, $to])
            ->selectRaw('DATE(received_at) as jour')
            ->selectRaw('COUNT(*) as recus')
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as convertis", [WhatsAppMessage::STATUS_CONVERTED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as ecartes", [WhatsAppMessage::STATUS_IGNORED])
            ->selectRaw("SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as en_attente", [WhatsAppMessage::STATUS_PENDING])
            ->groupBy('jour')
            ->orderBy('jour')
            ->get()
            ->keyBy('jour');

        // Toutes les commandes du jour, pas seulement celles issues de la
        // file : une journée où le site vend seul reste une journée réussie.
        $commandes = Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as jour')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN shipping_method = 'pos' THEN 1 ELSE 0 END) as pos")
            ->selectRaw('SUM(total) as chiffre')
            ->groupBy('jour')
            ->get()
            ->keyBy('jour');

        $jours = [];
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $cle = $date->format('Y-m-d');
            $m   = $messages->get($cle);
            $c   = $commandes->get($cle);

            $jours[] = [
                'date'              => $cle,
                'messages_recus'    => (int) ($m->recus ?? 0),
                'convertis'         => (int) ($m->convertis ?? 0),
                'ecartes'           => (int) ($m->ecartes ?? 0),
                'en_attente'        => (int) ($m->en_attente ?? 0),
                'commandes_total'   => (int) ($c->total ?? 0),
                'commandes_caisse'  => (int) ($c->pos ?? 0),
                'chiffre'           => (float) ($c->chiffre ?? 0),
            ];
        }

        $recus     = array_sum(array_column($jours, 'messages_recus'));
        $traites   = array_sum(array_column($jours, 'convertis')) + array_sum(array_column($jours, 'ecartes'));

        return response()->json([
            'periode' => ['du' => $from->toDateString(), 'au' => $to->toDateString()],
            'jours'   => $jours,
            'totaux'  => [
                'messages_recus'  => $recus,
                'traites'         => $traites,
                'en_attente'      => array_sum(array_column($jours, 'en_attente')),
                // Le seul indicateur qui décide de la bascule : une file
                // traitée à 100 % plusieurs jours d'affilée signifie que plus
                // rien ne se perd entre WhatsApp et le système.
                'taux_traitement' => $recus > 0 ? round($traites / $recus * 100, 1) : null,
            ],
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** @return array<string, int> */
    private function counts(): array
    {
        $counts = WhatsAppMessage::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'pending'   => (int) $counts->get(WhatsAppMessage::STATUS_PENDING, 0),
            'converted' => (int) $counts->get(WhatsAppMessage::STATUS_CONVERTED, 0),
            'ignored'   => (int) $counts->get(WhatsAppMessage::STATUS_IGNORED, 0),
        ];
    }

    /** @return array<string, mixed> */
    private function present(WhatsAppMessage $message, bool $withPayload = false): array
    {
        return [
            'id'            => $message->id,
            'from_phone'    => $message->from_phone,
            'contact_name'  => $message->contact_name,
            'type'          => $message->type,
            'body'          => $message->body,
            'status'        => $message->status,
            'handling_note' => $message->handling_note,
            'received_at'   => $message->received_at,
            'handled_at'    => $message->handled_at,
            'handler'       => $message->handler ? ['id' => $message->handler->id, 'name' => $message->handler->name] : null,
            'order'         => $message->order ? [
                'id'     => $message->order->id,
                'number' => $message->order->number,
                'total'  => $message->order->total,
                'status' => $message->order->status,
            ] : null,
            ...($withPayload ? ['payload' => $message->payload] : []),
        ];
    }
}
