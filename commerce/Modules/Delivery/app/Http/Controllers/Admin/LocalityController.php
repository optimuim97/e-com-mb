<?php

namespace Modules\Delivery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Delivery\Models\Locality;

/**
 * Cities and communes served, managed from the admin.
 *
 * The list used to ship inside the front-end bundle: a town the shop actually
 * delivers to could not be named at checkout until someone deployed. It now
 * lives in the database, and this controller is how the shop edits it.
 *
 * Two levels only: a city, and the communes under it. Deeper nesting has no
 * meaning on an address and would only make the selector harder to read.
 */
class LocalityController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build([
            'localities.view'   => ['index'],
            'localities.create' => ['store'],
            'localities.edit'   => ['update'],
            'localities.delete' => ['destroy'],
        ]);
    }

    /**
     * GET /admin/localities
     *
     * The whole list, inactive rows included — the admin has to see what it
     * disabled in order to bring it back.
     */
    public function index(Request $request): JsonResponse
    {
        $search = DeliveryZone::normalize((string) $request->query('q', ''));

        $cities = Locality::query()
            ->cities()
            ->where('country', $request->query('country', 'CI'))
            ->with(['communes'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($search !== '') {
            // A city matches on its own name or on any of its communes: typing
            // "Niofoin" must show the city that carries it.
            $cities = $cities->filter(
                fn (Locality $city) => str_contains($city->slug, $search)
                    || $city->communes->contains(fn (Locality $c) => str_contains($c->slug, $search))
            )->values();
        }

        return response()->json([
            'data'  => $cities,
            'meta'  => [
                'cities'   => $cities->count(),
                'communes' => $cities->sum(fn (Locality $city) => $city->communes->count()),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $this->refuseDuplicate($data);

        return response()->json(Locality::create($data)->load('communes'), 201);
    }

    public function update(Request $request, Locality $locality): JsonResponse
    {
        $data = $this->validated($request, $locality);

        // A city with communes cannot become a commune itself: its own children
        // would end up on a third level the selector does not show.
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null
            && $locality->communes()->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Cette ville contient des communes : videz-la avant d\'en faire une commune.',
            ]);
        }

        $this->refuseDuplicate($data + ['parent_id' => $locality->parent_id], $locality->id);

        $locality->update($data);

        return response()->json($locality->fresh()->load('communes'));
    }

    /**
     * DELETE /admin/localities/{locality}
     *
     * Deleting a city takes its communes with it. Orders already placed keep
     * their address as typed: it is copied onto the order, never referenced.
     */
    public function destroy(Locality $locality): JsonResponse
    {
        $communes = $locality->communes()->count();

        $locality->delete();

        return response()->json([
            'message' => $communes > 0
                ? "« {$locality->name} » et ses {$communes} commune(s) ont été supprimées."
                : "« {$locality->name} » a été supprimée.",
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Locality $locality = null): array
    {
        return $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            // A commune hangs off a city, never off another commune.
            'parent_id'  => [
                'nullable',
                Rule::exists('localities', 'id')->whereNull('parent_id'),
                Rule::notIn([$locality?->id]),
            ],
            'region'     => ['nullable', 'string', 'max:120'],
            'country'    => ['nullable', 'string', 'size:2'],
            'active'     => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);
    }

    /**
     * Two identical names under the same city would give the customer the same
     * choice twice, with no way to tell them apart.
     *
     * @param array<string, mixed> $data
     */
    private function refuseDuplicate(array $data, ?int $exceptId = null): void
    {
        $exists = Locality::query()
            ->where('slug', DeliveryZone::normalize((string) $data['name']))
            ->where('country', $data['country'] ?? 'CI')
            ->where('parent_id', $data['parent_id'] ?? null)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Cette localité existe déjà à cet endroit.',
            ]);
        }
    }
}
