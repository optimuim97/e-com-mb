<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'variant_id', 'quantity'];

    public function product() { return $this->belongsTo(Product::class); }
    public function variant() { return $this->belongsTo(ProductVariant::class); }

    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->variant?->price ?? $this->product->price);
    }

    public function getTotalPriceAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }
}
