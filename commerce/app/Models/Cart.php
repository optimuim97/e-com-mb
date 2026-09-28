<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_id', 'coupon_code'];

    public function items()  { return $this->hasMany(CartItem::class); }
    public function coupon() { return $this->belongsTo(Coupon::class, 'coupon_code', 'code'); }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(function ($item) {
            $price = $item->variant?->price ?? $item->product->price;
            return $price * $item->quantity;
        });
    }
}
