<?php

namespace Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'alt', 'sort_order', 'is_cover'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
