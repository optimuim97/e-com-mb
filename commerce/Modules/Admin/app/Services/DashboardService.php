<?php

namespace Modules\Admin\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\Admin\Support\Revenue;
use Modules\Catalog\Models\Product;
use Modules\Order\Models\Order;

class DashboardService
{
    /** Granularités proposées pour la courbe. La semaine est le défaut. */
    public const PERIODS = ['day', 'week', 'month'];

    public function getStats(): array
    {
        $statusCounts = Order::query()
            ->selectRaw('status, COUNT(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $realise = Revenue::realisedAt();

        return [
            // Chiffre d'affaires acquis : marchandise livrée, hors livraison.
            'revenue'             => Revenue::sum(Revenue::delivered()),
            // Engagé mais pas encore chez la cliente. Affiché à part, jamais
            // additionné au précédent.
            'revenue_in_progress' => Revenue::sum(Revenue::inProgress()),
            'orders'              => (int) Order::count(),
            'customers'           => (int) User::role('customer')->count(),
            'products'            => (int) Product::where('is_active', true)->count(),

            // Aujourd'hui
            'today_orders'  => (int) Order::whereDate('created_at', today())->count(),
            'today_revenue' => Revenue::sum(
                Revenue::delivered()->whereRaw("DATE($realise) = ?", [today()->toDateString()])
            ),

            // Semaine courante — la maille de lecture par défaut.
            'week_orders'  => (int) Order::where('created_at', '>=', now()->startOfWeek())->count(),
            'week_revenue' => Revenue::sum(
                Revenue::delivered()->whereRaw("$realise >= ?", [now()->startOfWeek()->toDateTimeString()])
            ),

            // Répartition par statut
            'pending'    => (int) ($statusCounts['pending']    ?? 0),
            'confirmed'  => (int) ($statusCounts['confirmed']  ?? 0),
            'processing' => (int) ($statusCounts['processing'] ?? 0),
            'shipped'    => (int) ($statusCounts['shipped']    ?? 0),
            'delivered'  => (int) ($statusCounts['delivered']  ?? 0),
            'cancelled'  => (int) ($statusCounts['cancelled']  ?? 0),
            'refunded'   => (int) ($statusCounts['refunded']   ?? 0),
        ];
    }

    public function getRecentOrders(): Collection
    {
        return Order::with('user')->latest()->take(10)->get();
    }

    public function getLowStockProducts(): Collection
    {
        return Product::where('track_stock', true)
            ->where('has_variants', false)
            ->whereColumn('stock', '<=', 'low_stock_threshold')
            ->take(10)
            ->get(['id', 'name', 'stock', 'low_stock_threshold']);
    }

    /**
     * Courbe du chiffre d'affaires, à la semaine par défaut.
     *
     * L'agrégation se fait en PHP à partir de lignes journalières plutôt qu'en
     * SQL : regrouper par semaine s'écrit `YEARWEEK` sur MySQL et
     * `strftime('%Y-%W')` sur SQLite, et la suite de tests tourne sur SQLite.
     * Le volume est dérisoire — une ligne par jour — le coût est nul.
     *
     * @param  string  $period  'day' | 'week' | 'month'
     */
    public function getSalesChart(string $period = 'week', ?int $span = null): Collection
    {
        $period = in_array($period, self::PERIODS, true) ? $period : 'week';

        $depuis = match ($period) {
            'day'   => now()->subDays($span ?? 30),
            'week'  => now()->subWeeks($span ?? 12)->startOfWeek(),
            'month' => now()->subMonths($span ?? 12)->startOfMonth(),
        };

        $realise = Revenue::realisedAt();

        $jours = Revenue::delivered()
            ->selectRaw("DATE($realise) as jour")
            ->selectRaw('SUM(' . Revenue::amount() . ') as revenue')
            ->selectRaw('COUNT(*) as orders')
            ->whereRaw("$realise >= ?", [$depuis->toDateTimeString()])
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();

        return $this->foldByPeriod($jours, $period);
    }

    /**
     * Replie des totaux journaliers sur la granularité demandée.
     *
     * La date rendue est le **début** de la période : c'est elle qui sert
     * d'étiquette et qui permet de trier, alors qu'un numéro de semaine ne se
     * compare pas d'une année à l'autre.
     */
    private function foldByPeriod(Collection $jours, string $period): Collection
    {
        return $jours
            ->groupBy(function ($ligne) use ($period) {
                $date = CarbonImmutable::parse($ligne->jour);

                return match ($period) {
                    'day'   => $date->toDateString(),
                    'week'  => $date->startOfWeek()->toDateString(),
                    'month' => $date->startOfMonth()->toDateString(),
                };
            })
            ->map(fn (Collection $lignes, string $debut) => (object) [
                'date'    => $debut,
                'revenue' => (float) $lignes->sum('revenue'),
                'orders'  => (int) $lignes->sum('orders'),
            ])
            ->values();
    }
}
