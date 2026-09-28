<?php

namespace Modules\Order\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Traduit les filtres et les tris du tableau des commandes en SQL.
 *
 * Le tableau de l'administration laisse filtrer chaque colonne à la manière
 * d'un tableur : « contient », « entre deux dates », « supérieur à ». Le
 * tableau n'a jamais toutes les commandes en main — il les demande page par
 * page —, c'est donc ici que chaque filtre devient une clause.
 *
 * Seules les colonnes déclarées dans COLUMNS sont filtrables ou triables. Le
 * modèle reçu vient du navigateur : un identifiant de colonne ne doit jamais
 * atteindre la requête sans être passé par cette liste. Une colonne inconnue
 * est ignorée plutôt que refusée — une disposition enregistrée dans le
 * navigateur avant qu'une colonne soit renommée ne doit pas casser l'écran.
 */
final class OrderGridQuery
{
    /**
     * Colonnes du tableau : type de filtre, champs visés, tri possible.
     *
     * Une colonne peut viser plusieurs champs — « client » cherche dans le nom
     * du compte, son e-mail et le nom saisi à la livraison. Un champ préfixé
     * `user.` est lu sur le compte client.
     *
     * `finance` : réservé aux personnes qui voient les montants. Filtrer sur le
     * total permettrait sinon de retrouver un montant masqué par dichotomie.
     */
    private const COLUMNS = [
        'number'          => ['type' => 'text',   'fields' => ['number'],                                  'sort' => 'number'],
        'customer'        => ['type' => 'text',   'fields' => ['user.name', 'user.email', 'shipping_address->first_name', 'shipping_address->last_name']],
        'phone'           => ['type' => 'text',   'fields' => ['shipping_address->phone']],
        'locality'        => ['type' => 'text',   'fields' => ['shipping_address->commune', 'shipping_address->city']],
        'address'         => ['type' => 'text',   'fields' => ['shipping_address->address_line1']],
        'tracking_number' => ['type' => 'text',   'fields' => ['tracking_number'],                         'sort' => 'tracking_number'],
        'created_at'      => ['type' => 'date',   'fields' => ['created_at'],                              'sort' => 'created_at'],
        'paid_at'         => ['type' => 'date',   'fields' => ['paid_at'],                                 'sort' => 'paid_at'],
        'shipped_at'      => ['type' => 'date',   'fields' => ['shipped_at'],                              'sort' => 'shipped_at'],
        'delivered_at'    => ['type' => 'date',   'fields' => ['delivered_at'],                            'sort' => 'delivered_at'],
        'total'           => ['type' => 'number', 'fields' => ['total'],                                   'sort' => 'total',  'finance' => true],
        'status'          => ['type' => 'text',   'fields' => ['status'],                                  'sort' => 'status'],
        'payment_method'  => ['type' => 'text',   'fields' => ['payment_method'],                          'sort' => 'payment_method'],
    ];

    /** Opérateurs qui excluent : sur plusieurs champs, tous doivent les respecter. */
    private const NEGATIVE = ['notContains', 'notEqual', 'blank'];

    public function __construct(private readonly bool $seesFinance) {}

    /** Colonnes que cette personne peut filtrer ou trier. */
    private function column(string $colId): ?array
    {
        $col = self::COLUMNS[$colId] ?? null;

        if ($col === null || (($col['finance'] ?? false) && ! $this->seesFinance)) {
            return null;
        }

        return $col;
    }

    // ── Filtres ────────────────────────────────────────────────────────────

