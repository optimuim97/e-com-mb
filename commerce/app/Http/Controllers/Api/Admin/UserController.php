<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        return UserResource::collection(
            User::with('roles')
                ->when($request->filled('search'), fn($q) =>
                    $q->where('name', 'like', '%'.$request->search.'%')
                      ->orWhere('email', 'like', '%'.$request->search.'%')
                )
                ->latest()
                ->paginate(20)
        );
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user->load(['roles', 'orders']));
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'phone' => 'nullable|string',
            'role'  => 'sometimes|string|in:admin,staff,customer',
        ]);
        if (isset($data['role'])) { $user->syncRoles([$data['role']]); unset($data['role']); }
        $user->update($data);
        return new UserResource($user->load('roles'));
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        return response()->json(['message' => 'Utilisateur supprimé']);
    }
}
