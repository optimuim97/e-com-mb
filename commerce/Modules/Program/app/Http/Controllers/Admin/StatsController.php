<?php

namespace Modules\Program\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Program\Models\ProgramConsultation;
use Modules\Program\Models\ProgramMembership;

class StatsController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build(['program.view' => ['index']]);
    }

    /**
     * GET /admin/program/stats
     */
    public function index(): JsonResponse
    {
        $stats = [
            'members_total'          => ProgramMembership::count(),
            'members_bronze'         => ProgramMembership::where('tier', 'bronze')->count(),
            'members_silver'         => ProgramMembership::where('tier', 'silver')->count(),
            'members_gold'           => ProgramMembership::where('tier', 'gold')->count(),
            'consultations_pending'  => ProgramConsultation::pending()->count(),
            'consultations_total'    => ProgramConsultation::count(),
        ];

        return response()->json($stats);
    }
}
