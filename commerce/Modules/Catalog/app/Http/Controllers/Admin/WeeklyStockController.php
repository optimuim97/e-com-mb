<?php

namespace Modules\Catalog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Modules\Catalog\Models\WeeklyStockAllocation;
use Modules\Catalog\Services\WeeklyStockBoard;

/**
 * Le stock mis en vente cette semaine.
 *
 * Lecture ouverte à qui voit le stock ; l'écriture décide de ce que la
 * boutique accepte de vendre, elle suit donc la même habilitation que la
 * correction d'un compteur.
 */
class WeeklyStockController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'stock.view'   => ['index'],
            'stock.adjust' => ['store'],
        ]);
    }

    public function __construct(private readonly WeeklyStockBoard $board) {}

    /**
     * GET /admin/weekly-stock?week=2026-09-14
     * Le tableau de la semaine demandée, celle en cours par défaut.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['week' => 'nullable|date']);

        return response()->json(
            $this->board->forWeek(WeeklyStockAllocation::weekOf($request->input('week')))
        );
    }

    /**
     * POST /admin/weekly-stock
     * Enregistre les allocations d'une semaine, en un seul envoi.
     *
     * Le tableau se remplit ligne par ligne mais se valide d'un bloc : une
     * sauvegarde par cellule multiplierait les allers-retours et laisserait la
     * semaine à moitié saisie si le réseau lâche en cours de route.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'week'                 => 'required|date',
            'rows'                 => 'required|array|min:1',
            'rows.*.product_id'    => 'required|integer|exists:products,id',
            // Nul = on retire le plafond de cet article pour la semaine, ce qui
            // n'est pas la même chose que zéro — zéro interdit la vente.
            'rows.*.quantity'      => 'nullable|integer|min:0|max:100000',
            'rows.*.note'          => 'nullable|string|max:255',
        ]);

        $semaine = WeeklyStockAllocation::weekOf($data['week']);

        DB::transaction(function () use ($data, $semaine) {
            foreach ($data['rows'] as $ligne) {
                $existante = WeeklyStockAllocation::lookup($ligne['product_id'], null, $semaine);

                // Quantité absente : la ligne disparaît, l'article repasse sans
                // plafond. Conserver une ligne vide se lirait comme « zéro ».
                if (($ligne['quantity'] ?? null) === null && empty($ligne['note'])) {
                    $existante?->delete();
                    continue;
                }

                $valeurs = [
                    'quantity' => $ligne['quantity'] ?? 0,
                    'note'     => $ligne['note'] ?? null,
                ];

                if ($existante) {
                    $existante->update($valeurs);
                    continue;
                }

                WeeklyStockAllocation::create($valeurs + [
                    'product_id' => $ligne['product_id'],
                    'variant_id' => null,
                    'week_start' => $semaine->toDateString(),
                ]);
            }
        });

        return response()->json($this->board->forWeek($semaine));
    }
}
