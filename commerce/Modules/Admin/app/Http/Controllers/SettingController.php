<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Admin\Services\SettingService;

class SettingController extends Controller implements HasMiddleware
{
    // public() = paramètres publics (hors admin), non soumis à permission.
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'settings.view' => ['index'],
            'settings.edit' => ['update'],
        ]);
    }

    public function __construct(private readonly SettingService $settings) {}

    public function public(): JsonResponse
    {
        return response()->json($this->settings->getPublic());
    }

    public function index(): JsonResponse
    {
        return response()->json($this->settings->getAll());
    }

    /**
     * GET /admin/modules
     * Les pans du back-office qu'on peut éteindre, et leur état.
     *
     * Servi à part des réglages : la liste et les libellés viennent du
     * registre, pas de la base. Un module ajouté au code apparaît donc dans
     * l'écran sans qu'il faille penser à insérer une ligne quelque part.
     */
    public function modules(): JsonResponse
    {
        return response()->json(['data' => \App\Support\ModuleRegistry::all()]);
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate(['settings' => 'required|array']);

        $this->settings->updateMany($request->input('settings'));

        return response()->json(['message' => 'Paramètres mis à jour.']);
    }
}
