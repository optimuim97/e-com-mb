<?php

namespace Modules\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variant_id',
        // Opération qui a fixé le prix, et remise consentie. Écrites à la
        // vente : le barème aura changé quand on voudra les statistiques.
        'promotion_id',
        'product_name', 'variant_label', 'sku',
        'quantity', 'unit_price', 'discount_amount', 'total_price',
    ];

    protected function casts(): array
    {
        return [
            'unit_price'      => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_price'     => 'decimal:2',
        ];
    }

    public function order()   { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class); }
}
