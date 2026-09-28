<?php

namespace Modules\Program\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Program\Http\Resources\ConsultationResource;
use Modules\Program\Models\ProgramConsultation;
use Modules\Program\Models\ProgramMembership;

class ConsultationController extends Controller
{
    /**
     * GET /program/consultations
     * Mes demandes de consultation.
     */
    public function index(Request $request): JsonResponse
    {
        $consultations = ProgramConsultation::where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json(ConsultationResource::collection($consultations));
    }

    /**
     * POST /program/consultations
     * Créer une demande de consultation.
     */
    public function store(Request $request): JsonResponse
    {
        // Vérification : l'utilisateur doit être membre
        $membership = ProgramMembership::where('user_id', $request->user()->id)->first();
        if (!$membership) {
            return response()->json(['message' => 'Vous devez rejoindre le programme pour demander une consultation.'], 403);
        }

        $validated = $request->validate([
            'type'         => 'required|in:skincare,product,routine,gift,other',
            'channel'      => 'required|in:whatsapp,video,email,store',
            'phone'        => 'nullable|string|max:30',
            'preferred_at' => 'nullable|date|after:now',
            'message'      => 'nullable|string|max:2000',
        ]);

        $consultation = ProgramConsultation::create([
            'user_id' => $request->user()->id,
            ...$validated,
            'status' => 'pending',
        ]);

        // +50 pts pour la première consultation
        $alreadyHad = ProgramConsultation::where('user_id', $request->user()->id)
            ->where('id', '!=', $consultation->id)
            ->exists();

        if (!$alreadyHad) {
            $membership->addPoints(50);
        }

        return response()->json([
            'message'      => 'Votre demande a été envoyée. Notre conseillère vous contactera sous 24h.',
            'consultation' => new ConsultationResource($consultation),
        ], 201);
    }

    /**
     * DELETE /program/consultations/{id}
     * Annuler une demande (seulement si pending).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $consultation = ProgramConsultation::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($consultation->status !== 'pending') {
            return response()->json(['message' => 'Seules les demandes en attente peuvent être annulées.'], 422);
        }

        $consultation->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Demande annulée.']);
    }
}