    /** @param array<string, mixed> $model  modèle de filtre du tableau, par colonne */
    public function filter(Builder $query, array $model): Builder
    {
        foreach ($model as $colId => $filtre) {
            $col = $this->column((string) $colId);

            if ($col === null || ! is_array($filtre)) {
                continue;
            }

            // Deux conditions reliées par ET / OU, telles que saisies dans le
            // menu de la colonne.
            if (isset($filtre['conditions']) && is_array($filtre['conditions'])) {
                $ou = strtoupper((string) ($filtre['operator'] ?? 'AND')) === 'OR';

                $query->where(function (Builder $groupe) use ($filtre, $col, $ou) {
                    foreach ($filtre['conditions'] as $condition) {
                        if (! is_array($condition)) {
                            continue;
                        }
                        $groupe->where(
                            fn (Builder $q) => $this->condition($q, $col, $condition),
                            boolean: $ou ? 'or' : 'and',
                        );
                    }
                });

                continue;
            }

            $query->where(fn (Builder $q) => $this->condition($q, $col, $filtre));
        }

        return $query;
    }

    /** Une condition, appliquée à chacun des champs de la colonne. */
    private function condition(Builder $query, array $col, array $condition): void
    {
        $type = (string) ($condition['type'] ?? '');

        // Sur une colonne à plusieurs champs, « contient » veut dire « l'un
        // d'eux contient », mais « ne contient pas » veut dire « aucun ne
        // contient » : sinon un client trouvé par son e-mail passerait le
        // filtre au motif que son nom de livraison, lui, ne correspond pas.
        $lien = in_array($type, self::NEGATIVE, true) ? 'and' : 'or';

        foreach ($col['fields'] as $champ) {
            $query->where(
                fn (Builder $q) => $this->field($q, $champ, $col['type'], $type, $condition),
                boolean: $lien,
            );
        }
    }

    private function field(Builder $query, string $champ, string $famille, string $type, array $condition): void
    {
        if (str_starts_with($champ, 'user.')) {
            $attribut = substr($champ, 5);

            // « Ne contient pas » ou « vide » sur le compte : une commande
            // sans compte (commande rapide invitée) les satisfait aussi.
            if (in_array($type, self::NEGATIVE, true)) {
                $query->whereDoesntHave('user')
                      ->orWhereHas('user', fn (Builder $u) => $this->compare($u, $attribut, $famille, $type, $condition));

                return;
            }

            $query->whereHas('user', fn (Builder $u) => $this->compare($u, $attribut, $famille, $type, $condition));

            return;
        }

        $this->compare($query, $champ, $famille, $type, $condition);
    }

    private function compare(Builder $q, string $champ, string $famille, string $type, array $c): void
    {
        match ($famille) {
            'number' => $this->compareNumber($q, $champ, $type, $c),
            'date'   => $this->compareDate($q, $champ, $type, $c),
            default  => $this->compareText($q, $champ, $type, $c),
        };
    }

    private function compareText(Builder $q, string $champ, string $type, array $c): void
    {
        $valeur = $this->like((string) ($c['filter'] ?? ''));

        match ($type) {
            'equals'      => $q->where($champ, '=', (string) ($c['filter'] ?? '')),
            // Une valeur absente est bien « différente » : sans le IS NULL,
            // SQL l'écarterait des deux côtés du filtre.
            'notEqual'    => $q->where(fn ($w) => $w->whereNull($champ)->orWhere($champ, '!=', (string) ($c['filter'] ?? ''))),
            'startsWith'  => $q->where($champ, 'like', $valeur . '%'),
            'endsWith'    => $q->where($champ, 'like', '%' . $valeur),
            'notContains' => $q->where(fn ($w) => $w->whereNull($champ)->orWhere($champ, 'not like', '%' . $valeur . '%')),
            'blank'       => $q->where(fn ($w) => $w->whereNull($champ)->orWhere($champ, '=', '')),
            'notBlank'    => $q->whereNotNull($champ)->where($champ, '!=', ''),
            default       => $q->where($champ, 'like', '%' . $valeur . '%'),
        };
    }

