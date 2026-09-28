<?php

namespace Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Delivery\Models\Delivery;
use Modules\Delivery\Enums\DeliveryStatus;
use Modules\Delivery\Services\DeliveryService;

class DeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveryService
    ) {}

    /**
     * Livraisons du jour pour le livreur connecté
     */
    public function today(Request $request): JsonResponse
    {
        $deliveries = $this->deliveryService->getTodayDeliveries($request->user()->id);

        return response()->json(['data' => $deliveries]);
    }

    /**
     * Détail d'une livraison
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $delivery = $this->deliveryService->getDeliveryForCourier($request->user()->id, $id);

        return response()->json(['data' => $delivery]);
    }

    /**
     * Mise à jour du statut d'une livraison
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:' . implode(',', array_column(DeliveryStatus::cases(), 'value')),
            'failure_reason' => 'nullable|required_if:status,FAILED|string|max:500',
        ]);

        $delivery = $this->deliveryService->updateStatus(
            courierId: $request->user()->id,
            deliveryId: $id,
            status: DeliveryStatus::from($request->status),
            failureReason: $request->failure_reason,
        );

        return response()->json([
            'message' => 'Statut mis à jour.',
            'data'    => $delivery,
        ]);
    }

    /**
     * Confirmation de livraison (QR + signature + géolocalisation)
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'qr_code'         => 'required|string',
            'signature_base64' => 'required|string',
            'latitude'         => 'required|numeric',
            'longitude'        => 'required|numeric',
        ]);

        $delivery = $this->deliveryService->confirmDelivery(
            courierId: $request->user()->id,
            deliveryId: $id,
            qrCode: $request->qr_code,
            signature: $request->signature_base64,
            latitude: $request->latitude,
            longitude: $request->longitude,
        );

        return response()->json([
            'message' => 'Livraison confirmée.',
            'data'    => $delivery,
        ]);
    }
}
