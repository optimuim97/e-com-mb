<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'short_description',
        'price', 'compare_price', 'cost_price', 'sku',
        'stock', 'low_stock_threshold', 'weight', 'type',
        'category_id', 'has_variants', 'track_stock',
        'is_active', 'is_featured', 'meta', 'position'
    ];

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'compare_price'  => 'decimal:2',
            'cost_price'     => 'decimal:2',
            'has_variants'   => 'boolean',
            'track_stock'    => 'boolean',
            'is_active'      => 'boolean',
            'is_featured'    => 'boolean',
            'meta'           => 'array',
        ];
    }

    public function category(): BelongsTo    { return $this->belongsTo(Category::class); }
    /** Multi-catégories (pivot) — category_id reste la catégorie principale. */
    public function categories(): BelongsToMany { return $this->belongsToMany(Category::class, 'category_product'); }
    /** Multi-gammes (pivot). */
    public function productLines(): BelongsToMany { return $this->belongsToMany(\Modules\Catalog\Models\ProductLine::class, 'product_line_product'); }
    public function images(): HasMany        { return $this->hasMany(ProductImage::class)->orderBy('sort_order'); }
    // Voir Modules\Catalog\Models\Product::coverImage() : le OU non groupé
    // faisait sauter la condition sur le produit.
    public function coverImage()             { return $this->hasOne(ProductImage::class)->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id'); }
    public function variants(): HasMany      { return $this->hasMany(ProductVariant::class); }
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'product_attributes');
    }

    public function getEffectiveStockAttribute(): int
    {
        if ($this->has_variants) {
            return $this->variants->sum('stock');
        }
        return $this->stock;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
