<?php

namespace Modules\Payment\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use App\Support\Shop;

/**
 * Intégration GeniusPay (https://pay.genius.ci).
 *
 * Auth : headers X-API-Key + X-API-Secret.
 * Deux usages :
 *   - createCheckoutLink(): lien de paiement hébergé (checkout_url) à
 *     envoyer au client (WhatsApp / email). Aucun payment_method imposé,
 *     le client choisit sur la page GeniusPay.
 *   - initiate(method): redirection directe vers un gateway précis
 *     (wave / orange_money) → renvoie payment_url.
 */
class GeniusPayService
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;

    /** Map des méthodes internes → payment_method GeniusPay */
    private const METHOD_MAP = [
        'wave'         => 'wave',
        'orange_money' => 'orange_money',
        'mtn'          => 'mtn_money',
        'mtn_money'    => 'mtn_money',
        'card'         => 'card',
    ];

    public function __construct()
    {
        $this->baseUrl   = rtrim((string) config('services.geniuspay.base_url') ?: 'https://pay.genius.ci/api/v1/merchant', '/');
        $this->apiKey    = (string) config('services.geniuspay.api_key');
        $this->apiSecret = (string) config('services.geniuspay.secret_key');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '';
    }

    /**
     * Lien de paiement hébergé (checkout_url) — méthode au choix du client.
     */
    public function createCheckoutLink(Order $order): array
    {
        return $this->createPayment($order, null);
    }

    /**
     * Paiement vers un gateway précis (wave / orange_money) → payment_url.
     */
    public function initiate(Order $order, string $method): array
    {
        $gpMethod = self::METHOD_MAP[$method] ?? null;
        return $this->createPayment($order, $gpMethod);
    }

    /**
     * Appel bas-niveau POST /payments.
     * $paymentMethod null → page checkout, sinon gateway direct.
     */
    private function createPayment(Order $order, ?string $paymentMethod): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('GeniusPay non configuré (clés manquantes).');
        }

        $frontendUrl = config('services.geniuspay.return_base_url', config('app.url'));
        $addr        = $order->shipping_address ?? [];

        $payload = [
            'amount'      => (int) $order->total,
            'currency'    => $order->currency ?: 'XOF',
            'description' => 'Commande ' . Shop::name() . ' ' . $order->number,
            'customer'    => array_filter([
                'name'    => trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')) ?: ($order->user->name ?? null),
                'email'   => $addr['email'] ?? $order->user->email ?? null,
                'phone'   => $addr['phone'] ?? $order->user->phone ?? null,
                'country' => $addr['country'] ?? 'CI',
            ]),
            'success_url' => "{$frontendUrl}/payment/return?order={$order->number}&status=success",
            'error_url'   => "{$frontendUrl}/payment/return?order={$order->number}&status=error",
            'metadata'    => [
                'order_id'     => $order->number,
                'order_db_id'  => $order->id,
            ],
        ];

        if ($paymentMethod) {
            $payload['payment_method'] = $paymentMethod;
        }

        $response = Http::withHeaders([
            'X-API-Key'    => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
            'Accept'       => 'application/json',
        ])->timeout(20)->post("{$this->baseUrl}/payments", $payload);

        if (!$response->successful()) {
            Log::error('GeniusPay HTTP error', ['status' => $response->status(), 'body' => $response->body()]);
            throw new \RuntimeException('Erreur de connexion au service de paiement GeniusPay.');
        }

        $json = $response->json();
        if (!($json['success'] ?? false) || empty($json['data'])) {
            Log::error('GeniusPay API error', $json ?? []);
            throw new \RuntimeException($json['message'] ?? 'Échec de l\'initialisation du paiement.');
        }

        $data = $json['data'];

        // Mémorise la référence GeniusPay sur la commande pour le webhook
        $order->update(['metadata' => array_merge($order->metadata ?? [], [
            'geniuspay_reference' => $data['reference'] ?? null,
            'geniuspay_gateway'   => $data['gateway']   ?? null,
        ])]);

        return [
            'provider'     => 'geniuspay',
            'reference'    => $data['reference']    ?? null,
            // payment_url = redirection gateway direct, checkout_url = page hébergée
            'payment_url'  => $data['payment_url']  ?? $data['checkout_url'] ?? null,
            'checkout_url' => $data['checkout_url'] ?? null,
            'amount'       => $data['amount']       ?? $order->total,
            'fees'         => $data['fees']         ?? null,
            'status'       => $data['status']       ?? 'pending',
            'order_number' => $order->number,
        ];
    }

    /**
     * Vérifie le statut d'un paiement via sa référence.
     */
    public function verify(string $reference): array
    {
        $response = Http::withHeaders([
            'X-API-Key'    => $this->apiKey,
            'X-API-Secret' => $this->apiSecret,
            'Accept'       => 'application/json',
        ])->timeout(15)->get("{$this->baseUrl}/payments/{$reference}");

        return $response->json()['data'] ?? [];
    }
}
