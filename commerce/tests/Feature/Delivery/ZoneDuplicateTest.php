<?php

namespace Tests\Feature\Delivery;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Delivery\Models\DeliveryZone;
use Tests\TestCase;

/**
 * Une destination ne doit être tarifée qu'une fois.
 *
 * Le résolveur retient la première zone qui correspond, dans l'ordre de tri :
 * deux zones « Bouaké » à des prix différents ne lèvent aucune erreur, elles
 * rendent le tarif imprévisible selon que l'agent a saisi la ville ou son
 * alias. Le doublon se refuse donc à la saisie — seul moment où quelqu'un peut
 * encore trancher, et le seul où l'écran des expéditions propose de créer une
 * zone depuis une commande.
 */
class ZoneDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);
        Sanctum::actingAs($admin);
    }

    private function zone(array $attrs = []): DeliveryZone
    {
        return DeliveryZone::create(array_merge([
            'group'   => 'Intérieur CI',
            'name'    => 'Bouaké',
            'country' => 'CI',
            'cities'  => ['bouake'],
            'price'   => 2500,
            'active'  => true,
        ], $attrs));
    }

    private function payload(array $attrs = []): array
    {
        return array_merge([
            'group'   => 'Intérieur CI',
            'name'    => 'Bouaké',
            'country' => 'CI',
            'cities'  => [],
            'price'   => 3000,
        ], $attrs);
    }

    public function test_un_nom_deja_pris_est_refuse(): void
    {
        $this->zone();

        $this->postJson('/api/admin/delivery-zones', $this->payload())
            ->assertStatus(422)
            ->assertJsonPath('zone.name', 'Bouaké');

        $this->assertSame(1, DeliveryZone::where('country', 'CI')->count());
    }

    public function test_la_comparaison_ignore_accents_et_casse(): void
    {
        $this->zone();

        // « BOUAKE » sans accent désigne la même ville : c'est exactement la
        // saisie qui créait le doublon en production.
        $this->postJson('/api/admin/delivery-zones', $this->payload(['name' => 'BOUAKE']))
            ->assertStatus(422);
    }

    public function test_un_alias_deja_couvert_est_refuse(): void
    {
        $this->zone(['name' => 'Bouaké Centre', 'cities' => ['bouake', 'bouake centre']]);

        // Le nom de la nouvelle zone tombe dans les alias de l'ancienne : le
        // résolveur les confondrait.
        $this->postJson('/api/admin/delivery-zones', $this->payload(['name' => 'Bouaké']))
            ->assertStatus(422);
    }

    public function test_un_nouvel_alias_en_conflit_est_refuse(): void
    {
        $this->zone();

        $this->postJson('/api/admin/delivery-zones', $this->payload([
            'name'   => 'Sakassou',
            'cities' => ['bouake'],
        ]))->assertStatus(422);
    }

    public function test_une_destination_libre_passe(): void
    {
        $this->zone();

        $this->postJson('/api/admin/delivery-zones', $this->payload([
            'name'   => 'Sakassou',
            'cities' => ['sakassou'],
        ]))->assertCreated();

        $this->assertSame(2, DeliveryZone::where('country', 'CI')->count());
    }

    public function test_le_meme_nom_dans_un_autre_pays_passe(): void
    {
        $this->zone();

        // Deux pays, deux tarifs : le résolveur filtre d'abord sur le pays,
        // aucune confusion possible.
        $this->postJson('/api/admin/delivery-zones', $this->payload(['country' => 'BF']))
            ->assertCreated();
    }

    public function test_une_zone_peut_etre_modifiee_sans_se_heurter_a_elle_meme(): void
    {
        $zone = $this->zone();

        $this->patchJson("/api/admin/delivery-zones/{$zone->id}", $this->payload(['price' => 4000]))
            ->assertOk()
            ->assertJsonPath('data.price', 4000);
    }

    public function test_une_modification_ne_peut_pas_empieter_sur_une_autre_zone(): void
    {
        $this->zone();
        $autre = $this->zone(['name' => 'Sakassou', 'cities' => ['sakassou']]);

        $this->patchJson("/api/admin/delivery-zones/{$autre->id}", $this->payload(['name' => 'Bouaké']))
            ->assertStatus(422);

        $this->assertSame('Sakassou', $autre->fresh()->name);
    }

    public function test_un_doublon_deja_en_base_reste_corrigeable(): void
    {
        // Deux zones « Bouaké » coexistent : c'est l'état que le garde-fou vise
        // à faire disparaître. Bloquer toute écriture sur les deux enfermerait
        // l'administration dedans.
        $this->zone();
        $doublon = $this->zone(['cities' => ['bouake', 'bouake ville']]);

        $this->patchJson("/api/admin/delivery-zones/{$doublon->id}", $this->payload([
            'name'   => 'Bouaké',
            'cities' => ['bouake', 'bouake ville'],
            'price'  => 3500,
        ]))->assertOk();

        $this->assertSame(3500, $doublon->fresh()->price);
    }
}
