<?php

namespace Modules\Program\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramConsultation extends Model
{
    protected $table = 'program_consultations';

    protected $fillable = [
        'user_id', 'type', 'channel', 'phone',
        'preferred_at', 'message', 'status', 'advisor_note',
    ];

    protected function casts(): array
    {
        return [
            'preferred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* ── Scopes ── */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
