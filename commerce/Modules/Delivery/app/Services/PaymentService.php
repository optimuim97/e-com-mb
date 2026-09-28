<?php
namespace Modules\Delivery\Services;

use Modules\Delivery\Models\Delivery;
use Modules\Delivery\Models\DeliveryPayment;
use Illuminate\Support\Collection;

class PaymentService
{
    public function recordPayment(int $deliveryId, int $courierId, array $data): DeliveryPayment
    {
        // Convertir les montants en centimes
        $payment = DeliveryPayment::create([
            'delivery_id' => $deliveryId,
            'courier_id' => $courierId,
            'payment_method' => $data['payment_method'],
            'mobile_operator' => $data['operator'] ?? null,
            'transaction_reference' => $data['transaction_ref'] ?? null,
            'amount_expected' => (int)($data['amount_expected'] * 100),
            'amount_received' => (int)($data['amount_received'] * 100),
            'change_amount' => (int)($data['amount_change'] * 100),
            'paid_at' => $data['paid_at'],
        ]);

        return $payment;
    }

    public function getPaymentsForDelivery(int $deliveryId): Collection
    {
        return DeliveryPayment::where('delivery_id', $deliveryId)
            ->orderBy('paid_at', 'desc')
            ->get();
    }

    public function getTodayCollectionSummary(int $courierId): array
    {
        $payments = DeliveryPayment::where('courier_id', $courierId)
            ->whereDate('paid_at', today())
            ->get();

        return [
            'total_collected' => $payments->sum('amount_received') / 100,
            'total_cash' => $payments->where('payment_method', 'CASH')->sum('amount_received') / 100,
            'total_mobile_money' => $payments->where('payment_method', 'MOBILE_MONEY')->sum('amount_received') / 100,
            'transaction_count' => $payments->count(),
        ];
    }
}
