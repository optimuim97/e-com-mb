<?php

namespace App\Support\Auditing;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Trace l'opérateur derrière chaque écriture.
 *
 * Deux niveaux complémentaires :
 *   · `created_by` / `updated_by` répondent à « qui a touché à ça ? » d'un
 *     coup d'œil, sans requête supplémentaire ;
 *   · le journal d'activité conserve le détail — quel champ, avant/après,
 *     quand — pour les cas où il faut reconstituer une décision.
 *
 * Les deux colonnes se remplissent seules à l'enregistrement : compter sur
 * chaque contrôleur pour les renseigner garantissait de les oublier quelque
 * part, et un audit incomplet ne vaut guère mieux que pas d'audit.
 */
trait TracksOperator
{
    use LogsActivity;

    public static function bootTracksOperator(): void
    {
        static::creating(function ($model) {
            if ($id = auth()->id()) {
                $model->created_by ??= $id;
                $model->updated_by ??= $id;
            }
        });

        static::updating(function ($model) {
            if ($id = auth()->id()) {
                $model->updated_by = $id;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Ce qui part au journal.
     *
     * `logOnlyDirty` : n'enregistrer que les champs réellement modifiés. Sans
     * cela chaque entrée reproduirait l'objet entier et l'historique deviendrait
     * illisible. `dontSubmitEmptyLogs` évite les entrées vides quand un
     * enregistrement ne change rien.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->auditedAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('dashboard');
    }

    /**
     * Champs suivis. Par défaut tout sauf le bruit technique ; un modèle peut
     * redéfinir cette liste pour restreindre davantage.
     *
     * @return array<int, string>
     */
    protected function auditedAttributes(): array
    {
        return ['*'];
    }

    /** Libellé lisible dans l'historique, à défaut d'un « Modèle #12 ». */
    public function getDescriptionForEvent(string $eventName): string
    {
        $label = match ($eventName) {
            'created' => 'créé',
            'updated' => 'modifié',
            'deleted' => 'supprimé',
            default   => $eventName,
        };

        return trim(class_basename($this) . ' ' . $label);
    }
}
