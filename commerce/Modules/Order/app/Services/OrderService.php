<?php

namespace Modules\Order\Services;

use App\Models\User;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Catalog\Models\StockMovement;
use Modules\Catalog\Services\StockLedger;
use Modules\Catalog\Support\WeeklyAllocationGuard;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Order\Data\OrderData;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Coupon\Models\Coupon;
use Modules\Coupon\Support\CouponDiscount;
use Modules\Payment\Models\Payment;
use Modules\Promo\Support\SellingPrice;
use Modules\Order\Events\OrderCancelled;
use Modules\Order\Events\OrderPlaced;
use Modules\Order\Events\OrderShipped;
use Modules\Order\Support\AbidjanZone;
use Modules\Order\Exceptions\DuplicateOrderException;
use Modules\Order\Support\DuplicateOrderCheck;
use Illuminate\Validation\ValidationException;
use Modules\Order\Services\OrderCreation\CartValidator;
use Modules\Order\Services\OrderCreation\DiscountCalculator;
use Modules\Order\Services\OrderCreation\OrderBuilder;
use Modules\Order\Services\OrderCreation\OrderItemsCreator;
use Modules\Order\Services\OrderCreation\PinManager;
use Modules\Order\Services\OrderCreation\ShippingResolver;
use Modules\Order\Services\OrderCreation\StockManager;
use Modules\Order\Support\HomeZone;

class OrderService
{
    /**
     * Relations à charger pour afficher les lignes d'une commande côté
     * boutique : nom, prix… et surtout la vignette, qui vit sur le produit.
     */
    private const ITEM_RELATIONS = ['items.product.coverImage', 'items.variant'];

    public function __construct(
        private readonly CartValidator      $cartValidator,
        private readonly PinManager         $pinManager,
        private readonly DiscountCalculator $discountCalculator,
        private readonly OrderBuilder       $orderBuilder,
        private readonly OrderItemsCreator  $orderItemsCreator,
        private readonly StockManager       $stockManager,
        private readonly ShippingResolver   $shippingResolver,
        private readonly StockLedger       $stockLedger,
    ) {}

