<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Catalog\Models\Product;
use Modules\Promo\Models\Promotion;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Traçabilité des actions du tableau de bord.
 *
 * Deux niveaux : les colonnes created_by / updated_by pour la question
 * courante, et le journal pour reconstituer une décision.
 */
class ActivityTrailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['name' => 'Nadia']);
        $user->syncRoles(['admin']);
        return $user;
    }

    private function promotionPayload(): array
    {
        return [
            'name'          => 'Soldes',
            'scope'         => Promotion::SCOPE_GLOBAL,
            'discount_type' => Promotion::DISCOUNT_PERCENT,
            'value'         => 20,
        ];
    }

    public function test_the_operator_is_recorded_on_creation(): void
    {
        $manager = $this->manager();
        Sanctum::actingAs($manager);

        $id = $this->postJson('/api/admin/promotions', $this->promotionPayload())
            ->assertCreated()->json('id');

        $promotion = Promotion::find($id);
        $this->assertSame($manager->id, $promotion->created_by);
        $this->assertSame($manager->id, $promotion->updated_by);
        $this->assertSame('Nadia', $promotion->creator->name);
    }

    public function test_the_editor_changes_but_the_creator_stays(): void
    {
        $author = $this->manager();
        Sanctum::actingAs($author);
        $id = $this->postJson('/api/admin/promotions', $this->promotionPayload())->json('id');

        $other = User::factory()->create(['name' => 'Awa']);
        $other->syncRoles(['admin']);
        Sanctum::actingAs($other);
        $this->putJson("/api/admin/promotions/{$id}", ['value' => 25])->assertOk();

        $promotion = Promotion::find($id);
        $this->assertSame($author->id, $promotion->created_by, "L'auteur d'origine doit être conservé.");
        $this->assertSame($other->id, $promotion->updated_by);
    }

    public function test_a_change_is_logged_with_its_before_and_after(): void
    {
        $manager = $this->manager();
        Sanctum::actingAs($manager);
        $id = $this->postJson('/api/admin/promotions', $this->promotionPayload())->json('id');

        $this->putJson("/api/admin/promotions/{$id}", ['value' => 35])->assertOk();

        $entry = Activity::where('event', 'updated')->latest()->first();
        $this->assertNotNull($entry, 'La modification doit laisser une trace.');
        $this->assertSame($manager->id, $entry->causer_id);
        $this->assertSame('35.00', (string) $entry->properties['attributes']['value']);
        $this->assertSame('20.00', (string) $entry->properties['old']['value']);
    }

    public function test_only_changed_fields_are_logged(): void
    {
        // Journaliser l'objet entier à chaque écriture rendrait l'historique
        // illisible : on ne garde que ce qui bouge.
        $manager = $this->manager();
        Sanctum::actingAs($manager);
        $id = $this->postJson('/api/admin/promotions', $this->promotionPayload())->json('id');

        $this->putJson("/api/admin/promotions/{$id}", ['value' => 40])->assertOk();

        $entry = Activity::where('event', 'updated')->latest()->first();
        $this->assertSame(['value'], array_keys((array) $entry->properties['attributes']));
    }

    public function test_an_unchanged_save_leaves_no_entry(): void
    {
        $manager = $this->manager();
        Sanctum::actingAs($manager);
        $id = $this->postJson('/api/admin/promotions', $this->promotionPayload())->json('id');

        $before = Activity::count();
        $this->putJson("/api/admin/promotions/{$id}", ['value' => 20])->assertOk();

        $this->assertSame($before, Activity::count(), 'Un enregistrement sans changement ne doit rien journaliser.');
    }

    public function test_the_history_endpoint_lists_operators_and_changes(): void
    {
        $manager = $this->manager();
        Sanctum::actingAs($manager);
        $id = $this->postJson('/api/admin/promotions', $this->promotionPayload())->json('id');
        $this->putJson("/api/admin/promotions/{$id}", ['value' => 30])->assertOk();

        $entries = $this->getJson('/api/admin/activity')->assertOk()->json('data');

        $this->assertNotEmpty($entries);
        $this->assertSame('Nadia', $entries[0]['operator']['name']);
        $this->assertSame('Promotion', $entries[0]['subject']['type']);
        // La plus récente est la modification, et elle ne porte que le champ
        // réellement touché.
        $this->assertSame('updated', $entries[0]['event']);
        $this->assertSame('value', $entries[0]['changes'][0]['field']);
    }

    /**
     * L'écran filtre sur une personne : c'est la question « qu'a fait X ? »,
     * celle qu'on se pose depuis sa fiche.
     */
    public function test_the_history_can_be_filtered_by_operator(): void
    {
        $nadia = $this->manager();
        Sanctum::actingAs($nadia);
        $this->postJson('/api/admin/promotions', $this->promotionPayload())->assertCreated();

        $awa = User::factory()->create(['name' => 'Awa']);
        $awa->syncRoles(['admin']);
        Sanctum::actingAs($awa);
        $this->postJson('/api/admin/promotions', $this->promotionPayload() + ['name' => 'Fin de série']);

        $entries = $this->getJson("/api/admin/activity?operator_id={$nadia->id}")
            ->assertOk()->json('data');

        $this->assertNotEmpty($entries);
        foreach ($entries as $entry) {
            $this->assertSame('Nadia', $entry['operator']['name']);
        }
    }

    /**
     * Les filtres voyagent avec la liste : les peupler depuis les utilisateurs
     * exigerait `users.view`, une habilitation distincte de celle du journal.
     */
    public function test_the_history_carries_its_own_filter_options(): void
    {
        $manager = $this->manager();
        Sanctum::actingAs($manager);
        $this->postJson('/api/admin/promotions', $this->promotionPayload())->assertCreated();

        $reponse = $this->getJson('/api/admin/activity')->assertOk()->json();

        $this->assertSame(
            [['id' => $manager->id, 'name' => 'Nadia']],
            $reponse['operators'],
        );
        $this->assertContains('Promotion', $reponse['subject_types']);
    }

    public function test_the_history_of_a_single_item_can_be_read(): void
    {
        Sanctum::actingAs($this->manager());
        $id = $this->postJson('/api/admin/promotions', $this->promotionPayload())->json('id');
        $this->putJson("/api/admin/promotions/{$id}", ['value' => 30])->assertOk();

        $entries = $this->getJson("/api/admin/activity/Promotion/{$id}")->assertOk()->json();

        $this->assertCount(2, $entries);   // création + modification
    }

    public function test_products_are_traced_too(): void
    {
        // La traçabilité doit couvrir le catalogue, pas seulement les promotions.
        $manager = $this->manager();
        $this->actingAs($manager);

        $product = Product::factory()->create();

        $this->assertSame($manager->id, $product->fresh()->created_by);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => Product::class,
            'event'        => 'created',
            'causer_id'    => $manager->id,
        ]);
    }

    public function test_a_staff_member_cannot_read_the_history(): void
    {
        // Le journal dit qui a fait quoi : outil d'encadrement, pas de
        // surveillance entre collègues.
        $staff = User::factory()->create();
        $staff->syncRoles(['staff']);
        Sanctum::actingAs($staff);

        $this->getJson('/api/admin/activity')->assertForbidden();
    }

    public function test_the_history_cannot_be_altered(): void
    {
        Sanctum::actingAs($this->manager());
        $this->postJson('/api/admin/promotions', $this->promotionPayload());

        $entry = Activity::latest()->first();

        // Aucune route d'écriture n'existe : un journal modifiable ne
        // prouverait rien. 404 ou 405 selon le routeur, jamais un succès.
        //
        // L'assertion accepte désormais les deux, comme l'annonçait déjà la
        // phrase ci-dessus. Une route de lecture existe sur ce chemin : le
        // routeur répond 405, pas 404. Exiger 404 testait un détail du routeur
        // au lieu de la propriété qui compte — l'impossibilité d'écrire.
        $this->assertContains(
            $this->putJson("/api/admin/activity/{$entry->id}", ['description' => 'faux'])->status(),
            [404, 405],
        );
        $this->assertContains(
            $this->deleteJson("/api/admin/activity/{$entry->id}")->status(),
            [404, 405],
        );
    }
}
