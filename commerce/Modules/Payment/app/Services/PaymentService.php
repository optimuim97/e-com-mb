<?php

namespace Modules\Payment\Services;

use Modules\Admin\Models\Setting;
use Modules\Order\Models\Order;
use Modules\Payment\Models\Payment;

class PaymentService
{
    public function __construct(
        private readonly WaveService      $wave,
        private readonly GeniusPayService $geniuspay,
        private readonly StripeService    $stripe,
    ) {}

    public function initiate(Order $order, string $method): array
    {
        // Si GeniusPay est activé en admin, il gère wave + orange money + mtn
        if (in_array($method, ['wave', 'orange_money', 'mtn'], true) && $this->geniusPayEnabled()) {
            return $this->initiateGeniusPay($order, $method);
        }

        return match ($method) {
            'wave'          => $this->initiateWave($order),
            'orange_money'  => $this->initiateOrangeMoney($order),
            'mtn'           => $this->initiateMtn($order),
            'geniuspay'     => $this->initiateGeniusPay($order, 'wave'),
            'stripe', 'card'=> $this->initiateStripe($order),
            'bank_transfer' => $this->bankTransferInstructions($order),
            'cod'           => $this->cashOnDelivery($order),
            default         => throw new \InvalidArgumentException("Méthode inconnue : {$method}"),
        };
    }

    /** GeniusPay activé côté admin ET correctement configuré ? */
    public function geniusPayEnabled(): bool
    {
        return filter_var(Setting::get('payment_geniuspay_enabled', false), FILTER_VALIDATE_BOOLEAN)
            && $this->geniuspay->isConfigured();
    }

    private function initiateGeniusPay(Order $order, string $method): array
    {
        $result = $this->geniuspay->initiate($order, $method);

        return [
            'provider'     => 'geniuspay',
            'payment_url'  => $result['payment_url'],
            'reference'    => $result['reference'],
            'order_number' => $order->number,
        ];
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

    private function initiateMtn(Order $order): array
    {
        // Paiement manuel MTN MoMo (instructions affichées côté front).
        return [
            'provider'    => 'mtn',
            'ussd_code'   => '*133#',
            'amount'      => $order->total,
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
        if (! $this->stripe->isConfigured()) {
            throw new \RuntimeException('Stripe n\'est pas configuré.');
        }

        return $this->stripe->initiate($order);
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
