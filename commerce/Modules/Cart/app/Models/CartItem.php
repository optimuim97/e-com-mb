<?php

namespace Modules\Cart\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductVariant;
use Modules\Promo\Support\SellingPrice;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'variant_id', 'quantity'];
    protected $appends  = ['unit_price', 'total_price'];

    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class); }

    public function getUnitPriceAttribute(): float
    {
        return SellingPrice::for($this->product, $this->variant);
    }

    public function getTotalPriceAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }
}
