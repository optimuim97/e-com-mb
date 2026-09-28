<?php

namespace Modules\Program\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Program\Http\Resources\ConsultationResource;
use Modules\Program\Models\ProgramConsultation;

class ConsultationController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'program.view' => ['index'],
            'program.edit' => ['update'],
        ]);
    }

    /**
     * GET /admin/program/consultations
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProgramConsultation::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $consultations = $query->paginate($request->integer('per_page', 30));

        return response()->json(ConsultationResource::collection($consultations)->response()->getData(true));
    }

    /**
     * PATCH /admin/program/consultations/{id}
     * Mettre à jour le statut et la note conseillère.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $consultation = ProgramConsultation::findOrFail($id);

        $validated = $request->validate([
            'status'       => 'sometimes|in:pending,confirmed,done,cancelled',
            'advisor_note' => 'sometimes|nullable|string|max:2000',
        ]);

        $consultation->update($validated);

        return response()->json(['consultation' => new ConsultationResource($consultation->fresh()->load('user'))]);
    }
}
