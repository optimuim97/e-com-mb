<?php

namespace Modules\Order\Listeners;

use App\Services\Notifications\AdminNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Order\Events\OrderShipped;

class NotifyAdminsOnOrderShipped implements ShouldQueue
{
    use InteractsWithQueue;

    public string $queue = 'notifications';

    public function __construct(
        private readonly AdminNotificationService $notificationService,
    ) {}

    public function handle(OrderShipped $event): void
    {
        $this->notificationService->orderShipped($event->order);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
}
