<?php

namespace Modules\Catalog\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductVariant extends Model
{
    use TracksOperator;

    protected $fillable = [
        'product_id', 'sku', 'price', 'compare_price', 'average_cost',
        'stock', 'weight', 'image', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'         => 'decimal:2',
            'compare_price' => 'decimal:2',
            'average_cost'  => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    public function product()         { return $this->belongsTo(Product::class); }
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_variant_attributes');
    }

    public function getLabelAttribute(): string
    {
        return $this->attributeValues->map(fn($v) => $v->attribute->name . ': ' . $v->value)->join(', ');
    }
}
