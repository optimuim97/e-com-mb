<?php

namespace Modules\Program\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Program\Http\Resources\MembershipResource;
use Modules\Program\Http\Resources\TierResource;
use Modules\Program\Models\ProgramBenefit;
use Modules\Program\Models\ProgramMembership;
use Modules\Program\Models\ProgramTier;
use App\Support\Shop;

class ProgramController extends Controller
{
    /**
     * GET /program
     * Informations publiques : niveaux + avantages actifs par niveau.
     */
    public function index(): JsonResponse
    {
        $tiers = ProgramTier::orderBy('sort_order')
            ->get()
            ->map(function (ProgramTier $tier) {
                $benefits = ProgramBenefit::active()
                    ->forTier($tier->slug)
                    ->orderBy('sort_order')
                    ->pluck('title');

                return [
                    'slug'         => $tier->slug,
                    'name'         => $tier->name,
                    'icon'         => $tier->icon,
                    'color'        => $tier->color,
                    'points_min'   => $tier->points_min,
                    'points_max'   => $tier->points_max,
                    'discount_pct' => $tier->discount_pct,
                    'benefits'     => $benefits,
                ];
            });

        return response()->json(['tiers' => $tiers]);
    }

    /**
     * GET /program/membership
     * Statut du membre connecté.
     */
    public function membership(Request $request): JsonResponse
    {
        $membership = ProgramMembership::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['membership' => new MembershipResource($membership)]);
    }

    /**
     * POST /program/join
     * Rejoindre le programme (une seule fois par utilisateur).
     */
    public function join(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $membership = ProgramMembership::firstOrCreate(
            ['user_id' => $userId],
            ['tier' => 'bronze', 'points' => 0, 'points_lifetime' => 0]
        );

        return response()->json([
            'message'    => 'Bienvenue dans le club ' . Shop::name() . ' !',
            'membership' => new MembershipResource($membership),
        ], 201);
    }
}
