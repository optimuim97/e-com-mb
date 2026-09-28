<?php

namespace Modules\Cart\Models;

use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Coupon\Models\Coupon;
use Modules\Promo\Support\SellingPrice;

class Cart extends Model
{
    use HasFactory;

    protected static function newFactory(): CartFactory
    {
        return CartFactory::new();
    }
    protected $fillable = [
        'user_id',
        'session_id',
        'coupon_code',
        'abandoned_at',
        'reminder_1_sent_at',
        'reminder_3_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'abandoned_at'       => 'datetime',
            'reminder_1_sent_at' => 'datetime',
            'reminder_3_sent_at' => 'datetime',
        ];
    }

    public function items()  { return $this->hasMany(CartItem::class); }
    public function coupon() { return $this->belongsTo(Coupon::class, 'coupon_code', 'code'); }
    public function user()   { return $this->belongsTo(\App\Models\User::class); }

    public function getSubtotalAttribute(): float
    {
        return $this->items->sum(
            fn ($item) => SellingPrice::for($item->product, $item->variant) * $item->quantity
        );
    }
}
