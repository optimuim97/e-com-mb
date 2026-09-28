<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::crud('permissions');
    }

    public function index(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name']);

        // Groupe par préfixe (ex: products.* → products)
        $grouped = $permissions->groupBy(fn($p) => explode('.', $p->name)[0]);

        return response()->json([
            'list'    => $permissions,
            'grouped' => $grouped->map(fn($items, $group) => [
                'group' => $group,
                'items' => $items->pluck('name'),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
        ]);

        $permission = Permission::create(['name' => $data['name'], 'guard_name' => 'web']);

        return response()->json(['id' => $permission->id, 'name' => $permission->name], 201);
    }

    public function update(Request $request, Permission $permission): JsonResponse
    {
        $data = $request->validate([
            'name' => "required|string|max:100|unique:permissions,name,{$permission->id}",
        ]);

        $permission->update(['name' => $data['name']]);

        return response()->json(['id' => $permission->id, 'name' => $permission->name]);
    }

    public function destroy(Permission $permission): JsonResponse
    {
        $permission->delete();

        return response()->json(['message' => 'Permission supprimée.']);
    }
}
