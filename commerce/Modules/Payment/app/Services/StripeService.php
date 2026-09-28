<?php

namespace Modules\Payment\Services;

use Laravel\Cashier\Cashier;
use Modules\Order\Models\Order;

/**
 * Stripe Checkout (paiement one-off d'une commande) via Laravel Cashier.
 *
 * Stripe ne supporte pas le XOF : les commandes sont converties en EUR au
 * taux FIXE du franc CFA (1 EUR = 655.957 XOF) avant la facturation.
 */
class StripeService
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.stripe.secret'))
            && ! empty(config('services.stripe.key'));
    }

    /**
     * Crée une session Stripe Checkout et renvoie l'URL de paiement.
     */
    public function initiate(Order $order): array
    {
        $currency = strtolower((string) config('services.stripe.currency', 'eur'));
        $amount   = $this->toStripeAmount((float) $order->total, (string) $order->currency, $currency);

        $session = Cashier::stripe()->checkout->sessions->create([
            'mode'                 => 'payment',
            'client_reference_id'  => $order->number,
            'line_items'           => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => $currency,
                    'unit_amount'  => $amount,
                    'product_data' => [
                        'name' => 'Commande ' . $order->number,
                    ],
                ],
            ]],
            'metadata' => [
                'order_id'     => (string) $order->id,
                'order_number' => $order->number,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'order_id'     => (string) $order->id,
                    'order_number' => $order->number,
                ],
            ],
            'success_url' => $this->url(config('services.stripe.success_url'), $order->number),
            'cancel_url'  => $this->url(config('services.stripe.cancel_url'), $order->number),
        ]);

        // On garde l'id de session pour retrouver la commande au webhook / status.
        $order->update([
            'payment_method' => 'stripe',
            'metadata'       => array_merge($order->metadata ?? [], [
                'stripe_session_id' => $session->id,
            ]),
        ]);

        return [
            'provider'     => 'stripe',
            'payment_url'  => $session->url,
            'session_id'   => $session->id,
            'order_number' => $order->number,
        ];
    }

    /**
     * Convertit un montant en plus petite unité Stripe (centimes), avec
     * conversion XOF → devise cible si nécessaire.
     */
    public function toStripeAmount(float $amount, string $orderCurrency, string $targetCurrency): int
    {
        $orderCurrency = strtolower($orderCurrency);

        if ($orderCurrency !== 'xof') {
            // Déjà dans une devise Stripe (ex: EUR) → simple passage en centimes.
            return (int) round($amount * 100);
        }

        $rate = (float) config('services.stripe.xof_per_eur', 655.957);
        $eur  = $amount / $rate;

        return (int) round($eur * 100);
    }

    private function url(?string $template, string $orderNumber): string
    {
        $template ??= '';

        // Stripe exige {CHECKOUT_SESSION_ID} pour success_url si on veut l'id.
        return str_replace('{order_number}', $orderNumber, $template);
    }
}
