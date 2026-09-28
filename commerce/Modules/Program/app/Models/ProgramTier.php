<?php

namespace Modules\Program\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgramTier extends Model
{
    use TracksOperator;

    protected $table = 'program_tiers';

    protected $fillable = [
        'slug', 'name', 'icon', 'color',
        'points_min', 'points_max', 'discount_pct', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'points_min'   => 'integer',
            'points_max'   => 'integer',
            'discount_pct' => 'integer',
            'sort_order'   => 'integer',
        ];
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(ProgramBenefit::class, 'tier', 'slug')
                    ->where('is_active', true)
                    ->orderBy('sort_order');
    }
}
