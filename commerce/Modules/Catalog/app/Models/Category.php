<?php

namespace Modules\Catalog\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use TracksOperator;

    protected $fillable = ['name', 'slug', 'description', 'image', 'parent_id', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo  { return $this->belongsTo(Category::class, 'parent_id'); }
    public function children(): HasMany  { return $this->hasMany(Category::class, 'parent_id'); }
    /** Pivot multi-catégories : un produit peut appartenir à plusieurs catégories. */
    public function products(): BelongsToMany { return $this->belongsToMany(Product::class, 'category_product'); }
}
