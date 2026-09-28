<?php

namespace Modules\Purchase\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;

/**
 * Une ligne de bon d'achat : un article, une quantité, un coût.
 */
class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'variant_id',
        'quantity', 'unit_cost', 'total_cost', 'landed_unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'quantity'         => 'integer',
            'unit_cost'        => 'decimal:2',
            'total_cost'       => 'decimal:2',
            'landed_unit_cost' => 'decimal:2',
        ];
    }

    public function purchaseOrder(): BelongsTo { return $this->belongsTo(PurchaseOrder::class); }
    public function product(): BelongsTo       { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo       { return $this->belongsTo(ProductVariant::class, 'variant_id'); }
}
