<?php

namespace Tests\Feature\Cart;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Cart\Mail\AbandonedCartMail;
use Modules\Cart\Models\Cart;
use Modules\Cart\Models\CartItem;
use Modules\Catalog\Models\Product;
use Tests\TestCase;

/**
 * Relances de paniers abandonnés.
 *
 * Marque blanche : la relance part au client de la boutique, ou ne part pas.
 * L'ancienne version se rabattait sur une adresse personnelle écrite en dur :
 * chaque boutique cliente aurait envoyé les paniers de ses clients à un tiers.
 */
class AbandonmentReminderTest extends TestCase
{
    use RefreshDatabase;

    private function panierAbandonne(string $email): Cart
    {
        $user    = User::factory()->create(['email' => $email]);
        $product = Product::factory()->create();
        $cart    = Cart::factory()->create([
            'user_id'      => $user->id,
            'abandoned_at' => now()->subDay(),
        ]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);

        return $cart;
    }

    public function test_la_relance_part_au_client(): void
    {
        Mail::fake();
        $this->panierAbandonne('awa@maboutique.ci');

        $this->artisan('cart:send-abandonment-reminders')->assertSuccessful();

        Mail::assertSent(AbandonedCartMail::class, fn ($mail) => $mail->hasTo('awa@maboutique.ci'));
    }

    public function test_une_adresse_generee_ne_recoit_rien_et_rien_ne_part_ailleurs(): void
    {
        Mail::fake();
        $this->panierAbandonne('quick-0102030405@clients.local');

        $this->artisan('cart:send-abandonment-reminders')->assertSuccessful();

        Mail::assertNothingSent();
    }
}
