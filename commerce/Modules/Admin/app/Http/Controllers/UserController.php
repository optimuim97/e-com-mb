<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller implements HasMiddleware
{
    /**
     * Domaine des adresses de service : elles remplissent une colonne unique
     * et non nulle, aucun message n'y transite. `.local` n'est pas routable,
     * donc rien ne peut partir vers l'extérieur par accident.
     */
    private const DOMAINE_INTERNE = 'boutique.local';

    public static function middleware(): array
    {
        return ResourcePermissions::crud('users');
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::with('roles')
            ->withCount('orders')
            ->withSum('orders', 'total')
            ->when($request->search, fn($q) =>
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
            )
            ->when($request->role, fn($q) =>
                $q->role($request->role)
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($users->through(fn($u) => $this->format($u)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            // L'adresse devient facultative : un livreur n'a pas toujours de
            // boîte aux lettres, et lui en inventer une pour la lui faire
            // saisir à chaque connexion n'a pas de sens.
            'email'    => 'nullable|email|unique:users,email',
            'username' => 'nullable|string|max:50|alpha_dash|unique:users,username',
            'password' => ['required', Password::min(8)],
            'phone'    => 'nullable|string|max:30',
            'role'     => 'required|string|exists:roles,name',
        ]);

        // L'identifiant se déduit du nom quand il n'est pas fourni :
        // « Sidik Ouattara » donne « souattara ».
        // `nullable` n'ajoute pas la clé quand le champ est absent : les deux
        // accès doivent tolérer son absence, pas seulement une valeur vide.
        $identifiant = ! empty($data['username'])
            ? mb_strtolower($data['username'])
            : User::generateUsername($data['name']);

        $user = User::create([
            'name'     => $data['name'],
            // Sans adresse, on en compose une à partir de l'identifiant :
            // la colonne est unique et non nulle, et rien n'y est jamais envoyé.
            'email'    => $data['email'] ?? $identifiant . '@' . self::DOMAINE_INTERNE,
            'username' => $identifiant,
            'password' => Hash::make($data['password']),
            'phone'    => $data['phone'] ?? null,
        ]);

        $user->syncRoles([$data['role']]);

        return response()->json($this->format($user->load('roles')), 201);
    }

    public function show(User $user): JsonResponse
    {
        $user->load('roles', 'roles.permissions');

        return response()->json($this->format($user));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => "sometimes|email|unique:users,email,{$user->id}",
            'username' => "sometimes|nullable|string|max:50|alpha_dash|unique:users,username,{$user->id}",
            'password' => ['sometimes', 'nullable', Password::min(8)],
            'phone'    => 'sometimes|nullable|string|max:30',
            'role'     => 'sometimes|string|exists:roles,name',
            'permissions' => 'sometimes|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user->fill(array_filter([
            'name'     => $data['name']  ?? null,
            'email'    => $data['email'] ?? null,
            'username' => isset($data['username']) ? mb_strtolower($data['username']) : null,
            'phone'    => $data['phone'] ?? null,
        ], fn($v) => $v !== null));

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return response()->json($this->format($user->load('roles')));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Impossible de supprimer votre propre compte.'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    private function format(User $user): array
    {
        // Adresses de service : celles de la commande rapide, et celles
        // composées pour un compte qui se connecte par identifiant. Ni l'une
        // ni l'autre n'a de boîte aux lettres derrière : les afficher
        // laisserait croire qu'on peut y écrire.
        $interne = str_ends_with($user->email ?? '', '@clients.local')
            || str_ends_with($user->email ?? '', '@' . self::DOMAINE_INTERNE);

        return [
            'id'           => $user->id,
            'name'         => $user->name,
            'email'        => $interne ? null : $user->email,
            'username'     => $user->username,
            'phone'        => $user->phone,
            'role'         => $user->roles->first()?->name ?? 'customer',
            'roles'        => $user->roles->pluck('name'),
            'permissions'  => $user->getDirectPermissions()->pluck('name'),
            'orders_count' => $user->orders_count ?? 0,
            'orders_sum_total' => $user->orders_sum_total ?? 0,
            'created_at'   => $user->created_at,
        ];
    }
}
