<?php

namespace Modules\Admin\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;

/**
 * Photo de la galerie de la page d'accueil.
 * Gérée depuis l'admin : upload, description, ordre, activation.
 */
class GalleryPhoto extends Model
{
    use TracksOperator;

    protected $fillable = [
        'image_path',
        'title',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }
}
