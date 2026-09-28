<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ResourcePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Spatie\Activitylog\Models\Activity;

/**
 * Historique des modifications faites depuis le tableau de bord.
 *
 * Consultation seule : un journal que l'on peut modifier ne prouve rien.
 */
class ActivityLogController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return ResourcePermissions::build(['activity.view' => ['index', 'forSubject']]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'operator_id' => 'nullable|integer|exists:users,id',
            'subject_type'=> 'nullable|string|max:120',
            'event'       => 'nullable|in:created,updated,deleted',
            'date_from'   => 'nullable|date',
            'date_to'     => 'nullable|date',
            'per_page'    => 'nullable|integer|min:10|max:100',
        ]);

        // Tri sur l'identifiant et non la date : `created_at` n'a qu'une
        // précision à la seconde, et deux écritures rapprochées
        // ressortiraient dans un ordre arbitraire.
        $query = Activity::query()
            ->with('causer:id,name,email')
            ->orderByDesc('id');

        if ($id = $request->integer('operator_id')) {
            $query->where('causer_id', $id);
        }
        if ($type = $request->string('subject_type')->toString()) {
            // On accepte le nom court (« Product ») plutôt que la classe
            // complète : l'interface n'a pas à connaître les namespaces.
            $query->where('subject_type', 'like', "%\\{$type}");
        }
        if ($event = $request->string('event')->toString()) {
            $query->where('event', $event);
        }
        if ($from = $request->date('date_from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->date('date_to')) {
            $query->where('created_at', '<=', $to->endOfDay());
        }

        $page = $query->paginate((int) $request->get('per_page', 40))
            ->through(fn (Activity $a) => $this->present($a));

        // Les filtres voyagent avec la liste : l'écran n'a pas à interroger les
        // utilisateurs pour peupler son select — ce qui exigerait `users.view`,
        // une habilitation distincte de celle du journal.
        return response()->json($page->toArray() + [
            'operators'     => $this->operators(),
            'subject_types' => $this->subjectTypes(),
        ]);
    }

    /**
     * Les personnes qui apparaissent dans le journal.
     *
     * Tirées du journal lui-même plutôt que de la liste des comptes : un compte
     * supprimé garde ses traces, et un compte qui n'a jamais rien fait n'a pas à
     * encombrer le filtre.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function operators(): array
    {
        $ids = Activity::query()
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        return User::query()
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /**
     * Les natures d'objets présentes dans le journal, en nom court.
     *
     * @return array<int, string>
     */
    private function subjectTypes(): array
    {
        return Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->map(fn ($type) => class_basename((string) $type))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Historique d'un élément précis, pour l'afficher sur sa fiche.
     */
    public function forSubject(Request $request, string $type, int $id): JsonResponse
    {
        $activities = Activity::query()
            ->with('causer:id,name,email')
            ->where('subject_type', 'like', "%\\{$type}")
            ->where('subject_id', $id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json($activities->map(fn (Activity $a) => $this->present($a)));
    }

    /**
     * Met l'entrée en forme pour l'interface.
     *
     * `attributes` porte les valeurs après modification et `old` celles
     * d'avant ; on les rapproche champ par champ pour éviter à l'écran de
     * refaire ce croisement.
     */
    private function present(Activity $activity): array
    {
        $after  = $activity->properties['attributes'] ?? [];
        $before = $activity->properties['old'] ?? [];

        $changes = [];
        foreach ($after as $field => $newValue) {
            $oldValue = $before[$field] ?? null;
            if ($oldValue == $newValue) {
                continue;
            }
            $changes[] = ['field' => $field, 'from' => $oldValue, 'to' => $newValue];
        }

        return [
            'id'          => $activity->id,
            'event'       => $activity->event,
            'description' => $activity->description,
            'subject'     => [
                'type' => class_basename((string) $activity->subject_type),
                'id'   => $activity->subject_id,
            ],
            'operator'    => $activity->causer ? [
                'id'    => $activity->causer->id,
                'name'  => $activity->causer->name,
                'email' => $activity->causer->email,
            ] : null,
            'changes'     => $changes,
            'created_at'  => $activity->created_at,
        ];
    }
}
