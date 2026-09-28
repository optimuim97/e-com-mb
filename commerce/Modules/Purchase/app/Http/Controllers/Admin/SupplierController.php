<?php

namespace Modules\Purchase\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Purchase\Http\Resources\SupplierResource;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\Supplier;

class SupplierController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::crud('suppliers');
    }

    public function index(Request $request): ResourceCollection
    {
        $query = Supplier::query()->withCount('purchaseOrders')->orderBy('name');

        $query->when($request->filled('search'), function ($q) use ($request) {
            $terme = '%' . $request->string('search') . '%';
            $q->where(fn ($s) => $s->where('name', 'like', $terme)
                                   ->orWhere('contact_name', 'like', $terme)
                                   ->orWhere('phone', 'like', $terme));
        });

        $query->when($request->boolean('active_only'), fn ($q) => $q->actifs());

        return SupplierResource::collection(
            $query->paginate($request->integer('per_page') ?: 25)->withQueryString()
        );
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier->loadCount('purchaseOrders'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules());

        $supplier = Supplier::create($data);

        return response()->json(new SupplierResource($supplier), 201);
    }

    public function update(Request $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validate($this->rules($supplier->id)));

        return new SupplierResource($supplier->loadCount('purchaseOrders'));
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        // Un fournisseur rattaché à des bons d'achat n'est pas supprimable :
        // ses pièces justifient des coûts déjà entrés en stock. On l'archive.
        $rattaches = PurchaseOrder::where('supplier_id', $supplier->id)->count();

        if ($rattaches > 0) {
            $supplier->update(['is_active' => false]);

            return response()->json([
                'message' => "Ce fournisseur a {$rattaches} bon(s) d'achat : il a été désactivé plutôt que supprimé, pour conserver l'historique des coûts.",
                'archived' => true,
            ]);
        }

        $supplier->delete();

        return response()->json(['message' => 'Fournisseur supprimé.']);
    }

    /** @return array<string, string> */
    private function rules(?int $ignore = null): array
    {
        return [
            'name'         => 'required|string|max:160',
            'contact_name' => 'nullable|string|max:160',
            'phone'        => 'nullable|string|max:40',
            'email'        => 'nullable|email|max:160',
            'address'      => 'nullable|string|max:255',
            'notes'        => 'nullable|string|max:2000',
            'is_active'    => 'nullable|boolean',
        ];
    }
}
