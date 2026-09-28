<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function index(Request $request): ResourceCollection
    {
        return OrderResource::collection(
            Order::with(['user', 'items'])
                ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
                ->when($request->filled('search'), fn($q) =>
                    $q->where('number', 'like', '%'.$request->search.'%')
                      ->orWhereHas('user', fn($u) => $u->where('email', 'like', '%'.$request->search.'%'))
                )
                ->latest()
                ->paginate(20)
        );
    }

    public function show(Order $order): OrderResource
    {
        return new OrderResource($order->load(['user', 'items.product', 'items.variant', 'payments']));
    }

    public function update(Request $request, Order $order): OrderResource
    {
        $data = $request->validate([
            'status'          => 'sometimes|in:'.implode(',', Order::STATUSES),
            'tracking_number' => 'nullable|string',
            'admin_note'      => 'nullable|string',
        ]);

        if (isset($data['admin_note'])) {
            $order->update(['admin_note' => $data['admin_note']]);
        }

        if (isset($data['status'])) {
            $order = $this->orderService->updateStatus($order, $data['status'], $data['tracking_number'] ?? null);
        }

        return new OrderResource($order->load(['user', 'items']));
    }
}
