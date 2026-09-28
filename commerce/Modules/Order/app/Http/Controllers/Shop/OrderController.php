<?php

namespace Modules\Order\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Order\Data\OrderData;
use Modules\Order\Http\Requests\StoreOrderRequest;
use Modules\Order\Http\Resources\OrderResource;
use Modules\Order\Services\InvoiceService;
use Modules\Order\Exceptions\DuplicateOrderException;
use Modules\Order\Support\DuplicateOrderCheck;
use Modules\Order\Services\OrderCancellation;
use Modules\Order\Services\OrderService;
use Modules\Payment\Services\GeniusPayService;
use Modules\Payment\Services\PaymentService;

class OrderController extends Controller
{
    // Méthodes qui déclenchent toujours un redirect de paiement côté client
    private const ONLINE_PAYMENT_METHODS = ['geniuspay', 'card', 'stripe'];

    public function __construct(
        private readonly OrderService   $orderService,
        private readonly InvoiceService $invoiceService,
        private readonly PaymentService $paymentService,
    ) {}

    public function index(Request $request): ResourceCollection
    {
        $status  = $request->query('status');
        $perPage = (int) ($request->query('per_page', 15));

        return OrderResource::collection(
            $this->orderService->getOrdersForUser($request->user(), $status ?: null, $perPage)
        );
    }

    public function show(Request $request, string $number): OrderResource
    {
        return new OrderResource(
            $this->orderService->getOrderForUser($request->user(), $number)
        );
    }

    public function invoice(Request $request, string $number): \Symfony\Component\HttpFoundation\Response
    {
        $order = $this->orderService->getOrderForUser($request->user(), $number);

        $pdf = $this->invoiceService->generate($order);

        return $pdf->download($this->invoiceService->filename($order));
    }

    /**
     * POST /orders/{number}/pay
     * (Re)génère un lien de paiement en ligne pour une commande non réglée.
     * Permet au client de finaliser/reprendre un paiement abandonné.
     */
    public function pay(Request $request, string $number, GeniusPayService $geniuspay): JsonResponse
    {
        $order = $this->orderService->getOrderForUser($request->user(), $number);

        if ($order->paid_at) {
            return response()->json(['message' => 'Cette commande est déjà payée.'], 422);
        }

        // Le client a explicitement demandé à payer : on utilise GeniusPay dès
        // qu'il est configuré (indépendamment du toggle checkout). La méthode
        // de la commande oriente le gateway (wave / orange_money), sinon page checkout.
        if (!$geniuspay->isConfigured()) {
            return response()->json([
                'message' => 'Le paiement en ligne n\'est pas disponible pour le moment.',
            ], 503);
        }

        $method = in_array($order->payment_method, ['wave', 'orange_money'], true)
            ? $order->payment_method
            : null;

        try {
            $payment = $method
                ? $geniuspay->initiate($order, $method)
                : $geniuspay->createCheckoutLink($order);

            return response()->json([
                'payment_url' => $payment['payment_url'] ?? $payment['checkout_url'] ?? null,
                'reference'   => $payment['reference']   ?? null,
                'provider'    => 'geniuspay',
            ]);
        } catch (\Throwable $e) {
            \Log::error('Customer pay init failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Impossible d\'initialiser le paiement. Réessayez ou contactez-nous.',
            ], 500);
        }
    }

    /**
     * POST /orders/{number}/cancel
     *
     * Annulation par la cliente, dans la fenêtre de rétractation. Le motif du
     * refus est renvoyé tel quel : il est déjà rédigé pour être lu par elle.
     */
    public function cancel(Request $request, string $number, OrderCancellation $cancellation): JsonResponse
    {
        $order = $this->orderService->getOrderForUser($request->user(), $number);

        try {
            $order = $cancellation->cancel($order, $request->input('reason'));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Votre commande a bien été annulée.',
            'order'   => new OrderResource($order->load(['items.product.coverImage', 'items.variant', 'payments'])),
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data = OrderData::fromValidated($request->validated());

        try {
            $order = $this->orderService->createFromCart($request->user(), $data);
        } catch (DuplicateOrderException $e) {
            // 409 : la commande n'est pas refusée, elle attend une réponse.
            // L'écran montre la commande déjà en cours, puis renvoie la même
            // requête avec `confirm_duplicate` si la cliente maintient.
            return response()->json([
                'message'               => $e->getMessage(),
                'requires_confirmation' => true,
                'duplicate_order'       => DuplicateOrderCheck::summary($e->existing),
            ], 409);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $payload = (new OrderResource($order))->toArray($request);

        if ($order->generated_pin) {
            $payload['generated_pin'] = $order->generated_pin;
        }

        // Méthodes en ligne : cinetpay/geniuspay toujours, + wave/orange_money
        // si GeniusPay est activé en admin (sinon elles restent "manuelles").
        $onlineMethods = self::ONLINE_PAYMENT_METHODS;
        if ($this->paymentService->geniusPayEnabled()) {
            $onlineMethods = array_merge($onlineMethods, ['wave', 'orange_money']);
        }

        if (in_array($data->payment_method, $onlineMethods, true)) {
            try {
                $paymentData = $this->paymentService->initiate($order, $data->payment_method);
                $payload['payment_url'] = $paymentData['payment_url'] ?? null;
            } catch (\Exception $e) {
                \Log::error('Payment initiation failed: ' . $e->getMessage());
                // Ne pas bloquer la commande — le client pourra réessayer
                $payload['payment_error'] = 'Impossible d\'initialiser le paiement. Contactez le support.';
            }
        }

        return response()->json($payload, 201);
    }
}
