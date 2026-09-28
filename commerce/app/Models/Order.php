<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'number', 'user_id', 'status',
        'subtotal', 'shipping_cost', 'discount_amount', 'tax_amount', 'total', 'currency',
        'shipping_address', 'billing_address',
        'shipping_method', 'tracking_number',
        'coupon_code', 'customer_note', 'admin_note',
        'paid_at', 'shipped_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'billing_address'  => 'array',
            'subtotal'         => 'decimal:2',
            'shipping_cost'    => 'decimal:2',
            'discount_amount'  => 'decimal:2',
            'tax_amount'       => 'decimal:2',
            'total'            => 'decimal:2',
            'paid_at'          => 'datetime',
            'shipped_at'       => 'datetime',
            'delivered_at'     => 'datetime',
        ];
    }

    const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];

    public function user()     { return $this->belongsTo(User::class); }
    public function items()    { return $this->hasMany(OrderItem::class); }
    public function payments() { return $this->hasMany(Payment::class); }

    public static function generateNumber(): string
    {
        $year  = now()->year;
        $count = static::whereYear('created_at', $year)->count() + 1;
        return 'ORD-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
