<?php

namespace Modules\Program\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Program\Http\Resources\BenefitResource;
use Modules\Program\Models\ProgramBenefit;

class BenefitController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'program.view' => ['index'],
            'program.edit' => ['store', 'update', 'destroy'],
        ]);
    }

    /**
     * GET /admin/program/benefits
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProgramBenefit::orderBy('tier')->orderBy('sort_order');

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        return response()->json(BenefitResource::collection($query->get()));
    }

    /**
     * POST /admin/program/benefits
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier'        => 'required|in:bronze,silver,gold',
            'icon'        => 'nullable|string|max:10',
            'title'       => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $benefit = ProgramBenefit::create($validated);

        return response()->json(['benefit' => new BenefitResource($benefit)], 201);
    }

    /**
     * PATCH /admin/program/benefits/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $benefit = ProgramBenefit::findOrFail($id);

        $validated = $request->validate([
            'tier'        => 'sometimes|in:bronze,silver,gold',
            'icon'        => 'nullable|string|max:10',
            'title'       => 'sometimes|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer|min:0',
        ]);

        $benefit->update($validated);

        return response()->json(['benefit' => new BenefitResource($benefit->fresh())]);
    }

    /**
     * DELETE /admin/program/benefits/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        ProgramBenefit::findOrFail($id)->delete();
        return response()->json(['message' => 'Avantage supprimé.']);
    }
}
