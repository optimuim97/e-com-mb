<?php

namespace Tests\Feature;

use Modules\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Promo\Models\Promotion;
use Modules\Promo\Services\PromotionResolver;
use Tests\TestCase;

/**
 * Règle métier centrale : les promotions ne se cumulent pas. Une seule
 * s'applique — la plus prioritaire, et à priorité égale la plus
 * avantageuse pour la cliente.
 */
class PromotionResolverTest extends TestCase
{
    use RefreshDatabase;

    private PromotionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(PromotionResolver::class);
    }

    private function product(float $price = 10000): Product
    {
        return Product::factory()->create(['price' => $price, 'is_flash_sale' => false]);
    }

    private function promotion(array $attrs = []): Promotion
    {
        return Promotion::create(array_merge([
            'name'          => 'Opération',
            'scope'         => Promotion::SCOPE_GLOBAL,
            'type'          => Promotion::TYPE_STANDARD,
            'discount_type' => 'percent',
            'value'         => 20,
            'is_active'     => true,
        ], $attrs));
    }

    public function test_sans_promotion_le_prix_ne_bouge_pas(): void
    {
        $p = $this->product(10000);
        $this->assertSame(10000.0, $this->resolver->priceFor($p));
        $this->assertFalse($this->resolver->isDiscounted($p));
    }

    public function test_promotion_globale_s_applique_a_tout_le_catalogue(): void
    {
        $this->promotion(['value' => 20]);
        $p = $this->product(10000);

        $this->assertSame(8000.0, $this->resolver->priceFor($p));
        $this->assertTrue($this->resolver->isDiscounted($p));
    }

    public function test_promotion_ciblee_ne_touche_que_ses_articles(): void
    {
        $targeted   = $this->product(10000);
        $untouched = $this->product(10000);

        $promotion = $this->promotion(['scope' => Promotion::SCOPE_PRODUCT, 'value' => 30]);
        $promotion->products()->attach($targeted->id);

        $this->assertSame(7000.0, $this->resolver->priceFor($targeted));
        $this->assertSame(10000.0, $this->resolver->priceFor($untouched));
    }

    public function test_deux_promotions_ne_se_cumulent_pas(): void
    {
        // 20 % puis 30 % : si elles se cumulaient on tomberait à 5600.
        $this->promotion(['value' => 20, 'name' => 'Vingt']);
        $this->promotion(['value' => 30, 'name' => 'Trente']);

        $this->assertSame(7000.0, $this->resolver->priceFor($this->product(10000)));
    }

    public function test_la_priorite_l_emporte_sur_le_montant(): void
    {
        // Une promotion moins généreuse mais prioritaire doit gagner :
        // c'est le levier pour imposer une opération précise.
        $this->promotion(['value' => 30, 'priority' => 0, 'name' => 'Generique']);
        $this->promotion(['value' => 10, 'priority' => 5, 'name' => 'Prioritaire']);

        $this->assertSame(9000.0, $this->resolver->priceFor($this->product(10000)));
    }

    public function test_promotion_expiree_ou_future_est_ignoree(): void
    {
        $this->promotion(['value' => 50, 'ends_at' => now()->subDay()]);
        $this->promotion(['value' => 40, 'starts_at' => now()->addDay()]);

        $this->assertSame(10000.0, $this->resolver->priceFor($this->product(10000)));
    }

    public function test_promotion_inactive_est_ignoree(): void
    {
        $this->promotion(['value' => 50, 'is_active' => false]);
        $this->assertSame(10000.0, $this->resolver->priceFor($this->product(10000)));
    }

    public function test_remise_fixe_ne_rend_jamais_le_prix_negatif(): void
    {
        $this->promotion(['discount_type' => 'fixed', 'value' => 50000]);
        $this->assertSame(0.0, $this->resolver->priceFor($this->product(10000)));
    }

    public function test_la_vente_flash_reste_prise_en_compte(): void
    {
        // La vente flash est une promotion comme une autre : on garde le
        // prix le plus bas, pas celui du mécanisme le plus récent.
        $p = Product::factory()->create([
            'price'           => 10000,
            'is_flash_sale'   => true,
            'flash_price'     => 6000,
            'flash_starts_at' => now()->subHour(),
            'flash_ends_at'   => now()->addHour(),
        ]);
        $this->promotion(['value' => 20]);   // donnerait 8000, moins avantageux

        $this->assertSame(6000.0, $this->resolver->priceFor($p));
    }

    public function test_les_promotions_ne_sont_lues_qu_une_fois(): void
    {
        $this->promotion(['value' => 20]);
        $products = collect(range(1, 5))->map(fn () => $this->product(10000));

        $this->resolver->forget();
        \DB::enableQueryLog();
        $products->each(fn ($p) => $this->resolver->priceFor($p));
        $queryCount = count(\DB::getQueryLog());
        \DB::disableQueryLog();

        // Deux requêtes au total (promotions + produits liés), pas deux par
        // produit : c'est ce qui évite de faire exploser une page catalogue.
        $this->assertLessThanOrEqual(2, $queryCount,
            "Le résolveur doit charger les promotions une seule fois (vu : $queryCount requêtes).");
    }
}
