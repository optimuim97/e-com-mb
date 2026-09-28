<?php

namespace Modules\Delivery\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Delivery\Models\DeliveryZone;

class DeliveryZoneController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    // quote() / destinations() servent au checkout public → hors permission.
    public static function middleware(): array
    {
        return \App\Support\ResourcePermissions::build([
            'delivery_zones.view'   => ['index'],
            'delivery_zones.create' => ['store'],
            'delivery_zones.edit'   => ['update'],
            'delivery_zones.delete' => ['destroy'],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $zones = DeliveryZone::query()
            ->when($request->filled('country'), fn($q) => $q->where('country', $request->country))
            ->when($request->filled('group'),   fn($q) => $q->where('group', $request->group))
            ->orderBy('country')
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $zones]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        if ($doublon = $this->zoneEnDoublon($data)) {
            return $this->refusDoublon($doublon);
        }

        $zone = DeliveryZone::create($data);
        return response()->json(['data' => $zone], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $zone = DeliveryZone::findOrFail($id);
        $data = $this->validated($request, $id);

        if ($doublon = $this->zoneEnDoublon($data, $zone)) {
            return $this->refusDoublon($doublon);
        }

        $zone->update($data);
        return response()->json(['data' => $zone->fresh()]);
    }

    /**
     * Zone existante qui couvre déjà l'une des appellations saisies.
     *
     * Le résolveur retient la première zone qui correspond, dans l'ordre de tri :
     * deux zones portant « Bouaké » à des tarifs différents ne provoquent aucune
     * erreur, elles rendent simplement le prix imprévisible selon que l'agent a
     * saisi la ville ou son alias. Le doublon se refuse donc à la saisie, seul
     * moment où quelqu'un peut encore trancher.
     *
     * La comparaison porte sur le nom ET les alias, des deux côtés : « bouake »
     * en alias d'une zone existante entre en conflit avec une nouvelle zone
     * nommée « Bouaké ».
     *
     * En modification, seules les appellations *ajoutées* sont vérifiées. Une
     * base qui porte déjà deux « Bouaké » ne doit pas devenir immodifiable :
     * refuser toute écriture sur les deux enfermerait l'administration dans le
     * doublon qu'on cherche justement à lui faire corriger.
     */
    private function zoneEnDoublon(array $data, ?DeliveryZone $modifiee = null): ?DeliveryZone
    {
        $appellations = $this->appellations([$data['name'], ...($data['cities'] ?? [])]);

        if ($modifiee) {
            $appellations = $appellations->diff(
                $this->appellations([$modifiee->name, ...($modifiee->cities ?? [])])
            );
        }

        if ($appellations->isEmpty()) {
            return null;
        }

        return DeliveryZone::where('country', $data['country'])
            ->when($modifiee, fn ($q) => $q->where('id', '!=', $modifiee->id))
            ->get()
            ->first(fn (DeliveryZone $zone) => $appellations
                ->intersect($this->appellations([$zone->name, ...($zone->cities ?? [])]))
                ->isNotEmpty());
    }

    /** Noms sous lesquels une zone peut être désignée, normalisés. */
    private function appellations(array $noms): \Illuminate\Support\Collection
    {
        return collect($noms)
            ->filter(fn ($nom) => is_string($nom) && trim($nom) !== '')
            ->map(fn (string $nom) => DeliveryZone::normalize($nom))
            ->unique()
            ->values();
    }

    private function refusDoublon(DeliveryZone $zone): JsonResponse
    {
        return response()->json([
            'message' => "« {$zone->name} » couvre déjà cette destination ({$zone->group}, "
                . number_format($zone->price, 0, ',', ' ') . ' FCFA). '
                . 'Modifiez cette zone plutôt que d\'en créer une seconde.',
            'zone'    => $zone,
        ], 422);
    }

    public function destroy(int $id): JsonResponse
    {
        DeliveryZone::findOrFail($id)->delete();
        return response()->json(['message' => 'Zone supprimée.']);
    }

    /**
     * Endpoint public : GET /api/shipping/quote?city=...&commune=...&country=CI&subtotal=...
     */
    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'city'     => 'nullable|string|max:120',
            'commune'  => 'nullable|string|max:120',
            'country'  => 'nullable|string|size:2',
            'subtotal' => 'nullable|integer|min:0',
        ]);

        $country = $request->country ?? 'CI';
        // Au moins une info de destination (city OU commune) sauf pour international
        if ($country === 'CI' && !$request->filled('city') && !$request->filled('commune')) {
            return response()->json([
                'found'   => false,
                'message' => 'Précisez une ville ou commune.',
            ], 422);
        }

        // Même résolution qu'à la création de commande (repli Abidjan compris) :
        // le montant annoncé ici doit être celui qui sera facturé.
        $quote = DeliveryZone::quoteWithAbidjanBase(
            $request->city ?? '',
            $country,
            $request->integer('subtotal'),
            $request->commune,
        );

        if (!$quote) {
            // Cas spécial : ville multi-communes (Abidjan) sans commune renseignée
            // → on demande la commune plutôt que d'annoncer "agents vous contacteront"
            if (
                $country === 'CI'
                && DeliveryZone::isMultiCommuneCity($request->city)
                && !$request->filled('commune')
            ) {
                return response()->json([
                    'found'         => false,
                    'needs_commune' => true,
                    'message'       => "Sélectionnez votre commune pour calculer les frais.",
                ], 200);
            }

            return response()->json([
                'found'   => false,
                'message' => "Aucune zone configurée pour cette destination.",
            ], 404);
        }

        return response()->json(['found' => true, ...$quote]);
    }

    /**
     * Endpoint public : GET /api/shipping/destinations
     * Retourne les zones actives groupées (utilisé par le checkout pour avertir
     * si la destination du client n'est pas couverte).
     */
    public function destinations(): JsonResponse
    {
        $zones = DeliveryZone::where('active', true)
            ->orderBy('country')
            ->orderBy('sort_order')
            ->get(['id', 'group', 'name', 'country', 'cities', 'price', 'price_unit']);

        // `is_abidjan` sert au sélecteur de commune de la boutique.
        //
        // Il se lit sur le GROUPE, pas sur le nom : le district compte des
        // quartiers livrés qui ne sont pas des communes officielles — Abatta,
        // Faya, Gonzagueville, Riviera, N'Dotré… Une liste de communes figée
        // côté front les laissait de côté, alors qu'ils sont bien tarifés
        // ici. C'est donc l'admin qui décide de ce qu'on livre, pas le code.
        // Le test vit sur le modèle : le back s'en sert aussi pour décider du
        // mode de paiement. Deux implémentations du même « c'est Abidjan »
        // finiraient par diverger, et le sélecteur proposerait une commune que
        // la commande refuserait ensuite de traiter comme abidjanaise.
        $zones->each(fn (DeliveryZone $zone) => $zone->setAttribute(
            'is_abidjan',
            $zone->country === 'CI' && DeliveryZone::isAbidjanGroup($zone->group),
        ));

        return response()->json(['data' => $zones]);
    }

    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate(
            [
                'group'          => 'required|string|max:80',
                'name'           => 'required|string|max:120',
                'country'        => 'required|string|size:2',
                'cities'         => 'nullable|array',
                'cities.*'       => 'string|max:120',
                'price'          => 'required|integer|min:0',
                'price_unit'     => 'nullable|in:flat,per_kg',
                'free_threshold' => 'nullable|integer|min:0',
                'sort_order'     => 'nullable|integer',
                'active'         => 'boolean',
            ],
            [
                'group.required'   => 'Le groupe est obligatoire.',
                'group.string'     => 'Le groupe doit être une chaîne de caractères.',
                'group.max'        => 'Le groupe ne doit pas dépasser 80 caractères.',

                'name.required'    => 'Le nom est obligatoire.',
                'name.string'      => 'Le nom doit être une chaîne de caractères.',
                'name.max'         => 'Le nom ne doit pas dépasser 120 caractères.',

                'country.required' => 'Le pays est obligatoire.',
                'country.size'     => 'Le code pays doit contenir exactement 2 caractères.',

                'cities.array'     => 'Les villes doivent être sous forme de liste.',
                'cities.*.string'  => 'Chaque ville doit être une chaîne de caractères.',
                'cities.*.max'     => 'Le nom de la ville ne doit pas dépasser 120 caractères.',

                'price.required'   => 'Le prix est obligatoire.',
                'price.integer'    => 'Le prix doit être un nombre entier.',
                'price.min'        => 'Le prix doit être supérieur ou égal à 0.',

                'price_unit.in'    => 'L’unité de prix doit être soit "flat" soit "per_kg".',

                'free_threshold.integer' => 'Le seuil de gratuité doit être un nombre entier.',
                'free_threshold.min'     => 'Le seuil de gratuité doit être ≥ 0.',

                'sort_order.integer' => 'L’ordre doit être un nombre entier.',

                'active.boolean'   => 'Le champ actif doit être vrai ou faux.',
            ]
        );
    }


}
