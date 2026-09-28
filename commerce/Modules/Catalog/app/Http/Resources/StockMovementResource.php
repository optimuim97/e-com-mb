<?php

namespace Modules\Catalog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Order\Models\Order;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Le coût d'entrée est une donnée d'achat : même traitement que les
        // autres montants privés, un agent sans `finance.view` ne le voit pas.
        $voitLesMontants = (bool) $request->user()?->can('finance.view');

        return [
            'id'           => $this->id,
            'type'         => $this->type,
            'type_label'   => $this->label,
            'direction'    => $this->direction,
            'quantity'     => $this->quantity,
            'stock_before' => $this->stock_before,
            'stock_after'  => $this->stock_after,
            'unit_cost'    => $voitLesMontants ? $this->unit_cost : null,
            'reason'       => $this->reason,
            'created_at'   => $this->created_at,

            'product' => $this->whenLoaded('product', fn () => [
                'id'   => $this->product->id,
                'name' => $this->product->name,
                'sku'  => $this->product->sku,
            ]),
            'variant' => $this->whenLoaded('variant', fn () => $this->variant ? [
                'id'    => $this->variant->id,
                'sku'   => $this->variant->sku,
                'label' => $this->variant->label,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ] : null),

            // Pièce à l'origine du mouvement. Seul le numéro de commande est
            // exposé : c'est ce qui permet de remonter à la source depuis
            // l'écran, sans charger la commande entière.
            'reference' => $this->referenceSummary(),
        ];
    }

    private function referenceSummary(): ?array
    {
        if (! $this->reference_type || ! $this->reference_id) {
            return null;
        }

        if (is_a($this->reference_type, Order::class, true)) {
            $commande = Order::select('id', 'number')->find($this->reference_id);

            return $commande ? [
                'type'  => 'order',
                'id'    => $commande->id,
                'label' => $commande->number,
            ] : null;
        }

        return [
            'type'  => class_basename($this->reference_type),
            'id'    => $this->reference_id,
            'label' => null,
        ];
    }
}
