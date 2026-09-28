<?php

namespace Modules\Order\Models;

use App\Models\User;
use App\Support\Auditing\TracksOperator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Une sortie de livraison : les commandes qu'un livreur emporte ensemble.
 *
 * Identifiée par un code que la boutique et le livreur se disent au téléphone.
 * Tout le reste — qui livre, quelle zone, combien il rapporte — se retrouve à
 * partir de ce code.
 */
class DeliveryRound extends Model
{
    use TracksOperator;

    /**
     * Préparée mais pas encore partie : la sélection est figée, le code
     * existe, les commandes ne sont pas expédiées.
     *
     * C'est l'état dans lequel l'extraction du soir dépose son lot. Sans lui,
     * la feuille de 21 h et la tournée du lendemain étaient deux objets sans
     * lien, et rien ne garantissait que ce qui partait le matin soit ce qui
     * avait été extrait la veille.
     */
    public const STATUS_DRAFT      = 'draft';

    public const STATUS_DISPATCHED = 'dispatched';
    public const STATUS_CLOSED     = 'closed';

    protected $fillable = [
        'code', 'label', 'zone_group', 'zone_name', 'courier_id',
        'status', 'dispatched_at', 'closed_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_at' => 'datetime',
            'closed_at'     => 'datetime',
        ];
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'courier_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryRoundOrder::class)->orderBy('position');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'delivery_round_orders')
            ->withPivot(DeliveryRoundOrder::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * Ajoute `items_count` : le nombre d'articles que le livreur emporte.
     *
     * Le nombre de livraisons ne suffit pas à préparer une sortie : douze
     * commandes peuvent faire douze pots ou quarante, et c'est ce second nombre
     * qui dit si tout tient sur la moto et si rien n'a été oublié au chargement.
     *
     * Une sous-requête plutôt qu'un chargement des lignes : la liste des
     * tournées en affiche trente, elle n'a pas à ramener chaque article.
     */
    public function scopeWithItemsCount($query)
    {
        return $query->addSelect([
            'items_count' => OrderItem::query()
                ->selectRaw('COALESCE(SUM(order_items.quantity), 0)')
                ->join('delivery_round_orders', 'delivery_round_orders.order_id', '=', 'order_items.order_id')
                ->whereColumn('delivery_round_orders.delivery_round_id', 'delivery_rounds.id'),
        ]);
    }

    /** Montant total que le livreur doit rapporter. */
    public function expectedTotal(): float
    {
        return (float) $this->lines()->sum('amount_expected');
    }

    /**
     * Code de tournée : `TR-2808-A`, soit jour + mois, puis une lettre par
     * tournée du jour.
     *
     * Il est dicté au téléphone et recopié à la main sur la feuille de route.
     * D'où le format court, et l'alphabet amputé de I et O — à l'oral comme à
     * l'écrit, ils se confondent avec 1 et 0, et une tournée qu'on ne retrouve
     * pas ne sert à rien.
     *
     * Généré sous verrou : deux agents qui préparent leurs tournées en même
     * temps obtiendraient sinon la même lettre, et la seconde insertion
     * tomberait sur la contrainte d'unicité.
     */
    public static function generateCode(?\DateTimeInterface $day = null): string
    {
        $day    = $day ?? now();
        $prefix = 'TR-' . $day->format('dm');

        return DB::transaction(function () use ($prefix) {
            // Verrouille les tournées du jour le temps de choisir la lettre
            // suivante. Sans ce verrou, deux préparations simultanées liraient
            // le même dernier code.
            $taken = static::query()
                ->where('code', 'like', $prefix . '-%')
                ->lockForUpdate()
                ->pluck('code')
                ->all();

            foreach (self::suffixes() as $suffix) {
                $code = $prefix . '-' . $suffix;
                if (! in_array($code, $taken, true)) {
                    return $code;
                }
            }

            // Plus de 576 tournées dans la même journée : on ne bloque pas la
            // boutique pour autant, on rallonge le code.
            return $prefix . '-' . strtoupper(bin2hex(random_bytes(3)));
        });
    }

    /**
     * A, B, … Z (sans I ni O), puis AA, AB… — de quoi tenir largement une
     * journée, dans un ordre qui reste lisible.
     *
     * @return \Generator<string>
     */
    private static function suffixes(): \Generator
    {
        $alphabet = str_split('ABCDEFGHJKLMNPQRSTUVWXYZ');

        foreach ($alphabet as $first) {
            yield $first;
        }
        foreach ($alphabet as $first) {
            foreach ($alphabet as $second) {
                yield $first . $second;
            }
        }
    }
}
