<?php
namespace Modules\Delivery\Data;

use Spatie\LaravelData\Data;
use Modules\Delivery\Models\DeliveryPayment;

class PaymentData extends Data
{
    public function __construct(
        public readonly int $order_id,
        public readonly string $payment_method,
        public readonly ?string $operator,
        public readonly ?string $transaction_ref,
        public readonly float $amount_expected,
        public readonly float $amount_received,
        public readonly float $amount_change,
        public readonly string $paid_at,
    ) {}

    public static function fromModel(DeliveryPayment $payment): self
    {
        return new self(
            order_id: $payment->delivery_id,
            payment_method: $payment->payment_method->value,
            operator: $payment->mobile_operator?->value,
            transaction_ref: $payment->transaction_reference,
            amount_expected: $payment->amount_expected / 100,
            amount_received: $payment->amount_received / 100,
            amount_change: $payment->change_amount / 100,
            paid_at: $payment->paid_at->toISOString(),
        );
    }
}
