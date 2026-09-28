<?php

namespace Modules\Program\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Program\Models\ProgramCheckin;
use Modules\Program\Models\ProgramMembership;

class TrackerController extends Controller
{
    /**
     * GET /program/tracker
     * Suivi de routine : streak, jours de la semaine, today_done.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $today  = Carbon::today();

        // Jours de la semaine en cours (Lun=0 … Dim=6)
        $startOfWeek = $today->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek   = $startOfWeek->copy()->addDays(6);

        $weekCheckins = ProgramCheckin::where('user_id', $userId)
            ->whereBetween('date', [$startOfWeek->toDateString(), $endOfWeek->toDateString()])
            ->pluck('date')
            ->map(fn($d) => Carbon::parse($d)->dayOfWeekIso - 1) // Mon=0
            ->toArray();

        // Calcul du streak consécutif
        $streak = 0;
        $check  = $today->copy();
        while (ProgramCheckin::where('user_id', $userId)->whereDate('date', $check)->exists()) {
            $streak++;
            $check->subDay();
            if ($streak > 365) break;
        }

        $todayDone = ProgramCheckin::where('user_id', $userId)
            ->whereDate('date', $today->toDateString())
            ->exists();

        return response()->json([
            'streak'     => $streak,
            'week'       => $weekCheckins,
            'today_done' => $todayDone,
        ]);
    }

    /**
     * POST /program/tracker/checkin
     * Enregistrer la routine du jour (+5 pts, une fois par jour).
     */
    public function checkin(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $date   = $request->input('date', Carbon::today()->toDateString());

        // Un seul check-in par jour (unique constraint en DB aussi)
        $already = ProgramCheckin::where('user_id', $userId)
            ->whereDate('date', $date)
            ->exists();

        if ($already) {
            return response()->json(['message' => 'Routine déjà validée aujourd\'hui.'], 422);
        }

        ProgramCheckin::create(['user_id' => $userId, 'date' => $date]);

        // +5 pts si membre
        $membership = ProgramMembership::where('user_id', $userId)->first();
        if ($membership) {
            $membership->addPoints(5);
        }

        return response()->json([
            'message' => 'Routine validée ! +5 points.',
            'points'  => $membership?->points,
        ]);
    }
}
