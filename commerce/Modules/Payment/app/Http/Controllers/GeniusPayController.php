<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\GeniusPayService;

class GeniusPayController extends Controller
{
    public function __construct(private readonly GeniusPayService $geniuspay) {}

    /**
     * Webhook GeniusPay.
     * Headers : X-Webhook-Signature, X-Webhook-Timestamp, X-Webhook-Event
     * Signature = HMAC-SHA256(timestamp + "." + json_payload, webhook_secret)
     */
    public function notify(Request $request): JsonResponse
    {
        $secret = config('services.geniuspay.webhook_secret');

        // Vérification de signature (si un secret est configuré)
        if ($secret) {
            $signature = $request->header('X-Webhook-Signature');
            $timestamp = $request->header('X-Webhook-Timestamp');

            $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);

            if (!$signature || !hash_equals($expected, $signature)) {
                Log::warning('GeniusPay webhook: signature invalide');
                return response()->json(['detail' => 'Invalid signature'], 401);
            }
            // Protection rejeu : fenêtre de 5 min
            if (abs(time() - (int) $timestamp) > 300) {
                return response()->json(['detail' => 'Timestamp too old'], 400);
            }
        }

        Log::info('GeniusPay webhook', $request->all());

        $event = $request->input('event');
        $data  = $request->input('data', []);

        $reference   = $data['reference'] ?? null;
        $orderNumber = $data['metadata']['order_id'] ?? null;
        $status      = $data['status'] ?? null;

        // Retrouver la commande : par metadata.order_id puis par référence stockée
        $order = null;
        if ($orderNumber) {
            $order = Order::where('number', $orderNumber)->first();
        }
        if (!$order && $reference) {
            $order = Order::whereJsonContains('metadata->geniuspay_reference', $reference)->first();
        }

        if (!$order) {
            Log::warning('GeniusPay: commande introuvable', compact('reference', 'orderNumber'));
            return response()->json(['status' => 'not_found'], 200);
        }

        $isSuccess = $event === 'payment.success' || $status === 'completed';

        if ($isSuccess) {
            if (!$order->paid_at) {
                Payment::create([
                    'order_id'       => $order->id,
                    'provider'       => 'geniuspay',
                    'status'         => 'completed',
                    'amount'         => $data['amount'] ?? $order->total,
                    'currency'       => $data['currency'] ?? 'XOF',
                    'transaction_id' => $reference,
                    'metadata'       => $data,
                    'paid_at'        => now(),
                ]);
                $order->update(['status' => 'confirmed', 'paid_at' => now()]);
                Log::info('GeniusPay: paiement confirmé', ['order' => $order->number]);
            }
        } elseif (in_array($event, ['payment.failed', 'payment.cancelled', 'payment.expired'], true)) {
            if (!$order->paid_at) {
                $order->update(['status' => 'cancelled']);
            }
            Log::warning('GeniusPay: paiement non abouti', ['order' => $order->number, 'event' => $event]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Le frontend vérifie le statut après retour de la page de paiement.
     * Re-synchronise depuis GeniusPay si la commande est encore pending.
     */
    public function status(string $orderNumber): JsonResponse
    {
        $order = Order::where('number', $orderNumber)->first();
        if (!$order) {
            return response()->json(['status' => 'not_found'], 404);
        }

        // Si pas encore payée, on interroge GeniusPay (au cas où le webhook traîne)
        if (!$order->paid_at && !empty($order->metadata['geniuspay_reference'])) {
            try {
                $remote = $this->geniuspay->verify($order->metadata['geniuspay_reference']);
                if (($remote['status'] ?? null) === 'completed') {
                    Payment::firstOrCreate(
                        ['order_id' => $order->id, 'transaction_id' => $remote['reference'] ?? null],
                        [
                            'provider' => 'geniuspay',
                            'status'   => 'completed',
                            'amount'   => $remote['amount'] ?? $order->total,
                            'currency' => $remote['currency'] ?? 'XOF',
                            'metadata' => $remote,
                            'paid_at'  => now(),
                        ]
                    );
                    $order->update(['status' => 'confirmed', 'paid_at' => now()]);
                }
            } catch (\Throwable $e) {
                Log::info('GeniusPay status check failed: ' . $e->getMessage());
            }
            $order->refresh();
        }

        return response()->json([
            'order_number' => $order->number,
            'status'       => $order->status,
            'paid'         => (bool) $order->paid_at,
        ]);
    }
}
