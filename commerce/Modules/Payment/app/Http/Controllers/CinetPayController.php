<?php

namespace Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;
use Modules\Payment\Services\CinetPayService;

class CinetPayController extends Controller
{
    public function __construct(private readonly CinetPayService $cinetpay) {}

    /**
     * Webhook appelé par CinetPay quand un paiement est complété.
     * CinetPay POST: cpm_trans_id, cpm_site_id, cpm_amount, cpm_currency,
     *                cpm_payment_date, cpm_payment_time, cpm_error_message,
     *                cpm_result (00 = succès), signature, …
     */
    public function notify(Request $request): JsonResponse
    {
        Log::info('CinetPay webhook', $request->all());

        $transactionId = $request->input('cpm_trans_id');
        $result        = $request->input('cpm_result');       // "00" = succès
        $amount        = $request->input('cpm_amount');

        if (! $transactionId) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // Trouver la commande via le transaction_id stocké dans metadata
        $order = Order::whereJsonContains('metadata->cinetpay_transaction_id', $transactionId)->first();

        if (! $order) {
            Log::warning('CinetPay: commande introuvable', ['transaction_id' => $transactionId]);
            return response()->json(['status' => 'not_found'], 200);
        }

        if ($result === '00') {
            // Paiement réussi
            if ($order->status === 'pending') {
                Payment::create([
                    'order_id'       => $order->id,
                    'provider'       => 'cinetpay',
                    'status'         => 'completed',
                    'amount'         => $amount ?? $order->total,
                    'currency'       => 'XOF',
                    'transaction_id' => $transactionId,
                    'metadata'       => $request->all(),
                    'paid_at'        => now(),
                ]);

                $order->update([
                    'status'  => 'confirmed',
                    'paid_at' => now(),
                ]);

                Log::info('CinetPay: paiement confirmé', ['order' => $order->number]);
            }
        } else {
            // Paiement échoué ou annulé
            $order->update(['status' => 'payment_failed']);
            Log::warning('CinetPay: paiement échoué', [
                'order'  => $order->number,
                'result' => $result,
                'msg'    => $request->input('cpm_error_message'),
            ]);
        }

        // CinetPay attend toujours un 200
        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Endpoint pour que le frontend vérifie le statut d'une commande après retour CinetPay.
     */
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
