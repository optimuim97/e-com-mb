<?php

namespace Modules\Order\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Order\Models\Order;

/**
 * Warns a customer who is about to order what she already has coming.
 *
 * It happens on a phone: the confirmation is slow, the page is reloaded, the
 * WhatsApp agent re-enters an order the customer had already placed on the
 * site. Two identical parcels go out, one comes back, and someone has to
 * explain the second charge.
 *
 * So this is a warning, never a refusal: a customer really may order the same
 * cream twice in a week. Confirming lets it straight through — the point is
 * that she is told, not that she is stopped.
 *
 * "Similar" means: the same customer, an order that has not been delivered,
 * and at least 90 % of the items in common. Quantities are ignored — ordering
 * two of what you already have coming is the very mistake worth catching.
 */
final class DuplicateOrderCheck
{
    /** Share of items in common above which two orders are deemed alike. */
    public const THRESHOLD = 0.9;

    /**
     * Statuses of an order still owed to the customer.
     *
     * Delivered, cancelled and refunded orders are settled: ordering the same
     * thing again is a repeat purchase, not a double.
     */
    private const OPEN = ['pending', 'confirmed', 'processing', 'shipped'];

    /**
     * The most recent open order that looks like the one being placed.
     *
     * @param  array<int, array{product_id: int|string, variant_id?: int|string|null}>  $lines
     */
    public static function find(?User $user, ?string $phone, array $lines): ?Order
    {
        $wanted = self::keys($lines);

        if ($wanted === [] || (! $user && ! $phone)) {
            return null;
        }

        return Order::query()
            ->with(['items.product.coverImage', 'items.variant'])
            ->whereIn('status', self::OPEN)
            ->where(function (Builder $q) use ($user, $phone) {
                // The account is the identity: a quick order resolves its
                // customer by phone number, and the checkout is signed in.
                if ($user) {
                    $q->where('user_id', $user->id);
                }

                // Same number under another account — an order taken by an
                // agent, then placed again by the customer herself.
                if ($phone) {
                    $q->orWhere('shipping_address->phone', $phone);
                }
            })
            ->latest('created_at')
            // A handful is enough: the customer is warned about her latest
            // similar order, not given a list.
            ->limit(20)
            ->get()
            ->first(fn (Order $order) => self::similarity(
                $wanted,
                self::keys($order->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                ])->all()),
            ) >= self::THRESHOLD);
    }

    /**
     * Share of items two orders have in common, between 0 and 1.
     *
     * Measured against the longer of the two: ordering one cream while a
     * ten-item order is on its way is not a duplicate, even though that cream
     * is in both.
     *
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     */
    public static function similarity(array $a, array $b): float
    {
        $largest = max(count($a), count($b));

        if ($largest === 0) {
            return 0.0;
        }

        return count(array_intersect($a, $b)) / $largest;
    }

    /**
     * Item lines as comparable keys: a product, and the variant if there is one.
     *
     * @param  array<int, array{product_id: int|string, variant_id?: int|string|null}>  $lines
     * @return array<int, string>
     */
    public static function keys(array $lines): array
    {
        return array_values(array_unique(array_map(
            fn (array $line) => $line['product_id'] . ':' . ($line['variant_id'] ?? ''),
            $lines,
        )));
    }

    /**
     * Line thumbnail: the variant's own image when it has one, the product
     * cover otherwise. Null rather than a stand-in — the screen draws its own.
     */
    private static function thumbnail($item): ?string
    {
        $variantImage = trim((string) ($item->variant?->image ?? ''));

        if ($variantImage !== '') {
            return asset('storage/' . $variantImage);
        }

        return $item->product?->coverImage?->url;
    }

    /**
     * What the customer is shown to recognise her order: when, where it stands,
     * and what is in it.
     *
     * @return array<string, mixed>
     */
    public static function summary(Order $order): array
    {
        return [
            'number'       => $order->number,
            'status'       => $order->status,
            'status_label' => OrderStatusLabel::for($order),
            'created_at'   => $order->created_at,
            'total'        => $order->total,
            'currency'     => $order->currency,
            'items'        => $order->items->map(fn ($item) => [
                'name'     => $item->product_name,
                'variant'  => $item->variant_label,
                'quantity' => $item->quantity,
                // La vignette vaut mieux qu'une ligne de texte : la cliente
                // reconnaît ce qu'elle a commandé sans avoir à lire.
                'image'    => self::thumbnail($item),
            ])->all(),
        ];
    }
}
