<?php

namespace Modules\Payment\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;

class WaveService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => config('services.wave.base_url'),
            'headers'  => [
                'Authorization' => 'Bearer ' . config('services.wave.api_key'),
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);
    }

    /**
     * Crée une session de paiement Wave et retourne le wave_launch_url.
     */
    public function createCheckoutSession(Order $order): array
    {
        try {
            $response = $this->http->post('checkout/sessions', [
                'json' => [
                    'amount'           => (string) intval($order->total),
                    'currency'         => $order->currency ?? 'XOF',
                    'error_url'        => config('services.wave.error_url') . '&order=' . $order->number,
                    'success_url'      => config('services.wave.success_url') . '&order=' . $order->number,
                    'client_reference' => $order->number,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'provider'          => 'wave',
                'checkout_id'       => $data['id'],
                'wave_launch_url'   => $data['wave_launch_url'],
                'client_reference'  => $data['client_reference'],
                'amount'            => $data['amount'],
                'currency'          => $data['currency'],
                'payment_status'    => $data['payment_status'],
            ];
        } catch (ClientException $e) {
            $body = json_decode($e->getResponse()->getBody()->getContents(), true);
            Log::error('Wave checkout error', ['order' => $order->number, 'error' => $body]);
            throw new \RuntimeException($body['message'] ?? 'Erreur Wave lors de la création du paiement.');
        }
    }

    /**
     * Vérifie la signature HMAC du webhook Wave.
     * Wave envoie : X-Wave-Signature: sha256=<hmac>
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        $secret = config('services.wave.webhook_secret');
        if (empty($secret)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signatureHeader);
    }

    /**
     * Récupère les détails d'une session de checkout.
     */
    public function getCheckoutSession(string $checkoutId): array
    {
        $response = $this->http->get("checkout/sessions/{$checkoutId}");
        return json_decode($response->getBody()->getContents(), true);
    }
}
