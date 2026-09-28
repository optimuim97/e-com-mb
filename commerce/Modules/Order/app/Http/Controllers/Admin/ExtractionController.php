<?php

namespace Modules\Order\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ExportStamp;
use App\Support\ModuleRegistry;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Exceptions\NoShippableOrdersException;
use Modules\Order\Models\Order;
use Modules\Order\Services\DeliveryRoundService;
use Modules\Order\Services\InvoiceService;
use Modules\Order\Support\ShipmentSheetScope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manual extraction, on the 21:00 task's calendar.
 *
 * Same scope, same window, same marker as the scheduled run
 * (SendShipmentSheetCommand): extracting by hand at 15:00 moves the zone's
 * window forward, and the 21:00 run only picks up what arrived since. Two
 * independent mechanisms would have stepped on each other — duplicates on one
 * side, orders never picked up on the other.
 *
 * The file is the plain-text delivery sheet the evening email already carries:
 * the one the shop prints on its thermal printer.
 */
class ExtractionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'orders.view' => ['index'],
            // Marking orders as extracted modifies them: same right as moving
            // them into preparation.
            'orders.edit' => ['store'],
        ]);
    }

    /**
     * GET /admin/orders/extractions
     * Each zone's state: scheduled today or not, ready, overdue.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'today' => now()->dayOfWeek,
            'data'  => array_map(function (string $zone) {
                $from = ShipmentSheetScope::windowStart($zone);

                return [
                    'type'             => $zone,
                    'label'            => ShipmentSheetScope::label($zone),
                    'days'             => ShipmentSheetScope::DAYS[$zone],
                    'days_label'       => ShipmentSheetScope::daysLabel($zone),
                    'scheduled_today'  => ShipmentSheetScope::isScheduled($zone),
                    'requires_payment' => ShipmentSheetScope::requiresPayment($zone),
                    'window_start'     => $from,
                    'pending_count'    => ShipmentSheetScope::due($zone, $from, now())->count(),
                    // The safety net: what should have left and has not.
                    'overdue_count'    => ShipmentSheetScope::overdue($zone, $from)->count(),
                ];
            }, array_keys(ShipmentSheetScope::ZONES)),
        ]);
    }

    /**
     * POST /admin/orders/extractions/{type}
     *
     * On a day the carrier does not come, the extraction requires
     * `confirm_off_day`: without it the answer is a 409 carrying the zone's
     * calendar, which the screen shows before letting the agent go ahead.
     */
    public function store(
        Request $request,
        string $type,
        InvoiceService $invoices,
        DeliveryRoundService $rounds,
    ): Response {
        abort_unless(ShipmentSheetScope::isKnown($type), 404);

        $request->validate(['confirm_off_day' => 'nullable|boolean']);

        if (! ShipmentSheetScope::isScheduled($type) && ! $request->boolean('confirm_off_day')) {
            return response()->json([
                'message' => ShipmentSheetScope::label($type)
                    . ' : extraction prévue le ' . ShipmentSheetScope::daysLabel($type)
                    . ". Aujourd'hui n'est pas un jour de passage — confirmez pour extraire quand même.",
                'requires_confirmation' => true,
                'days_label'            => ShipmentSheetScope::daysLabel($type),
            ], 409);
        }

        $now    = now();
        $orders = ShipmentSheetScope::due($type, ShipmentSheetScope::windowStart($type), $now);

        if ($orders->isEmpty()) {
            return response()->json([
                'message' => ShipmentSheetScope::label($type) . ' : aucune commande à extraire.',
            ], 422);
        }

        $label = ShipmentSheetScope::label($type);

        // All or nothing: if the sheet cannot be produced, no order is marked,
        // no round is left behind, and the next attempt finds the same orders.
        [$sheet, $roundCode] = DB::transaction(function () use ($orders, $label, $type, $now, $invoices, $rounds) {
            $round = $this->openDraftRound($rounds, $orders, $label, $type);

            $sheet = $invoices->generateByZoneTxt([
                'order_ids'           => $orders->pluck('id')->all(),
                'label'               => $label,
                'show_product_totals' => false,
                'show_item_counts'    => false,
                // The selection is already filtered upstream; with explicit ids
                // and this flag, the sheet holds every order passed — which is
                // exactly what gets marked below.
                'include_unpaid'      => true,
                'round'               => $round,
            ]);

            // One order at a time, through the model: the activity log records
            // who extracted what, which a mass update would not.
            $orders->each(fn (Order $order) => $order->update(['exported_at' => $now]));

            // The 21:00 run resumes from here: it will not resend these orders
            // and will pick up everything that arrives afterwards.
            ShipmentSheetScope::rememberRun($type, $now);

            return [$sheet, $round['code'] ?? null];
        });

        $filename = 'extraction_' . ShipmentSheetScope::fileSlug($type) . '_' . ExportStamp::now() . '.txt';

        return response($sheet, 200, [
            'Content-Type'        => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            // Read by the screen to confirm how many orders went out.
            'X-Extracted-Count'   => (string) $orders->count(),
            'X-Round-Code'        => (string) $roundCode,
            'Access-Control-Expose-Headers' => 'X-Extracted-Count, X-Round-Code, Content-Disposition',
        ]);
    }

    /**
     * Draft round for these orders, as the 21:00 run opens one.
     *
     * Without it, a manual extraction would leave its orders outside any round,
     * and the morning departure could not be confirmed on a code. A failure
     * here does not block the sheet: a missing round is recreated in two clicks
     * from the shipments screen.
     *
     * @param  Collection<int, Order>  $orders
     * @return array{code: string, count: int, items: int}|null
     */
    private function openDraftRound(
        DeliveryRoundService $rounds,
        Collection $orders,
        string $label,
        string $zone,
    ): ?array {
        if (! ModuleRegistry::enabled('rounds')) {
            return null;
        }

        try {
            $round = $rounds->create(
                orderIds: $orders->pluck('id')->all(),
                label:    $label . ' — ' . Carbon::now()->format('d/m'),
                draft:    true,
            )['round'];
        } catch (NoShippableOrdersException) {
            return null;
        } catch (\Throwable $e) {
            Log::error('[ManualExtraction] Draft round failed', ['zone' => $zone, 'error' => $e->getMessage()]);

            return null;
        }

        return [
            'code'  => $round->code,
            'count' => $round->lines->count(),
            'items' => (int) $round->lines->sum(fn ($line) => $line->order?->items->sum('quantity') ?? 0),
        ];
    }
}
