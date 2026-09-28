<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Admin\Models\GalleryPhoto;

/**
 * Galerie photos de la page d'accueil.
 *
 * - GET /gallery                → public : photos actives, ordonnées
 * - GET /admin/gallery          → liste complète (admin)
 * - POST /admin/gallery         → upload (image + titre + description)
 * - PUT /admin/gallery/{photo}  → édition titre/description/actif
 * - PUT /admin/gallery-reorder  → réordonner (liste d'ids)
 * - DELETE /admin/gallery/{photo}
 */
class GalleryPhotoController extends Controller
{
    /** Public : photos actives pour la section d'accueil. */
    public function public(): JsonResponse
    {
        // Module éteint : liste vide plutôt qu'un 403, la page d'accueil
        // masque simplement la section au lieu d'afficher une erreur.
        if (! ModuleRegistry::enabled('gallery')) {
            return response()->json(['data' => []]);
        }

        $photos = GalleryPhoto::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $photos]);
    }

    /** Admin : toutes les photos (actives ou non). */
    public function index(): JsonResponse
    {
        $photos = GalleryPhoto::orderBy('sort_order')->orderBy('id')->get();

        return response()->json(['data' => $photos]);
    }

    /** Admin : publier une nouvelle photo. */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image'       => 'required|image|max:8192',
            'title'       => 'nullable|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);

        $path = $request->file('image')->store('gallery', 'public');

        $photo = GalleryPhoto::create([
            'image_path'  => $path,
            'title'       => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            // En fin de liste par défaut
            'sort_order'  => (int) GalleryPhoto::max('sort_order') + 1,
        ]);

        return response()->json(['data' => $photo], 201);
    }

    /** Admin : éditer titre / description / visibilité. */
    public function update(Request $request, GalleryPhoto $photo): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'nullable|string|max:120',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'sometimes|boolean',
        ]);

        $photo->update($validated);

        return response()->json(['data' => $photo->fresh()]);
    }

    /** Admin : réordonner — reçoit la liste complète des ids dans le nouvel ordre. */
    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer|exists:gallery_photos,id',
        ]);

        foreach ($validated['ids'] as $i => $id) {
            GalleryPhoto::where('id', $id)->update(['sort_order' => $i + 1]);
        }

        return response()->json(['message' => 'Ordre mis à jour.']);
    }

    /** Admin : supprimer la photo (et son fichier). */
    public function destroy(GalleryPhoto $photo): JsonResponse
    {
        Storage::disk('public')->delete($photo->image_path);
        $photo->delete();

        return response()->json(['message' => 'Photo supprimée.']);
    }
}
