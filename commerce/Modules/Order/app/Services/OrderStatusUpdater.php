<?php

namespace Modules\Order\Services;

use Modules\Order\Models\Order;

class OrderStatusUpdater
{
    private Order $order;
    private array $statusTransitions = [
        'shipped' => 'shipped_at',
        'delivered' => 'delivered_at'
    ];

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function updateStatus(string $status, ?string $trackingNumber = null): void
    {
        $attrs = ['status' => $status];

        if ($trackingNumber) {
            $attrs['tracking_number'] = $trackingNumber;
        }

        $this->updateTimestamps($status, $attrs);
        $this->order->update($attrs);
    }

    private function updateTimestamps(string $status, array &$attrs): void
    {
        foreach ($this->statusTransitions as $transitionStatus => $timestampField) {
            if ($status === $transitionStatus && !$this->order->{$timestampField}) {
                $attrs[$timestampField] = now();
            }
        }
    }
}
