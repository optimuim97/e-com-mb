<?php

namespace Modules\Customer\Http\Controllers;

use App\Support\ExportStamp;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Maatwebsite\Excel\Excel;
use Modules\Customer\Exports\CustomersExport;
use Modules\Customer\Http\Resources\UserResource;

class UserController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::crud('customers',
            verbs: ['view' => ['index', 'show', 'export']],
        );
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $request->validate([
            'format'    => 'nullable|in:xlsx,csv',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'country'   => 'nullable|string|size:2',
        ]);

        $export = new CustomersExport(
            dateFrom: $request->date_from,
            dateTo:   $request->date_to,
            country:  $request->country ? strtoupper($request->country) : null,
        );

        $format   = $request->get('format', 'xlsx') === 'csv' ? Excel::CSV : Excel::XLSX;
        $ext      = $format === Excel::CSV ? 'csv' : 'xlsx';
        $filename = 'clients-' . ExportStamp::now() . '.' . $ext;

        return $export->download($filename, $format);
    }

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
