<?php

namespace Modules\Catalog\Models;

use App\Support\Auditing\TracksOperator;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use HasFactory, SoftDeletes, TracksOperator;

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'price',
        'compare_price',
        'cost_price',
        // Coût moyen unitaire pondéré, tenu par le module Achats.
        'average_cost',
        'sku',
        'stock',
        'low_stock_threshold',
        'weight',
        'type',
        'category_id',
        'product_line_id',
        'has_variants',
        'track_stock',
        'position',
        'is_active',
        'is_featured',
        'is_flash_sale',
        'flash_price',
        'flash_starts_at',
        'flash_ends_at',
        'is_new',
        'is_bestseller',
        'meta'
    ];

    protected function casts(): array
    {
        return [
            'price'           => 'decimal:2',
            'compare_price'   => 'decimal:2',
            'cost_price'      => 'decimal:2',
            'average_cost'    => 'decimal:2',
            'flash_price'     => 'decimal:2',
            'flash_starts_at' => 'datetime',
            'flash_ends_at'   => 'datetime',
            'has_variants'    => 'boolean',
            'track_stock'     => 'boolean',
            'is_active'       => 'boolean',
            'is_featured'     => 'boolean',
            'is_flash_sale'   => 'boolean',
            'is_new'          => 'boolean',
            'is_bestseller'   => 'boolean',
            'meta'            => 'array',
        ];
    }

    /** True if the flash sale is currently active (in time window + flag on) */
    public function getIsFlashActiveAttribute(): bool
    {
        if (!$this->is_flash_sale) return false;
        $now = now();
        if ($this->flash_starts_at && $this->flash_starts_at->gt($now)) return false;
        if ($this->flash_ends_at   && $this->flash_ends_at->lt($now))   return false;
        return true;
    }

    /**
     * Prix réellement payé : le plus bas entre le prix courant, la vente
     * flash et la promotion applicable.
     *
     * Point d'entrée unique du prix — le panier, les totaux de commande et
     * l'API en dépendent tous. Une remise branchée ailleurs se verrait à
     * l'affichage sans être facturée, ou l'inverse.
     */
    public function getEffectivePriceAttribute(): float
    {
        return app(\Modules\Promo\Services\PromotionResolver::class)->priceFor($this);
    }

    /** La promotion qui s'applique à ce produit, s'il y en a une. */
    public function getActivePromotionAttribute(): ?\Modules\Promo\Models\Promotion
    {
        return app(\Modules\Promo\Services\PromotionResolver::class)->bestFor($this);
    }

    /** Le produit est-il vendu moins cher que son prix affiché ? */
    public function getIsDiscountedAttribute(): bool
    {
        return $this->effective_price < (float) $this->price;
    }

    /** Catégorie principale (compat) — la vérité multi est dans categories(). */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Un produit peut appartenir à plusieurs catégories. */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    /**
     * Opérations promotionnelles qui ciblent cet article.
     *
     * Inverse de Promotion::products(). Sert à filtrer le catalogue sur une
     * campagne ; le prix, lui, passe toujours par le résolveur — cette
     * relation ne dit pas laquelle des opérations l'emporte.
     */
    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(\Modules\Promo\Models\Promotion::class, 'promotion_product')
            ->withPivot(['discount_type', 'value']);
    }

    /** Gamme principale (compat) — la vérité multi est dans productLines(). */
    public function productLine(): BelongsTo
    {
        return $this->belongsTo(ProductLine::class);
    }

    /** Un produit peut appartenir à plusieurs gammes de soins. */
    public function productLines(): BelongsToMany
    {
        return $this->belongsToMany(ProductLine::class, 'product_line_product');
    }
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }
    /**
     * Image de couverture : celle marquée « couverture », à défaut la première
     * du tri d'affichage.
     *
     * Écrire `->where('is_cover', true)->orWhere('sort_order', 0)` produisait
     * « product_id = X AND is_cover = 1 OR sort_order = 0 » : le OU n'étant pas
     * groupé, la condition sur le produit sautait et la requête remontait
     * l'image d'un tout autre produit — d'où la même vignette sur toutes les
     * lignes d'une commande. Un tri suffit, et il ne peut pas déborder du
     * produit.
     */
    public function coverImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Lignes de commande portant ce produit.
     *
     * Sert à agréger le stock réservé — ce qui est vendu mais pas encore parti
     * de la boutique. Voir Modules\Order\Support\StockReservation.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(\Modules\Order\Models\OrderItem::class, 'product_id');
    }
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(\Modules\Review\Models\Review::class, 'product_id');
    }

    /**
     * Range les épuisés en fin de liste, puis applique le rang de la boutique.
     *
     * Un article qu'on ne peut pas acheter n'a rien à faire en tête de page :
     * la cliente clique, découvre la rupture, et repart. Il reste visible —
     * le retirer ferait disparaître la référence des recherches et des liens
     * déjà partagés — mais il passe derrière tout ce qui est disponible.
     *
     * Le calcul reste en SQL et ne s'appuie sur aucun alias de `withSum` :
     * l'expression doit tenir dans un ORDER BY paginé, sur MySQL comme sur
     * SQLite.
     *
     * `track_stock` à faux = stock non suivi : l'article est toujours
     * disponible, jamais relégué.
     */
    public function scopeOrderByAvailability($query)
    {
        return $query->orderByRaw(
            'CASE
                WHEN products.track_stock = 0 THEN 0
                WHEN products.has_variants = 1 THEN
                    CASE WHEN COALESCE((
                        SELECT SUM(v.stock) FROM product_variants v
                        WHERE v.product_id = products.id AND v.is_active = 1
                    ), 0) <= 0 THEN 1 ELSE 0 END
                ELSE
                    CASE WHEN COALESCE(products.stock, 0) <= 0 THEN 1 ELSE 0 END
            END ASC'
        );
    }

    public function getEffectiveStockAttribute(): int
    {
        if ($this->has_variants) {
            return (int) $this->variants->sum('stock');
        }
        return (int) ($this->stock ?? 0);
    }
}
