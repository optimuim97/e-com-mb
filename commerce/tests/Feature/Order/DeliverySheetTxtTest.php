<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Feuille de livraison en texte brut.
 *
 * Elle est lue au Bloc-notes et sortie sur imprimante thermique : deux rendus
 * en police monospace, mais au jeu de caractères réduit et à la largeur
 * contrainte. Trois façons de gâcher le tirage, toutes vérifiées ici.
 */
class DeliverySheetTxtTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    /** Largeur native d'une imprimante thermique 80 mm. */
    private const LARGEUR = 48;

    /** Décorations absentes d'une police monospace basique. */
    private const HORS_JEU = [
        'tiret cadratin'           => "\u{2014}",
        'tiret demi-cadratin'      => "\u{2013}",
        'point median'             => "\u{00B7}",
        'signe multiplie'          => "\u{00D7}",
        'puce'                     => "\u{2022}",
        'apostrophe typographique' => "\u{2019}",
        'guillemet anglais'        => "\u{201C}",
        'espace insecable'         => "\u{00A0}",
        'espace fine insecable'    => "\u{202F}",
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(PermissionsSeeder::class);
        $this->setUpUser();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['admin']);

        return $admin;
    }

    private function commander(string $nomProduit, string $repere = ''): void
    {
        $produit = Product::factory()->priced(12500)->create([
            'stock' => 10,
            'name'  => $nomProduit,
        ]);

        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => 2]);

        $adresse = [
            'first_name'    => 'Fatou',
            'last_name'     => 'Kone',
            'address_line1' => 'Rue des Jardins',
            'city'          => 'Abidjan',
            'commune'       => 'Cocody',
            'country'       => 'CI',
            'phone'         => '0102030405',
        ];

        if ($repere !== '') {
            $adresse['landmark'] = $repere;
        }

        $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $adresse,
                'payment_method'   => 'cod',
            ])
            ->assertCreated();
    }

    private function feuille(): string
    {
        Sanctum::actingAs($this->admin());

        $reponse = $this->get('/api/admin/orders/export-by-zone-txt');
        $reponse->assertOk();
        $reponse->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

        return $reponse->getContent();
    }

    /** Retire le BOM : il compte pour un caractère et fausserait les largeurs. */
    private function lignes(string $texte): array
    {
        return explode("\r\n", ltrim($texte, "\u{FEFF}"));
    }

    // ── Ce qui rend un tirage flou ─────────────────────────────────────────

    public function test_aucune_ligne_ne_depasse_la_largeur_dimpression(): void
    {
        $this->commander('Elixir de Roses - Eau de Rose de Damas Premium');

        foreach ($this->lignes($this->feuille()) as $ligne) {
            $this->assertLessThanOrEqual(
                self::LARGEUR,
                mb_strlen($ligne),
                'Ligne trop large : la thermique reduira la police. ' . $ligne,
            );
        }
    }

    public function test_la_colonne_des_montants_reste_alignee(): void
    {
        $this->commander('Elixir a la Rose');

        $lignes = array_filter(
            $this->lignes($this->feuille()),
            fn ($l) => str_contains($l, 'Elixir') && str_contains($l, 'XOF'),
        );

        $this->assertNotEmpty($lignes, 'Aucune ligne de produit trouvee.');

        foreach ($lignes as $ligne) {
            $this->assertSame(self::LARGEUR, mb_strlen($ligne), 'Montant desaligne : ' . $ligne);
        }
    }

    // ── Jeu de caractères ──────────────────────────────────────────────────

    public function test_aucun_caractere_hors_police_basique(): void
    {
        // Nom collé depuis Word : apostrophe courbe et tiret cadratin.
        $this->commander("Creme d\u{2019}Or \u{2014} edition limitee", "Face a l\u{2019}ecole");

        $texte = $this->feuille();

        foreach (self::HORS_JEU as $nom => $caractere) {
            $this->assertStringNotContainsString(
                $caractere,
                $texte,
                "Un {$nom} a traverse : il force un repli proportionnel qui decale la colonne des montants.",
            );
        }

        $this->assertStringContainsString("Creme d'Or - edition", $texte);
    }

    public function test_le_bom_est_conserve(): void
    {
        $this->commander('Elixir a la Rose');

        // Sans lui, le Bloc-notes Windows casse les accents des mots français.
        $this->assertStringStartsWith("\u{FEFF}", $this->feuille());
    }

    // ── Lisibilité de la feuille ───────────────────────────────────────────

    public function test_plus_aucun_filet_lourd(): void
    {
        $this->commander('Elixir a la Rose');
        $texte = $this->feuille();

        // Une ligne de soixante-douze signes égal coûte de l'encre et du temps
        // sur une thermique sans rien apprendre au livreur.
        $this->assertStringNotContainsString('====', $texte);

        // Un seul trait subsiste, exactement à la largeur de la feuille.
        $this->assertStringContainsString(str_repeat('-', self::LARGEUR), $texte);
        $this->assertStringNotContainsString(str_repeat('-', self::LARGEUR + 1), $texte);
    }

    public function test_les_livraisons_sont_numerotees(): void
    {
        $this->commander('Elixir a la Rose');
        $texte = $this->feuille();

        // Ce qui distingue deux livraisons, c'est leur numéro d'ordre : il
        // situe le livreur dans sa tournée, ce qu'un trait ne fait pas.
        $this->assertStringContainsString('#1  ', $texte);
    }

    public function test_le_nom_de_la_cliente_ne_figure_pas_sur_la_feuille(): void
    {
        $this->commander('Elixir a la Rose');
        $texte = $this->feuille();

        // La feuille passe de main en main et traîne dans un véhicule. Le
        // téléphone suffit à joindre, l'adresse à livrer.
        $this->assertStringNotContainsString('Fatou', $texte);
        $this->assertStringNotContainsString('Kone', $texte);
        $this->assertStringContainsString('Tel : 0102030405', $texte);
    }
}
