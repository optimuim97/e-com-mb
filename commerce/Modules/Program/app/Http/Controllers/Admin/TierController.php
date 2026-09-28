<?php

namespace Modules\Program\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Program\Http\Resources\TierResource;
use Modules\Program\Models\ProgramTier;

class TierController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'program.view' => ['index'],
            'program.edit' => ['update'],
        ]);
    }

    /**
     * GET /admin/program/tiers
     */
    public function index(): JsonResponse
    {
        $tiers = ProgramTier::orderBy('sort_order')->get();
        return response()->json(TierResource::collection($tiers));
    }

    /**
     * PATCH /admin/program/tiers/{id}
     * Modifier un niveau (paliers, réduction).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Accepte l'id numérique OU le slug (bronze/silver/gold)
        $tier = is_numeric($id)
            ? ProgramTier::findOrFail($id)
            : ProgramTier::where('slug', $id)->firstOrFail();

        $validated = $request->validate([
            'name'         => 'sometimes|string|max:60',
            'icon'         => 'sometimes|string|max:10',
            'color'        => 'sometimes|nullable|string|max:20',
            'points_min'   => 'sometimes|integer|min:0',
            'points_max'   => 'sometimes|nullable|integer|min:0',
            'discount_pct' => 'sometimes|integer|min:0|max:100',
        ]);

        $tier->update($validated);

        return response()->json(['tier' => new TierResource($tier->fresh())]);
    }
}
