<?php

namespace Modules\Program\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramMembership extends Model
{
    protected $table = 'program_memberships';

    protected $fillable = ['user_id', 'tier', 'points', 'points_lifetime'];

    protected function casts(): array
    {
        return [
            'points'          => 'integer',
            'points_lifetime' => 'integer',
        ];
    }

    /* ── Relations ── */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ── Helpers ── */

    /**
     * Ajoute des points et recalcule le niveau.
     */
    public function addPoints(int $amount): void
    {
        $this->points          += $amount;
        $this->points_lifetime += $amount;
        $this->recalculateTier();
        $this->save();
    }

    /**
     * Recalcule le tier selon les points cumulés (lifetime).
     */
    public function recalculateTier(): void
    {
        $tier = ProgramTier::where('points_min', '<=', $this->points_lifetime)
            ->orderByDesc('points_min')
            ->first();

        if ($tier) {
            $this->tier = $tier->slug;
        }
    }

    /**
     * Nombre de commandes (pour le dashboard).
     */
    public function getOrdersCountAttribute(): int
    {
        return \App\Models\Order::where('user_id', $this->user_id)
            ->whereIn('status', ['processing', 'shipped', 'delivered'])
            ->count();
    }
}
