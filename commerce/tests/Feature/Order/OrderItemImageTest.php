<?php

namespace Tests\Feature\Order;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Modules\Catalog\Models\ProductImage;
use Tests\Concerns\ActingAsUser;
use Tests\TestCase;

/**
 * Vignettes des lignes de commande.
 *
 * La relation `coverImage` s'écrivait
 * `->where('is_cover', true)->orWhere('sort_order', 0)`. Le OU n'étant pas
 * groupé, SQL lisait « product_id = X AND is_cover = 1 OR sort_order = 0 » :
 * la condition sur le produit sautait dès que la première branche échouait,
 * et la requête remontait l'image d'un autre produit. Sur la page d'une
 * commande, tous les articles portaient la même vignette.
 */
class OrderItemImageTest extends TestCase
{
    use RefreshDatabase, ActingAsUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Modules\Delivery\Database\Seeders\DeliveryZoneSeeder::class);
        $this->setUpUser();
    }

    private function shippingAddress(): array
    {
        return [
            'first_name'    => 'Fatou',
            'last_name'     => 'Koné',
            'address_line1' => 'Cocody, Abidjan',
            'city'          => 'Abidjan',
            'commune'       => 'Cocody',
            'country'       => 'CI',
            'phone'         => '0102030405',
        ];
    }

    public function test_chaque_ligne_porte_la_vignette_de_son_propre_produit(): void
    {
        $creme = Product::factory()->create(['name' => 'Crème hydratante', 'price' => 5000]);
        $huile = Product::factory()->create(['name' => 'Huile de ricin',   'price' => 3000]);

        // Volontairement dans cet ordre : l'image de la crème est la première
        // ligne de la table, c'est elle que la requête fautive servait à tous.
        ProductImage::create(['product_id' => $creme->id, 'path' => 'produits/creme.jpg', 'sort_order' => 0, 'is_cover' => true]);
        ProductImage::create(['product_id' => $huile->id, 'path' => 'produits/huile.jpg', 'sort_order' => 0, 'is_cover' => true]);

        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $creme->id, 'quantity' => 1]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $huile->id, 'quantity' => 1]);

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->shippingAddress(),
                'payment_method'   => 'cod',
            ])
            ->assertCreated()
            ->json('number');

        $lignes = collect($this->asUser()->getJson("/api/orders/{$numero}")->assertOk()->json('data.items'))
            ->keyBy('product_name');

        $this->assertStringContainsString('produits/creme.jpg', $lignes['Crème hydratante']['product_image']);
        $this->assertStringContainsString('produits/huile.jpg', $lignes['Huile de ricin']['product_image']);
    }

    public function test_un_produit_sans_image_ne_recupere_pas_celle_dun_autre(): void
    {
        $avecImage = Product::factory()->create(['name' => 'Masque argile', 'price' => 4000]);
        $sansImage = Product::factory()->create(['name' => 'Savon noir',    'price' => 2000]);

        ProductImage::create(['product_id' => $avecImage->id, 'path' => 'produits/masque.jpg', 'sort_order' => 0, 'is_cover' => true]);

        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $avecImage->id, 'quantity' => 1]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $sansImage->id, 'quantity' => 1]);

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->shippingAddress(),
                'payment_method'   => 'cod',
            ])
            ->assertCreated()
            ->json('number');

        $lignes = collect($this->asUser()->getJson("/api/orders/{$numero}")->assertOk()->json('data.items'))
            ->keyBy('product_name');

        $this->assertStringContainsString('produits/masque.jpg', $lignes['Masque argile']['product_image']);
        $this->assertNull($lignes['Savon noir']['product_image']);
    }

    public function test_la_couverture_prime_sur_le_premier_du_tri(): void
    {
        $produit = Product::factory()->create(['name' => 'Sérum éclat', 'price' => 9000]);

        ProductImage::create(['product_id' => $produit->id, 'path' => 'produits/serum-dos.jpg',   'sort_order' => 0, 'is_cover' => false]);
        ProductImage::create(['product_id' => $produit->id, 'path' => 'produits/serum-face.jpg',  'sort_order' => 3, 'is_cover' => true]);

        $cart = Cart::factory()->create(['user_id' => $this->user->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $produit->id, 'quantity' => 1]);

        $numero = $this->asUser()
            ->postJson('/api/orders', [
                'shipping_address' => $this->shippingAddress(),
                'payment_method'   => 'cod',
            ])
            ->assertCreated()
            ->json('number');

        $ligne = $this->asUser()->getJson("/api/orders/{$numero}")->assertOk()->json('data.items.0');

        $this->assertStringContainsString('produits/serum-face.jpg', $ligne['product_image']);
    }
}
