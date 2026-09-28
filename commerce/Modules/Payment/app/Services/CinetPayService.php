<?php

namespace Modules\Payment\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use App\Support\Shop;

class CinetPayService
{
    private string $apiKey;
    private string $siteId;
    private string $baseUrl = 'https://api-checkout.cinetpay.com/v2';

    public function __construct()
    {
        $this->apiKey  = (string) config('services.cinetpay.api_key');
        $this->siteId  = (string) config('services.cinetpay.site_id');
    }

    /**
     * Initie une transaction CinetPay et retourne l'URL de paiement.
     */
    public function initiate(Order $order): array
    {
        $transactionId = 'CMD-' . $order->number . '-' . time();

        $frontendUrl = config('services.cinetpay.return_base_url', config('app.url'));

        $payload = [
            'apikey'                 => $this->apiKey,
            'site_id'                => $this->siteId,
            'transaction_id'         => $transactionId,
            'amount'                 => (int) $order->total,
            'currency'               => 'XOF',
            'description'            => 'Commande ' . Shop::name() . ' ' . $order->number,
            'return_url'             => "{$frontendUrl}/payment/return?order={$order->number}",
            'notify_url'             => config('app.url') . '/api/payment/cinetpay/notify',
            'customer_name'          => $order->shipping_address['first_name'] ?? '',
            'customer_surname'       => $order->shipping_address['last_name'] ?? '',
            'customer_phone_number'  => $order->shipping_address['phone'] ?? '',
            'customer_email'         => $order->user->email ?? Shop::email(),
            'customer_address'       => $order->shipping_address['address_line1'] ?? 'Abidjan',
            'customer_city'          => $order->shipping_address['city'] ?? 'Abidjan',
            'customer_country'       => 'CI',
            'customer_state'         => 'CI',
            'customer_zip_code'      => '00225',
            'channels'               => 'ALL',   // Visa, Mastercard, Mobile Money, Wave…
            'lang'                   => 'fr',
            'metadata'               => $order->number,
        ];

        $response = Http::timeout(15)->post("{$this->baseUrl}/payment", $payload);

        if (! $response->successful()) {
            Log::error('CinetPay HTTP error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Erreur de connexion au service de paiement.');
        }

        $data = $response->json();

        // CinetPay renvoie code "201" (string) pour succès
        if (($data['code'] ?? '') !== '201') {
            Log::error('CinetPay API error', $data);
            throw new \RuntimeException($data['message'] ?? 'Erreur lors de l\'initialisation du paiement.');
        }

        // Enregistrer le transaction_id sur la commande pour retrouver le paiement au webhook
        $order->update(['metadata' => array_merge($order->metadata ?? [], [
            'cinetpay_transaction_id' => $transactionId,
            'cinetpay_token'          => $data['data']['payment_token'] ?? null,
        ])]);

        return [
            'payment_url'   => $data['data']['payment_url'],
            'payment_token' => $data['data']['payment_token'] ?? null,
            'transaction_id'=> $transactionId,
        ];
    }

    /**
     * Vérifie le statut d'un paiement CinetPay (appelé depuis le webhook).
     */
    public function verify(string $transactionId, string $token): array
    {
        $response = Http::timeout(15)->post("{$this->baseUrl}/payment/check", [
            'apikey'         => $this->apiKey,
            'site_id'        => $this->siteId,
            'transaction_id' => $transactionId,
            'token'          => $token,
        ]);

        return $response->json() ?? [];
    }
}
