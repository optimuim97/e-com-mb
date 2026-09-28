<?php

namespace Modules\Catalog\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductLine extends Model
{
    use TracksOperator;

    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'description',
        'price',
        'items',
        'cover_image',
        'color_hex',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price'     => 'decimal:2',
            'items'     => 'array',
        ];
    }

    /**
     * Un produit peut appartenir à plusieurs gammes (pivot).
     * L'ancien hasMany via products.product_line_id volait le produit à
     * l'autre gamme à chaque synchronisation.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_line_product');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->cover_image) return null;
        return asset('storage/' . $this->cover_image);
    }

    public function getProductsCountAttribute(): int
    {
        return $this->products()->where('is_active', true)->count();
    }
}
