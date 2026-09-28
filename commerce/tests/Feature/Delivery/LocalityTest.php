<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Delivery\Models\Locality;
use Tests\TestCase;

/**
 * Cities and communes served, managed from the admin.
 *
 * The list used to ship inside the front-end bundle: a town the shop delivers
 * to could not be named at checkout until someone deployed. These tests lock
 * the two levels, the shop-facing list, and what the agent may not do.
 */
class LocalityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['admin']);
    }

    private function city(string $name, array $communes = [], array $extra = []): Locality
    {
        $city = Locality::create(array_merge(['name' => $name, 'region' => 'Poro'], $extra));

        foreach ($communes as $commune) {
            Locality::create(['name' => $commune, 'parent_id' => $city->id]);
        }

        return $city->refresh();
    }

    // ── The shop's list ─────────────────────────────────────────────────────

    public function test_the_shop_only_sees_active_localities(): void
    {
        $this->city('Korhogo', ['Sinématiali', 'Sirasso']);
        $this->city('Boundiali', ['Kasséré'], ['active' => false]);

        $data = $this->getJson('/api/localities')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Korhogo', $data[0]['name']);
        $this->assertSame(['Sinématiali', 'Sirasso'], $data[0]['communes']);
    }

    public function test_a_disabled_commune_disappears_from_the_shop(): void
    {
        $city = $this->city('Korhogo', ['Sinématiali', 'Sirasso']);
        $city->communes()->where('name', 'Sirasso')->update(['active' => false]);

        // The cached list must not survive a change made in the admin.
        Locality::first()->touch();

        $this->assertSame(
            ['Sinématiali'],
            $this->getJson('/api/localities')->assertOk()->json('data.0.communes'),
        );
    }

    public function test_the_list_is_public(): void
    {
        // The address form is shown before any account exists.
        $this->city('Korhogo');

        $this->getJson('/api/localities')->assertOk();
    }

    // ── Admin management ────────────────────────────────────────────────────

    public function test_an_admin_adds_a_city_then_a_commune(): void
    {
        Sanctum::actingAs($this->admin);

        $city = $this->postJson('/api/admin/localities', [
            'name' => 'Korhogo', 'region' => 'Poro',
        ])->assertCreated()->json();

        $this->postJson('/api/admin/localities', [
            'name' => 'Niofoin', 'parent_id' => $city['id'],
        ])->assertCreated();

        $this->assertSame(
            ['Niofoin'],
            $this->getJson('/api/localities')->assertOk()->json('data.0.communes'),
        );
    }

    public function test_the_admin_list_shows_disabled_localities(): void
    {
        $this->city('Boundiali', ['Kasséré'], ['active' => false]);

        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/localities')
            ->assertOk()
            ->assertJsonPath('meta.cities', 1)
            ->assertJsonPath('meta.communes', 1)
            ->assertJsonPath('data.0.active', false);
    }

    public function test_the_search_finds_a_city_through_its_communes(): void
    {
        $this->city('Korhogo', ['Niofoin']);
        $this->city('Boundiali', ['Kasséré']);

        Sanctum::actingAs($this->admin);

        $data = $this->getJson('/api/admin/localities?q=niofoin')->assertOk()->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('Korhogo', $data[0]['name']);
    }

    public function test_removing_a_city_removes_its_communes(): void
    {
        $city = $this->city('Korhogo', ['Niofoin', 'Sirasso']);

        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/admin/localities/{$city->id}")->assertOk();

        $this->assertSame(0, Locality::count());
    }

    // ── Guard rails ─────────────────────────────────────────────────────────

    public function test_the_same_name_cannot_be_added_twice_under_a_city(): void
    {
        $city = $this->city('Korhogo', ['Niofoin']);

        Sanctum::actingAs($this->admin);

        // Accents aside, it is the same locality — and the customer would see
        // the same choice twice with no way to tell them apart.
        $this->postJson('/api/admin/localities', [
            'name' => 'niofoin', 'parent_id' => $city->id,
        ])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_a_commune_cannot_hang_off_another_commune(): void
    {
        $city    = $this->city('Korhogo', ['Niofoin']);
        $commune = $city->communes()->first();

        Sanctum::actingAs($this->admin);

        // Two levels only: a third would not be shown by the selector.
        $this->postJson('/api/admin/localities', [
            'name' => 'Quartier', 'parent_id' => $commune->id,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    public function test_a_city_holding_communes_cannot_become_a_commune(): void
    {
        $city  = $this->city('Korhogo', ['Niofoin']);
        $other = $this->city('Boundiali');

        Sanctum::actingAs($this->admin);

        $this->patchJson("/api/admin/localities/{$city->id}", [
            'name' => 'Korhogo', 'parent_id' => $other->id,
        ])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    // ── Permissions ─────────────────────────────────────────────────────────

    public function test_an_agent_may_add_a_locality_but_not_delete_one(): void
    {
        $city = $this->city('Korhogo', ['Niofoin']);

        $agent = User::factory()->create();
        $agent->syncRoles(['staff']);
        Sanctum::actingAs($agent);

        // Adding a missing town is daily work; wiping a branch is not.
        $this->postJson('/api/admin/localities', ['name' => 'Sirasso', 'parent_id' => $city->id])
            ->assertCreated();

        $this->deleteJson("/api/admin/localities/{$city->id}")->assertForbidden();
    }

    public function test_a_customer_cannot_edit_the_list(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/admin/localities', ['name' => 'Korhogo'])->assertForbidden();
    }
}
