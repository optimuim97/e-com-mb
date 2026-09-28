<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'revenue'   => Order::whereIn('status', ['delivered', 'shipped'])->sum('total'),
                'orders'    => Order::count(),
                'customers' => User::role('customer')->count(),
                'products'  => Product::where('is_active', true)->count(),
            ],
            'recent_orders' => Order::with('user')->latest()->take(10)->get(),
            'low_stock'     => Product::where('track_stock', true)
                ->where('has_variants', false)
                ->whereColumn('stock', '<=', 'low_stock_threshold')
                ->take(10)
                ->get(['id', 'name', 'stock', 'low_stock_threshold']),
            'sales_chart'   => Order::select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total) as revenue'),
                    DB::raw('COUNT(*) as orders')
                )
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ]);
    }
}
