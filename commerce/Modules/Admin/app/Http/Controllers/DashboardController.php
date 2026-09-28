<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Http\Resources\LowStockProductResource;
use Modules\Admin\Http\Resources\SalesChartEntryResource;
use Modules\Admin\Services\DashboardService;
use Modules\Order\Http\Resources\OrderResource;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Finance privée : un agent sans `finance.view` ne reçoit ni le chiffre
        // d'affaires ni le graphique du CA. L'admin garde ces valeurs privées.
        $canSeeFinance = (bool) $request->user()?->can('finance.view');

        $stats = $this->dashboard->getStats();
        if (! $canSeeFinance) {
            unset($stats['revenue'], $stats['revenue_in_progress'], $stats['today_revenue'], $stats['week_revenue']);
        }

        // Semaine par défaut : c'est la maille à laquelle la boutique se lit.
        // Le jour est trop bruité pour un commerce qui livre par tournées.
        $period = $request->string('period')->toString();
        $period = in_array($period, DashboardService::PERIODS, true) ? $period : 'week';

        return response()->json([
            'stats'         => $stats,
            'can_finance'   => $canSeeFinance,
            'period'        => $period,
            'recent_orders' => OrderResource::collection($this->dashboard->getRecentOrders()),
            'low_stock'     => LowStockProductResource::collection($this->dashboard->getLowStockProducts()),
            'sales_chart'   => $canSeeFinance
                ? SalesChartEntryResource::collection($this->dashboard->getSalesChart($period))
                : [],
        ]);
    }
}
