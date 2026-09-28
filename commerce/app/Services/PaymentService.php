<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;

class PaymentService
{
    public function initiate(Order $order, string $method): array
    {
        return match ($method) {
            'wave'          => $this->initiateWave($order),
            'orange_money'  => $this->initiateOrangeMoney($order),
            'stripe'        => $this->initiateStripe($order),
            'bank_transfer' => $this->bankTransferInstructions($order),
            'cod'           => $this->cashOnDelivery($order),
            default         => throw new \InvalidArgumentException("Méthode inconnue : {$method}"),
        };
    }

    public function recordPayment(Order $order, string $provider, string $transactionId, array $metadata = []): Payment
    {
        $payment = Payment::create([
            'order_id'       => $order->id,
            'provider'       => $provider,
            'status'         => 'completed',
            'amount'         => $order->total,
            'currency'       => $order->currency,
            'transaction_id' => $transactionId,
            'metadata'       => $metadata,
            'paid_at'        => now(),
        ]);

        $order->update(['status' => 'confirmed', 'paid_at' => now()]);

        return $payment;
    }

    private function initiateWave(Order $order): array
    {
        // TODO: Intégrer l'API Wave
        return [
            'provider'    => 'wave',
            'redirect_url'=> config('services.wave.checkout_url') . '?amount=' . $order->total,
            'order_number'=> $order->number,
        ];
    }

    private function initiateOrangeMoney(Order $order): array
    {
        // TODO: Intégrer l'API Orange Money
        return [
            'provider'    => 'orange_money',
            'ussd_code'   => '#144#',
            'amount'      => $order->total,
            'order_number'=> $order->number,
        ];
    }

    private function initiateStripe(Order $order): array
    {
        // TODO: Intégrer Stripe SDK
        return [
            'provider'      => 'stripe',
            'client_secret' => null, // Stripe PaymentIntent
            'order_number'  => $order->number,
        ];
    }

    private function bankTransferInstructions(Order $order): array
    {
        return [
            'provider'    => 'bank_transfer',
            'instructions'=> config('payment.bank_transfer_instructions', []),
            'order_number'=> $order->number,
            'amount'      => $order->total,
        ];
    }

    private function cashOnDelivery(Order $order): array
    {
        return [
            'provider'    => 'cod',
            'message'     => 'Paiement à la livraison. Préparez la somme exacte.',
            'amount'      => $order->total,
            'order_number'=> $order->number,
        ];
    }
}
