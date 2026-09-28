<?php

namespace App\Http\Controllers\Api\Shop;

use App\Data\OrderData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
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
            $request->user()->orders()->with('items')->latest()->paginate(15)
        );
    }

    public function show(Request $request, string $number): OrderResource
    {
        $order = $request->user()->orders()
            ->where('number', $number)
            ->with(['items', 'payments'])
            ->firstOrFail();

        return new OrderResource($order);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $data  = OrderData::fromValidated($request->validated());

        try {
            $order = $this->orderService->createFromCart($request->user(), $data);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(new OrderResource($order), 201);
    }
}
