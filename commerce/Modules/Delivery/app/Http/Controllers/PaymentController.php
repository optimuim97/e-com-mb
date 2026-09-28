<?php

namespace Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use Modules\Delivery\Services\PaymentService;
use Modules\Delivery\Data\PaymentData;
use Modules\Delivery\Http\Requests\RecordPaymentRequest;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    /**
     * Enregistrer un paiement de livraison
     */
    public function store(int $id, RecordPaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $payment = $this->paymentService->recordPayment(
            deliveryId: $id,
            courierId: auth()->id(),
            data: $validated
        );

        return response()->json([
            'message' => 'Paiement enregistré avec succès',
            'data' => PaymentData::fromModel($payment)
        ], 201);
    }

    /**
     * Voir les paiements d'une livraison
     */
    public function show(int $id): JsonResponse
    {
        $payments = $this->paymentService->getPaymentsForDelivery($id);

        return response()->json([
            'data' => $payments->map(fn($p) => PaymentData::fromModel($p))
        ]);
    }
}
