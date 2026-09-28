<?php

namespace Modules\Promo\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Catalog\Models\Product;

/**
 * Promotion appliquée automatiquement, sans code à saisir par la cliente.
 */
class Promotion extends Model
{
    use TracksOperator;

    /**
     * Nature de l'opération, indépendante de sa portée et du calcul.
     * Sert au suivi commercial et au badge affiché en boutique.
     */
    public const TYPE_STANDARD  = 'standard';    // opération courante
    public const TYPE_CLEARANCE = 'clearance';   // écoulement de stock
    public const TYPE_SEASONAL  = 'seasonal';    // soldes, fêtes, saison
    public const TYPE_LAUNCH    = 'launch';      // lancement d'un produit
    public const TYPE_LOYALTY   = 'loyalty';     // geste envers les fidèles

    public const TYPES = [
        self::TYPE_STANDARD,
        self::TYPE_CLEARANCE,
        self::TYPE_SEASONAL,
        self::TYPE_LAUNCH,
        self::TYPE_LOYALTY,
    ];

    /** Badge par défaut, quand aucun libellé n'est saisi. */
    public const TYPE_BADGES = [
        self::TYPE_CLEARANCE => 'Déstockage',
        self::TYPE_SEASONAL  => 'Offre de saison',
        self::TYPE_LAUNCH    => 'Nouveauté',
        self::TYPE_LOYALTY   => 'Offre fidélité',
    ];

    public const SCOPE_GLOBAL  = 'global';
    public const SCOPE_PRODUCT = 'product';
    public const SCOPES = [self::SCOPE_GLOBAL, self::SCOPE_PRODUCT];

    public const DISCOUNT_PERCENT = 'percent';
    public const DISCOUNT_FIXED   = 'fixed';
    public const DISCOUNT_TYPES   = [self::DISCOUNT_PERCENT, self::DISCOUNT_FIXED];

    protected $fillable = [
        'name', 'description', 'scope', 'type', 'discount_type', 'value',
        'max_discount_amount', 'banner_label',
        'starts_at', 'ends_at', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'value'               => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'starts_at'           => 'datetime',
            'ends_at'             => 'datetime',
            'is_active'           => 'boolean',
            'priority'            => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        // Un article peut porter sa propre remise au sein de l'opération :
        // colonnes nulles = il suit celle de la promotion.
        return $this->belongsToMany(Product::class, 'promotion_product')
            ->withPivot(['discount_type', 'value']);
    }

    /** Promotions actives à l'instant présent (dates ouvertes incluses). */
    public function scopeActiveNow(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isRunning(): bool
    {
        if (! $this->is_active) return false;
        if ($this->starts_at && now()->lt($this->starts_at)) return false;
        if ($this->ends_at && now()->gt($this->ends_at)) return false;
        return true;
    }

    /** Opération d'écoulement de stock — badge et suivi distincts. */
    public function isClearance(): bool
    {
        return $this->type === self::TYPE_CLEARANCE;
    }

    /** Libellé du badge affiché sur la fiche produit. */
    public function badge(): string
    {
        if ($this->banner_label) {
            return $this->banner_label;
        }

        if ($this->discount_type === self::DISCOUNT_PERCENT) {
            return '−' . rtrim(rtrim(number_format((float) $this->value, 2, ',', ' '), '0'), ',') . ' %';
        }

        return self::TYPE_BADGES[$this->type] ?? 'Promotion';
    }

    /**
     * Prix après remise, jamais négatif.
     *
     * Une remise fixe supérieure au prix donnerait un montant négatif : on
     * plafonne à zéro plutôt que de laisser filer une valeur absurde jusque
     * dans le total de la commande.
     */
    public function priceFor(float $price, ?string $discountType = null, float|int|string|null $value = null): float
    {
        // Remise propre à l'article si elle est renseignée, sinon celle de
        // l'opération.
        $type   = $discountType ?: $this->discount_type;
        $amount = $value !== null ? (float) $value : (float) $this->value;

        $discount = $type === self::DISCOUNT_PERCENT
            ? $price * $amount / 100
            : $amount;

        // Le plafond de l'opération vaut aussi pour une remise dérogatoire :
        // c'est lui qui borne ce que la campagne peut coûter.
        if ($this->max_discount_amount !== null) {
            $discount = min($discount, (float) $this->max_discount_amount);
        }

        return round(max(0, $price - $discount), 2);
    }

    /**
     * Prix de cet article sous cette promotion, remise dérogatoire comprise.
     *
     * `pivot` n'est présent que si le produit a été chargé via la relation ;
     * on retombe sinon sur la remise générale.
     */
    public function priceForProduct(Product $product): float
    {
        return $this->priceFor(
            (float) $product->price,
            $product->pivot->discount_type ?? null,
            $product->pivot->value ?? null,
        );
    }
}
