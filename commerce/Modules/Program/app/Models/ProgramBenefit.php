<?php

namespace Modules\Program\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;

class ProgramBenefit extends Model
{
    use TracksOperator;

    protected $table = 'program_benefits';

    protected $fillable = [
        'tier', 'icon', 'title', 'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /* ── Scopes ── */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForTier($query, string $tier)
    {
        return $query->where('tier', $tier);
    }
}
