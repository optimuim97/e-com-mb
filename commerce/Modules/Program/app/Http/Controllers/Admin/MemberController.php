<?php

namespace Modules\Program\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Program\Http\Resources\MembershipResource;
use Modules\Program\Models\ProgramMembership;

class MemberController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'program.view' => ['index', 'show'],
            'program.edit' => ['update'],
        ]);
    }

    /**
     * GET /admin/program/members
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProgramMembership::with('user')->latest();

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        if ($request->filled('search')) {
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%')
            );
        }

        $members = $query->paginate($request->integer('per_page', 50));

        return response()->json(MembershipResource::collection($members)->response()->getData(true));
    }

    /**
     * GET /admin/program/members/{id}
     */
    public function show(int $id): JsonResponse
    {
        $member = ProgramMembership::with('user')->findOrFail($id);
        return response()->json(['membership' => new MembershipResource($member)]);
    }

    /**
     * PATCH /admin/program/members/{id}
     * Modifier points et/ou niveau manuellement.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $member = ProgramMembership::findOrFail($id);

        $validated = $request->validate([
            'points' => 'sometimes|integer|min:0',
            'tier'   => 'sometimes|in:bronze,silver,gold',
        ]);

        $member->update($validated);

        return response()->json(['membership' => new MembershipResource($member->fresh())]);
    }
}
