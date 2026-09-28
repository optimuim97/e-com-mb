<?php

namespace Modules\Delivery\Services;

use Illuminate\Support\Collection;
use Modules\Delivery\Models\Delivery;
use Modules\Delivery\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;

class DeliveryService
{
    /**
     * Livraisons du jour assignées au livreur
     */
    public function getTodayDeliveries(int $courierId): Collection
    {
        return Delivery::with(['customer', 'payments'])
            ->where('courier_id', $courierId)
            ->whereDate('assigned_at', today())
            ->whereNotIn('status', [
                DeliveryStatus::DELIVERED->value,
                DeliveryStatus::CANCELLED->value,
            ])
            ->orderByRaw("
                CASE
                    WHEN status = 'IN_PROGRESS' THEN 1
                    WHEN status = 'PENDING' THEN 2
                    WHEN status = 'FAILED' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('assigned_at', 'desc')
            ->get();
    }
    /**
     * Détail d'une livraison pour un livreur donné
     */
    public function getDeliveryForCourier(int $courierId, int $deliveryId): Delivery
    {
        return Delivery::forCourier($courierId)
            ->with('customer', 'payments', 'assignments')
            ->findOrFail($deliveryId);
    }

    /**
     * Mettre à jour le statut d'une livraison
     */
    public function updateStatus(
        int $courierId,
        int $deliveryId,
        DeliveryStatus $status,
        ?string $failureReason = null,
    ): Delivery {
        $delivery = Delivery::forCourier($courierId)->findOrFail($deliveryId);

        $data = ['status' => $status];

        if ($status === DeliveryStatus::FAILED && $failureReason) {
            $data['failure_reason'] = $failureReason;
        }

        if ($status === DeliveryStatus::IN_PROGRESS && !$delivery->assigned_at) {
            $data['assigned_at'] = now();
        }

        if ($status === DeliveryStatus::DELIVERED) {
            $data['delivered_at'] = now();
        }

        $delivery->update($data);

        return $delivery->fresh();
    }

    /**
     * Confirmer une livraison (QR code + signature + géoloc)
     */
    public function confirmDelivery(
        int $courierId,
        int $deliveryId,
        string $qrCode,
        string $signature,
        float $latitude,
        float $longitude,
    ): Delivery {
        $delivery = Delivery::forCourier($courierId)->findOrFail($deliveryId);

        abort_unless($delivery->canBeConfirmed(), 422, 'Cette livraison ne peut pas être confirmée.');

        // Vérifier le QR code
        abort_unless(
            Hash::check($qrCode, $delivery->qr_code_hash),
            422,
            'QR code invalide.'
        );

        $delivery->update([
            'status'           => DeliveryStatus::DELIVERED,
            'signature_base64' => $signature,
            'confirmed_at'     => now(),
            'delivered_at'     => now(),
            'delivery_lat'     => $latitude,
            'delivery_lng'     => $longitude,
        ]);

        return $delivery->fresh();
    }
}