    private function compareNumber(Builder $q, string $champ, string $type, array $c): void
    {
        $de = isset($c['filter'])   ? (float) $c['filter']   : null;
        $a  = isset($c['filterTo']) ? (float) $c['filterTo'] : null;

        match ($type) {
            'blank'              => $q->whereNull($champ),
            'notBlank'           => $q->whereNotNull($champ),
            'notEqual'           => $q->where($champ, '!=', $de),
            'greaterThan'        => $q->where($champ, '>', $de),
            'greaterThanOrEqual' => $q->where($champ, '>=', $de),
            'lessThan'           => $q->where($champ, '<', $de),
            'lessThanOrEqual'    => $q->where($champ, '<=', $de),
            'inRange'            => $q->whereBetween($champ, [min($de, $a ?? $de), max($de, $a ?? $de)]),
            default              => $q->where($champ, '=', $de),
        };
    }

    /**
     * Comparaisons de dates, au jour près.
     *
     * Le tableau envoie « 2026-09-13 00:00:00 » : c'est une journée qu'on
     * désigne, pas un instant. « Entre le 12 et le 13 » inclut donc les deux
     * journées entières — c'est ce qu'attend quelqu'un qui cherche les
     * commandes d'hier et d'aujourd'hui. L'application tourne en UTC et
     * Abidjan est à GMT+0 : la date du serveur est celle de la boutique.
     */
    private function compareDate(Builder $q, string $champ, string $type, array $c): void
    {
        $de = $this->day($c['dateFrom'] ?? null);
        $a  = $this->day($c['dateTo'] ?? null);

        if ($de === null && ! in_array($type, ['blank', 'notBlank'], true)) {
            return;
        }

        match ($type) {
            'blank'       => $q->whereNull($champ),
            'notBlank'    => $q->whereNotNull($champ),
            'notEqual'    => $q->where(fn ($w) => $w->whereNull($champ)->orWhereDate($champ, '!=', $de)),
            'greaterThan' => $q->whereDate($champ, '>', $de),
            'lessThan'    => $q->whereDate($champ, '<', $de),
            'inRange'     => $q->whereDate($champ, '>=', min($de, $a ?? $de))
                               ->whereDate($champ, '<=', max($de, $a ?? $de)),
            default       => $q->whereDate($champ, '=', $de),
        };
    }

    /** « 2026-09-13 00:00:00 » → « 2026-09-13 », ou null si la date est illisible. */
    private function day(mixed $valeur): ?string
    {
        if (! is_string($valeur) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $valeur, $m)) {
            return null;
        }

        return $m[0];
    }

    /** Neutralise les jokers de LIKE saisis par l'utilisateur. */
    private function like(string $valeur): string
    {
        return addcslashes($valeur, '\\%_');
    }

    // ── Tri ────────────────────────────────────────────────────────────────

    /**
     * Applique le tri demandé, les plus récentes d'abord à défaut.
     *
     * L'identifiant départage toujours en dernier : deux commandes de la même
     * seconde ressortiraient sinon dans un ordre arbitraire, et une commande
     * pourrait apparaître sur deux pages successives — ou sur aucune.
     *
     * @param array<int, array{colId?: string, sort?: string}> $model
     */
    public function sort(Builder $query, array $model): Builder
    {
        $applique = false;

        foreach ($model as $item) {
            $col = is_array($item) ? $this->column((string) ($item['colId'] ?? '')) : null;

            if ($col === null || ! isset($col['sort'])) {
                continue;
            }

            $sens = strtolower((string) ($item['sort'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy($col['sort'], $sens);
            $applique = true;
        }

        if (! $applique) {
            $query->orderBy('created_at', 'desc');
        }

        return $query->orderBy('id', 'desc');
    }

    /**
     * Décode un paramètre JSON du tableau. Illisible = pas de filtre : on
     * préfère afficher toutes les commandes qu'une erreur 500.
     *
     * @return array<mixed>
     */
    public static function decode(mixed $brut): array
    {
        if (is_array($brut)) {
            return $brut;
        }

        if (! is_string($brut) || $brut === '') {
            return [];
        }

        $valeur = json_decode($brut, true);

        return is_array($valeur) ? $valeur : [];
    }
}