    public function getOrdersForUser(User $user, ?string $status = null, int $perPage = 15): LengthAwarePaginator
    {
        // Les vignettes sont chargées d'avance : sans ça, chaque ligne de chaque
        // commande déclenchait sa propre requête sur les images.
        $query = $user->orders()->with(self::ITEM_RELATIONS)->latest();

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    public function getOrderForUser(User $user, string $number): Order
    {
        return $user->orders()
            ->where('number', $number)
            ->with([...self::ITEM_RELATIONS, 'payments'])
            ->firstOrFail();
    }

    public function createFromCart(User $user, OrderData $data): Order
    {
        // Try to get the DB cart first
        $cart = $this->cartValidator->validateAndGetCart($user);

        // Determine which item source we'll use
        $hasCartItems    = $cart !== null;
        $hasPayloadItems = ! $hasCartItems && ! empty($data->items);

        if (! $hasCartItems && ! $hasPayloadItems) {
            throw new \RuntimeException('Panier vide');
        }

        // PIN retiré du process de commande : plus de génération/affichage de code PIN.
        // (L'infrastructure PIN reste dispo pour la connexion via PinController.)
        $generatedPin = null;

        // Code saisi au checkout : il n'était posé que sur le panier depuis le
        // tiroir. Tapé dans le tunnel, il s'affichait comme une réduction que
        // la commande ne portait jamais — le panier ignorait son existence.
        if ($hasCartItems && $data->coupon_code && ! $cart->coupon_code) {
            $cart->update(['coupon_code' => strtoupper($data->coupon_code)]);
            $cart->refresh();
        }

        // ── Happy path: DB cart has items ───────────────────────────────────
        if ($hasCartItems) {
            $this->guardAgainstDuplicate($user, $data, $cart);

            return DB::transaction(function () use ($cart, $user, $data, $generatedPin) {
                $discountInfo = $this->discountCalculator->calculateDiscount($cart, $cart->subtotal);

                $order = $this->orderBuilder->buildOrder($user, $cart, $data, $discountInfo);
                $this->orderItemsCreator->createFromCartItems($order, $cart);
                $this->stockManager->updateStock($cart, $order);

                $cart->items()->delete();
                $cart->update([
                    'coupon_code'        => null,
                    'abandoned_at'       => null,
                    'reminder_1_sent_at' => null,
                    'reminder_3_sent_at' => null,
                ]);

                $order->load('items');
                $order->generated_pin = $generatedPin;

                // ── Notification admin (après transaction) ──────────────────
                event(new OrderPlaced($order));

                return $order;
            });
        }

        // ── Fallback path: DB cart was empty, use payload items ─────────────
        $resolvedItems = $this->resolvePayloadItems($data->items);

        if ($resolvedItems->isEmpty()) {
            throw new \RuntimeException('Panier vide');
        }

        // Contrôle de disponibilité (évite la survente / stock négatif).
        $dejaRetenu = [];
        foreach ($resolvedItems as $item) {
            if ($item['track_stock'] && $item['available'] < $item['quantity']) {
                throw new \RuntimeException(
                    "Stock insuffisant pour « {$item['product_name']} » (disponible : {$item['available']}, demandé : {$item['quantity']})."
                );
            }

            // Quota hebdomadaire de la boutique en ligne : la commande rapide
            // est un parcours du site, elle y est soumise comme le panier. Les
            // lignes d'un même article se cumulent, comme dans StockManager.
            $refus = app(WeeklyAllocationGuard::class)->rejection(
                $item['product_id'],
                $item['product_name'],
                (int) $item['quantity'],
                $dejaRetenu[$item['product_id']] ?? 0,
            );

            if ($refus !== null) {
                throw new \RuntimeException($refus);
            }

            $dejaRetenu[$item['product_id']] = ($dejaRetenu[$item['product_id']] ?? 0) + (int) $item['quantity'];
        }

        // Règle métier : « À la livraison » réservé à Abidjan.
        // Hors Abidjan, on n'expédie que ce qui est déjà payé → prépaiement obligatoire.
        $method = strtolower((string) $data->payment_method);
        if (in_array($method, ['cod', 'delivery'], true)
            && ! AbidjanZone::isAbidjan($data->shipping_address->toArray())) {
            throw ValidationException::withMessages([
                'payment_method' => ['Le paiement à la livraison est réservé à ' . HomeZone::label() . '. ' . HomeZone::outsideLabel() . ', veuillez régler votre commande d\'avance (Wave, Orange Money ou carte).'],
            ]);
        }

        $this->guardAgainstDuplicate($user, $data, null);

        return DB::transaction(function () use ($user, $data, $resolvedItems, $generatedPin) {
            $subtotal = (float) $resolvedItems->sum(fn($i) => $i['unit_price'] * $i['quantity']);

            // Code promo de la commande rapide : recalculé ici sur les lignes
            // réelles, jamais repris du front. Même règle que le panier en base
            // — ciblage et exclusion des articles déjà remisés compris.
            [$coupon, $discount] = $this->resolveCoupon($data->coupon_code, $resolvedItems, $subtotal);

            // Frais de livraison résolus depuis la zone de livraison (source de
            // vérité). Zone introuvable → 0 + shipping_unknown : les agents
            // renseignent les frais manuellement.
            $shipping     = $this->shippingResolver->resolve($data->shipping_address->toArray());
            $shippingCost = $shipping['cost'];
            $total        = max(0, $subtotal - $discount + $shippingCost);

            $metadata = [];
            if ($shipping['unknown'])   { $metadata['shipping_unknown'] = true; }
            if ($shipping['zone_name']) { $metadata['shipping_zone']    = $shipping['zone_name']; }

            $order = Order::create([
                'number'           => Order::generateNumber(),
                'user_id'          => $user->id,
                'status'           => 'pending',
                'subtotal'         => $subtotal,
                'shipping_cost'    => $shippingCost,
                'discount_amount'  => $discount,
                'total'            => $total,
                'currency'         => 'XOF',
                'shipping_address' => $data->shipping_address->toArray(),
                'billing_address'  => ($data->billing_address ?? $data->shipping_address)->toArray(),
                'shipping_method'  => $data->shipping_method,
                'payment_method'   => $data->payment_method,
                'coupon_code'      => $coupon?->code,
                'customer_note'    => $data->customer_note,
                'metadata'         => $metadata ?: null,
            ]);

            // Enregistrement de paiement — seulement si un moyen est connu.
            // Hors zone, il ne l'est pas encore (payments.provider est NOT NULL) :
            // la ligne sera créée quand l'agent fixera le mode de règlement.
            if ($data->payment_method) {
                Payment::create([
                    'order_id' => $order->id,
                    'provider' => $data->payment_method,
                    'status'   => 'pending',
                    'amount'   => $total,
                    'currency' => 'XOF',
                ]);
            }

            foreach ($resolvedItems as $item) {
                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $item['product_id'],
                    'variant_id'    => $item['variant_id'],
                    'product_name'  => $item['product_name'],
                    'variant_label' => $item['variant_label'],
                    'sku'           => $item['sku'],
                    'quantity'        => $item['quantity'],
                    'promotion_id'    => $item['promotion_id'] ?? null,
                    'unit_price'      => $item['unit_price'],
                    'discount_amount' => ($item['discount'] ?? 0) * $item['quantity'],
                    'total_price'     => $item['unit_price'] * $item['quantity'],
                ]);

                // Sortie de stock enregistrée : le compteur et l'historique
                // bougent ensemble, sinon la commande resterait invisible dans
                // le registre des mouvements.
                $this->stockLedger->out(
                    $item['product'],
                    $item['variant'],
                    $item['quantity'],
                    StockMovement::TYPE_SALE,
                    [
                        'reference' => $order,
                        'reason'    => "Commande {$order->number}",
                    ],
                );
            }

            $order->load('items');
            $order->generated_pin = $generatedPin;

            // ── Notification admin ──────────────────────────────────────────
            event(new OrderPlaced($order));

            return $order;
        });
    }

    /**
     * Coupon applicable à des lignes hors panier en base, et son montant.
     *
     * Un code sans effet — expiré, épuisé, nominatif d'une autre cliente, ou
     * ciblé sur des articles absents — est ignoré en silence plutôt que de faire
     * échouer la commande : la cliente a déjà eu le refus explicite à la saisie,
     * et perdre la vente ici serait le pire des deux.
     *
     * @param  Collection<int, array{product: mixed, variant: mixed, unit_price: float, quantity: int}>  $lines
     * @return array{0: Coupon|null, 1: float}
     */
    private function resolveCoupon(?string $code, Collection $lines, float $subtotal): array
    {
        if (! $code) {
            return [null, 0.0];
        }

        $coupon = Coupon::where('code', strtoupper($code))->first();

        if (! $coupon || ! $coupon->isValid($subtotal)) {
            return [null, 0.0];
        }

        $discount = CouponDiscount::forLines($coupon, $lines->all())['discount'];

        if ($discount <= 0) {
            return [null, 0.0];
        }

        $coupon->increment('used_count');

        return [$coupon, $discount];
    }

    /** Load products/variants from DB and normalise payload items. */
    /**
     * Stops on a similar order the customer already has coming.
     *
     * Runs last, once stock, weekly quota and payment rules have passed: asking
     * the customer to confirm a duplicate for an order that will be refused
     * anyway costs her two round trips and tells her nothing useful. Nothing is
     * written either way.
     *
     * Only on the shop's own paths — the counter (POS) sells face to face,
     * where nobody needs telling that the same order was placed this morning.
     *
     * @param  \Modules\Cart\Models\Cart|null  $cart  Null when the items come
     *         from the payload (quick order, or a cart already emptied).
     */
    private function guardAgainstDuplicate(User $user, OrderData $data, $cart): void
    {
        if ($data->confirm_duplicate) {
            return;
        }

        $lines = $cart
            ? $cart->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
            ])->all()
            : ($data->items ?? []);

        $existing = DuplicateOrderCheck::find(
            $user,
            $data->shipping_address->phone ?? $user->phone,
            $lines,
        );

        if ($existing) {
            throw new DuplicateOrderException($existing);
        }
    }

    private function resolvePayloadItems(array $payloadItems): Collection
    {
        $productIds = collect($payloadItems)->pluck('product_id')->unique();
        $variantIds = collect($payloadItems)->pluck('variant_id')->filter()->unique();

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = $variantIds->isNotEmpty()
            ? ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        return collect($payloadItems)
            ->filter(fn($i) => $products->has($i['product_id']))
            ->map(function ($i) use ($products, $variants) {
                $product = $products[$i['product_id']];
                $variant = isset($i['variant_id']) ? $variants->get($i['variant_id']) : null;
                $vente   = SellingPrice::resolve($product, $variant);

                return [
                    // Modèles conservés : le registre écrit sur ces instances,
                    // et les recharger plus bas lirait un compteur déjà bougé.
                    'product'       => $product,
                    'variant'       => $variant,
                    'product_id'    => $product->id,
                    'variant_id'    => $variant?->id,
                    'product_name'  => $product->name,
                    'variant_label' => $variant?->label,
                    'sku'           => $variant?->sku ?? $product->sku,
                    'quantity'      => (int) $i['quantity'],
                    // Prix effectif : ce chemin sert le parcours invité, qui
                    // enregistrait la commande au tarif plein — la remise était
                    // annoncée sur la boutique puis facturée nulle part, et le
                    // chiffre d'affaires s'en trouvait gonflé d'autant.
                    'unit_price'    => $vente['price'],
                    // Attribution de la vente à l'opération qui l'a permise.
                    'promotion_id'  => $vente['promotion_id'],
                    'discount'      => $vente['discount'],
                    'track_stock'   => (bool) $product->track_stock,
                    'available'     => (int) ($variant?->stock ?? $product->stock),
                ];
            })
            ->values();
    }

    public function updateStatus(Order $order, string $status, ?string $trackingNumber = null): Order
    {
        $previousStatus = $order->status;
        $attrs = ['status' => $status];

        if ($status === 'shipped' && ! $order->shipped_at) {
            $attrs['shipped_at'] = now();
        }
        if ($status === 'delivered' && ! $order->delivered_at) {
            $attrs['delivered_at'] = now();
        }
        if ($trackingNumber) {
            $attrs['tracking_number'] = $trackingNumber;
        }

        $order->update($attrs);

        // Annulation décidée par la boutique : les articles retournent en
        // rayon. Sans ça, une commande annulée retirait de la vente un stock
        // qui n'avait jamais bougé physiquement. Le service protège lui-même
        // contre un double crédit si le statut est réappliqué.
        if ($status === 'cancelled' && $previousStatus !== 'cancelled') {
            app(OrderCancellation::class)->restoreStock($order);
        }

        $order = $order->fresh(['user', 'items']);

        // Notifier l'admin uniquement sur transition (évite les doublons).
        if ($previousStatus !== $status) {
            if ($status === 'shipped')   event(new OrderShipped($order));
            if ($status === 'cancelled') event(new OrderCancelled($order));
        }

        return $order;
    }

}
