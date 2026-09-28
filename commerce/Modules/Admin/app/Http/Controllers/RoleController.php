<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Spatie\Permission\Models\Role;

class RoleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::crud('roles');
    }

    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')
            ->orderBy('name')
            ->get();

        // Count users per role via the pivot table (avoids Spatie model_has_roles config issue)
        $counts = \DB::table('model_has_roles')
            ->select('role_id', \DB::raw('count(*) as total'))
            ->groupBy('role_id')
            ->pluck('total', 'role_id');

        $result = $roles->map(function ($r) use ($counts) {
            return [
                'id'          => $r->id,
                'name'        => $r->name,
                'users_count' => $counts[$r->id] ?? 0,
                'permissions' => $r->permissions->pluck('name'),
            ];
        });

        return response()->json($result);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:roles,name',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

        if (!empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json($this->format($role->load('permissions')), 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name'        => "sometimes|string|max:100|unique:roles,name,{$role->id}",
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        return response()->json($this->format($role->load('permissions')));
    }

    public function destroy(Role $role): JsonResponse
    {
        if (in_array($role->name, ['admin', 'staff', 'customer'])) {
            return response()->json(['message' => "Le rôle \"{$role->name}\" est protégé."], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Rôle supprimé.']);
    }

    private function format(Role $role, int $usersCount = 0): array
    {
        return [
            'id'          => $role->id,
            'name'        => $role->name,
            'users_count' => $usersCount,
            'permissions' => $role->permissions->pluck('name'),
        ];
    }
}
