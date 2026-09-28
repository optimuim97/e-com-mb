<?php

namespace Modules\Coupon\Models;

use App\Support\Auditing\TracksOperator;
use App\Support\ModuleRegistry;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory, TracksOperator;

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }
    protected $fillable = [
        'code', 'customer_id', 'type', 'value', 'minimum_amount',
        'usage_limit', 'used_count', 'is_active', 'starts_at', 'expires_at',
    ];

    /** Destinataire d'un coupon nominatif. Null = code ouvert à tous. */
    public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'customer_id');
    }

    public function isNominative(): bool
    {
        return $this->customer_id !== null;
    }

    /**
     * Articles visés par le code. Vide = le code porte sur tout le panier.
     *
     * Le ciblage est une restriction d'assiette, pas une condition d'accès : le
     * code reste valide, il ne remise simplement que ces articles-là.
     */
    public function products(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\Modules\Catalog\Models\Product::class, 'coupon_product');
    }

    /** Le code ne vaut-il que pour certains articles ? */
    public function isTargeted(): bool
    {
        return $this->relationLoaded('products')
            ? $this->products->isNotEmpty()
            : $this->products()->exists();
    }

    /** Identifiants des articles visés, ou tableau vide si le code est ouvert. */
    public function targetedProductIds(): array
    {
        return $this->relationLoaded('products')
            ? $this->products->pluck('id')->all()
            : $this->products()->pluck('products.id')->all();
    }

    protected function casts(): array
    {
        return [
            'value'          => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'is_active'      => 'boolean',
            'starts_at'      => 'datetime',
            'expires_at'     => 'datetime',
        ];
    }

    public function isValid(float $cartTotal): bool
    {
        // Module Coupons éteint : aucun code ne s'applique, qu'il vienne du
        // panier, de la commande rapide ou d'un panier enregistré avant l'arrêt.
        if (! ModuleRegistry::enabled('coupons')) return false;
        if (! $this->is_active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->expires_at && now()->gt($this->expires_at)) return false;
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) return false;
        if ($this->minimum_amount && $cartTotal < $this->minimum_amount) return false;
        return true;
    }

    public function computeDiscount(float $cartTotal): float
    {
        return match ($this->type) {
            'percentage'    => round($cartTotal * $this->value / 100, 2),
            'fixed'         => min((float) $this->value, $cartTotal),
            'free_shipping' => 0,
            default         => 0,
        };
    }
}
