<?php

namespace Modules\Review\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Review extends Model
{
    protected $table = 'product_reviews';

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'title',
        'comment',
        'status',
        'admin_note',
        'helpful_count',
    ];

    protected function casts(): array
    {
        return [
            'rating'        => 'integer',
            'helpful_count' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(\Modules\Catalog\Models\Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    // Scopes
    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', 'approved');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }
}
