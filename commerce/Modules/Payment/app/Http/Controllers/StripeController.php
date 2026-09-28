<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Stripe\Webhook;

class StripeController extends Controller
{
    /**
     * Webhook Stripe. Événement clé : checkout.session.completed.
     * La signature est vérifiée via le secret du webhook (STRIPE_WEBHOOK_SECRET).
     */
    public function notify(Request $request): JsonResponse
    {
        $secret = (string) config('services.stripe.webhook_secret');

        // Vérification de signature (anti-spoof)
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                $secret,
            );
        } catch (\Throwable $e) {
            Log::warning('Stripe webhook: signature invalide', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'invalid_signature'], 400);
        }

        return match ($event->type) {
            'checkout.session.completed' => $this->handleCompleted($event->data->object),
            'checkout.session.expired',
            'payment_intent.payment_failed' => $this->handleFailed($event->data->object),
            default => response()->json(['status' => 'ignored'], 200),
        };
    }

    private function handleCompleted(object $session): JsonResponse
    {
        $order = $this->resolveOrder($session);

        if (! $order) {
            Log::warning('Stripe: commande introuvable', ['session' => $session->id ?? null]);
            return response()->json(['status' => 'not_found'], 200);
        }

        // Paiement réellement encaissé ?
        if (($session->payment_status ?? null) !== 'paid') {
            return response()->json(['status' => 'unpaid'], 200);
        }

        if ($order->status === 'pending') {
            Payment::create([
                'order_id'       => $order->id,
                'provider'       => 'stripe',
                'status'         => 'completed',
                'amount'         => $order->total,
                'currency'       => $order->currency,
                'transaction_id' => $session->payment_intent ?? $session->id,
                'metadata'       => [
                    'stripe_session_id'   => $session->id ?? null,
                    'stripe_payment_intent' => $session->payment_intent ?? null,
                    'amount_total'        => $session->amount_total ?? null,
                    'currency_charged'    => $session->currency ?? null,
                ],
                'paid_at'        => now(),
            ]);

            $order->update(['status' => 'confirmed', 'paid_at' => now()]);
            Log::info('Stripe: paiement confirmé', ['order' => $order->number]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function handleFailed(object $object): JsonResponse
    {
        $order = $this->resolveOrder($object);

        if ($order && $order->status === 'pending') {
            $order->update(['status' => 'payment_failed']);
            Log::warning('Stripe: paiement échoué/expiré', ['order' => $order->number]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /** Retrouve la commande depuis les metadata ou la session stockée. */
    private function resolveOrder(object $object): ?Order
    {
        $orderId      = $object->metadata->order_id ?? null;
        $orderNumber  = $object->metadata->order_number ?? ($object->client_reference_id ?? null);
        $sessionId    = $object->id ?? null;

        if ($orderId && $order = Order::find($orderId)) {
            return $order;
        }

        if ($orderNumber && $order = Order::where('number', $orderNumber)->first()) {
            return $order;
        }

        if ($sessionId) {
            return Order::whereJsonContains('metadata->stripe_session_id', $sessionId)->first();
        }

        return null;
    }

    /** Le frontend vérifie le statut de la commande après retour du Checkout. */
    public function status(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('number', $orderNumber)->first();

        if (! $order) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'order_number' => $order->number,
            'status'       => $order->status,
            'paid'         => in_array($order->status, ['confirmed', 'shipped', 'delivered']),
        ]);
    }
}
