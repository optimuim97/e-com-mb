<?php

namespace Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A city or one of its communes, as the shop offers them at checkout.
 *
 * A row with no parent is a city (a department); a row with a parent is one of
 * its communes (a sub-prefecture). The shop's selector reads this table, so
 * adding a locality in the admin is enough to make it orderable — no build,
 * no deployment.
 */
class Locality extends Model
{
    protected $fillable = ['parent_id', 'name', 'region', 'country', 'active', 'sort_order'];

    protected $casts = [
        'active'     => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Cached shape served to the shop, per country. */
    private const CACHE_KEY = 'localities:public:';

    protected static function booted(): void
    {
        // The normalised name is never set by hand: it exists only to match
        // what customers type, and must always follow the name.
        static::saving(function (self $locality) {
            $locality->slug = DeliveryZone::normalize((string) $locality->name);
        });

        // Any write empties the cache, wherever it comes from — admin screen,
        // seeder or console. A stale selector would offer a locality the shop
        // has just stopped delivering to.
        static::saved(fn (self $locality) => $locality->forgetCache());
        static::deleted(fn (self $locality) => $locality->forgetCache());
    }

    private function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY . $this->country);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function communes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    /** Cities only — the top level of the list. */
    public function scopeCities(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function isCity(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Cities and communes offered to customers, in display order.
     *
     * An inactive city hides its communes with it: the admin disables a city it
     * no longer delivers to, and nothing under it should stay orderable.
     *
     * Cached: every address form asks for it, and it changes a few times a year.
     *
     * @return array<int, array{id: int, name: string, region: ?string, communes: array<int, string>}>
     */
    public static function publicTree(string $country = 'CI'): array
    {
        return Cache::remember(self::CACHE_KEY . $country, now()->addDay(), fn () => self::query()
            ->cities()
            ->active()
            ->where('country', $country)
            ->with(['communes' => fn ($q) => $q->where('active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (self $city) => [
                'id'       => $city->id,
                'name'     => $city->name,
                'region'   => $city->region,
                'communes' => $city->communes->pluck('name')->all(),
            ])
            ->all());
    }
}
