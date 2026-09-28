<?php

namespace Modules\Blog\Models;

use App\Support\Auditing\TracksOperator;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use SoftDeletes, TracksOperator;

    protected $fillable = [
        'post_category_id', 'author_id',
        'title', 'slug', 'excerpt', 'content', 'cover_image', 'video_url',
        'status', 'published_at', 'reading_time',
        'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /* ── Relations ── */
    public function category() { return $this->belongsTo(PostCategory::class, 'post_category_id'); }
    public function author()   { return $this->belongsTo(User::class, 'author_id'); }

    /* ── Scopes ── */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where(fn($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    /* ── Helpers ── */
    public static function computeReadingTime(string $content): int
    {
        $words = str_word_count(strip_tags($content));
        return (int) ceil($words / 200) ?: 1;
    }

    public function getCoverUrlAttribute(): ?string
    {
        if (!$this->cover_image) return null;
        return str_starts_with($this->cover_image, 'http')
            ? $this->cover_image
            : asset('storage/' . $this->cover_image);
    }
}
