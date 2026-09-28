<?php

namespace Modules\Order\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Routing\Controllers\HasMiddleware;
use App\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;
use App\Models\WhatsAppMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Payment\Models\Payment;
use Modules\Promo\Support\SellingPrice;

/**
 * POS admin : permet à un agent de créer une commande pour un client
 * qui ne passe pas par le site (vente en personne, WhatsApp, téléphone).
 */
class PosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build(['pos.use' => ['parse', 'store']]);
    }

    public function __construct(
        private readonly \Modules\Order\Services\PosMessageParser $parser,
    ) {}

    /**
     * POST /admin/pos/parse
     * Analyse un message libre et retourne les produits + client détectés.
     */
    public function parse(Request $request): JsonResponse
    {
        $data = $request->validate(['message' => 'required|string|max:2000']);

        return response()->json($this->parser->parse($data['message']));
    }

    /**
     * POST /admin/pos/orders
     * Crée une commande POS. L'admin maîtrise prix, livraison et statut paiement.
     */
    public function store(Request $request, StockLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'customer'             => 'required|array',
            'customer.name'        => 'required|string|max:120',
            'customer.phone'       => 'nullable|string|max:30',
            'customer.email'       => 'nullable|email|max:160',
            'customer.commune'     => 'nullable|string|max:120',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|integer|exists:products,id',
            'items.*.variant_id'   => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'payment_status'       => 'required|in:paid,unpaid',
            'payment_method'       => 'nullable|in:'.implode(',', Order::PAYMENT_METHODS_POS),
            'shipping_cost'        => 'nullable|numeric|min:0',
            'discount_amount'      => 'nullable|numeric|min:0',
            'note'                 => 'nullable|string|max:500',
            // Renseigné quand la commande naît d'un message de la file
            // WhatsApp : le message est alors marqué traité et la commande
            // garde la trace de son origine.
            'whatsapp_message_id'  => 'nullable|integer|exists:whatsapp_messages,id',
        ]);

        // ── Résolution / création du client (par téléphone si fourni) ────────
        $user = null;
        if (! empty($data['customer']['phone'])) {
            $user = User::where('phone', $data['customer']['phone'])->first();
            if (! $user) {
                $slug  = Str::slug($data['customer']['phone']);
                $user  = User::create([
                    'name'     => $data['customer']['name'],
                    'phone'    => $data['customer']['phone'],
                    'email'    => $data['customer']['email'] ?? "pos-{$slug}@clients.local",
                    'password' => Hash::make(Str::random(24)),
                ]);
            }
        }

        [$firstName, $lastName] = $this->splitName($data['customer']['name']);

        $isPaid          = $data['payment_status'] === 'paid';
        $paymentMethod   = $data['payment_method'] ?? 'cash';
        $shippingCost    = (float) ($data['shipping_cost']   ?? 0);
        $discountAmount  = (float) ($data['discount_amount'] ?? 0);

        // Résolution + contrôle de disponibilité AVANT toute écriture (évite la
        // survente / stock négatif). Renvoie 422 si un article tracké manque.
        $resolved = $this->resolveItems($data['items']);

        // Plafond de remise au comptoir : la vendeuse garde une marge de
        // négociation, sans que la caisse soit ouverte. Les comptes habilités
        // à gérer les promotions passent outre.
        if ($depassement = $this->remiseHorsPlafond($resolved, $discountAmount, $request)) {
            return response()->json(['message' => $depassement], 422);
        }
        foreach ($resolved as $item) {
            if ($item['track_stock'] && $item['available'] < $item['quantity']) {
                return response()->json([
                    'message' => "Stock insuffisant pour « {$item['product_name']} » (disponible : {$item['available']}, demandé : {$item['quantity']}).",
                ], 422);
            }
        }

        $waMessage = ! empty($data['whatsapp_message_id'])
            ? WhatsAppMessage::find($data['whatsapp_message_id'])
            : null;

        $order = DB::transaction(function () use ($data, $user, $firstName, $lastName, $isPaid, $paymentMethod, $shippingCost, $discountAmount, $resolved, $waMessage, $request, $ledger) {
            $subtotal = (float) collect($resolved)->sum(fn($i) => $i['unit_price'] * $i['quantity']);
            $total    = max(0, $subtotal - $discountAmount + $shippingCost);

            $shippingAddress = array_filter([
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'phone'         => $data['customer']['phone']   ?? null,
                'email'         => $data['customer']['email']   ?? null,
                'address_line1' => $data['customer']['commune'] ?? 'Retrait / sur place',
                'commune'       => $data['customer']['commune'] ?? null,
                'city'          => 'Abidjan',
                'country'       => 'CI',
            ], fn($v) => $v !== null);

            $order = Order::create([
                'number'          => Order::generateNumber(),
                'user_id'         => $user?->id,
                'status'          => $isPaid ? 'confirmed' : 'pending',
                'subtotal'        => $subtotal,
                'shipping_cost'   => $shippingCost,
                'discount_amount' => $discountAmount,
                'total'           => $total,
                'currency'        => 'XOF',
                'shipping_address'=> $shippingAddress,
                'billing_address' => $shippingAddress,
                'shipping_method' => 'pos',
                'payment_method'  => $paymentMethod,
                'customer_note'   => $data['note'] ?? null,
                'admin_note'      => $waMessage
                    ? 'Commande saisie depuis un message WhatsApp'
                    : 'Commande créée via POS admin',
                // Canal d'origine : sans lui, une commande de comptoir et une
                // commande WhatsApp sont indiscernables une fois enregistrées,
                // et la part réelle de chaque canal reste invérifiable.
                'metadata'        => array_filter([
                    'channel'             => $waMessage ? 'whatsapp' : 'pos',
                    'whatsapp_message_id' => $waMessage?->id,
                ], fn ($v) => $v !== null),
                'paid_at'         => $isPaid ? now() : null,
            ]);

            foreach ($resolved as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id'],
                    'variant_id'    => $item['variant_id'],
                    'product_name'  => $item['product_name'],
                    'variant_label' => $item['variant_label'],
                    'sku'           => $item['sku'],
                    'quantity'      => $item['quantity'],
                    'promotion_id'    => $item['promotion_id'] ?? null,
                    'unit_price'      => $item['unit_price'],
                    'discount_amount' => ($item['discount'] ?? 0) * $item['quantity'],
                    'total_price'     => $item['unit_price'] * $item['quantity'],
                ]);

                // Une vente au comptoir sort du stock comme une autre : elle
                // doit apparaître dans le registre, sans quoi l'écart entre le
                // comptage et le compteur resterait inexplicable.
                $ledger->out(
                    $item['product'],
                    $item['variant'],
                    $item['quantity'],
                    StockMovement::TYPE_SALE,
                    [
                        'reference' => $order,
                        'reason'    => "Commande {$order->number} (caisse)",
                    ],
                );
            }

            // Enregistrement de paiement
            Payment::create([
                'order_id'       => $order->id,
                'provider'       => $paymentMethod,
                'status'         => $isPaid ? 'completed' : 'pending',
                'amount'         => $total,
                'currency'       => 'XOF',
                'transaction_id' => null,
                'paid_at'        => $isPaid ? now() : null,
            ]);

            // Le message quitte la file dans la même transaction que la
            // commande : le voir « traité » sans commande derrière, ou
            // l'inverse, ruinerait le décompte de la double saisie.
            $waMessage?->update([
                'status'     => WhatsAppMessage::STATUS_CONVERTED,
                'order_id'   => $order->id,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);

            $order->load('items');
            return $order;
        });

        // Intègre la commande aux notifications/stats admin
        event(new OrderPlaced($order));

        return response()->json(
            (new OrderResource($order->load(['user', 'items'])))->toArray($request),
            201
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveItems(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->unique();
        $variantIds = collect($items)->pluck('variant_id')->filter()->unique();

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = $variantIds->isNotEmpty()
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        return collect($items)
            ->filter(fn($i) => $products->has($i['product_id']))
            ->map(function ($i) use ($products, $variants) {
                $product = $products[$i['product_id']];
                $variant = isset($i['variant_id']) ? $variants->get($i['variant_id']) : null;
                $vente   = SellingPrice::resolve($product, $variant);

                return [
                    // Modèles conservés pour le registre : les recharger dans la
                    // boucle lirait un compteur déjà déplacé.
                    'product'       => $product,
                    'variant'       => $variant,
                    'product_id'    => $product->id,
                    'variant_id'    => $variant?->id,
                    'product_name'  => $product->name,
                    'variant_label' => $variant?->sku,
                    'sku'           => $variant?->sku ?? $product->sku,
                    'quantity'      => (int) $i['quantity'],
                    // Même prix qu'en ligne : une opération en cours vaut aussi
                    // au comptoir, sans quoi la cliente qui vient en boutique
                    // paie plus cher que sur le site. La remise saisie par
                    // l'agente vient éventuellement en plus, sous son plafond.
                    'unit_price'    => $vente['price'],
                    'promotion_id'  => $vente['promotion_id'],
                    'discount'      => $vente['discount'],
                    'track_stock'   => (bool) $product->track_stock,
                    'available'     => (int) ($variant?->stock ?? $product->stock),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Message d'erreur si la remise dépasse le plafond autorisé, sinon null.
     *
     * Le plafond est exprimé en pourcentage du sous-total : raisonner en
     * montant fixe n'aurait aucun sens entre un savon et une commande de
     * plusieurs articles.
     */
    private function remiseHorsPlafond(array $resolved, float $remise, Request $request): ?string
    {
        if ($remise <= 0) {
            return null;
        }

        // Qui gère les promotions décide aussi au comptoir.
        if ($request->user()?->can('promotions.edit')) {
            return null;
        }

        $plafond = (float) \Modules\Admin\Models\Setting::get('pos_max_discount_percent', 15);
        if ($plafond <= 0) {
            return null;   // 0 = pas de plafond configuré
        }

        $sousTotal = (float) collect($resolved)->sum(fn ($i) => $i['unit_price'] * $i['quantity']);
        if ($sousTotal <= 0) {
            return null;
        }

        $pourcentage = $remise / $sousTotal * 100;
        if ($pourcentage <= $plafond) {
            return null;
        }

        $max = number_format($sousTotal * $plafond / 100, 0, ',', ' ');

        return sprintf(
            'Remise trop élevée : %.1f %% pour un plafond de %s %%. Maximum autorisé : %s FCFA.',
            $pourcentage,
            rtrim(rtrim(number_format($plafond, 1, ',', ' '), '0'), ','),
            $max
        );
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', trim($name), 2);
        return [$parts[0] ?? $name, $parts[1] ?? ''];
    }
}
