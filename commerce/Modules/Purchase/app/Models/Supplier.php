<?php

namespace Modules\Purchase\Models;

use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un fournisseur.
 *
 * Archivé plutôt que supprimé : ses bons d'achat justifient les coûts
 * historiques, et un coût dont on ne peut plus dire d'où il vient ne vaut
 * pas grand-chose devant un écart de marge.
 */
class Supplier extends Model
{
    use SoftDeletes, TracksOperator;

    protected $fillable = [
        'name', 'contact_name', 'phone', 'email', 'address', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scopeActifs($query)
    {
        return $query->where('is_active', true);
    }
}
