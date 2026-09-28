<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Modules\Admin\Models\Setting;
use Modules\Order\Mail\OrderConfirmationMail;
use Modules\Order\Mail\PaymentReminderMail;
use Modules\Order\Models\Order;
use Modules\Order\Support\CustomerEmail;
use Modules\Order\Support\ShipmentSheetScope;
use Modules\Order\Support\ShopMailbox;
use Tests\TestCase;

/**
 * What the shop keeps a trace of.
 *
 * Every message written to a customer lands in the shop's mailbox too:
 * otherwise nobody can check what was promised, to whom and when, and nobody
 * notices whether the reminders go out at all.
 *
 * The evening sheets, for their part, go to a list the shop edits itself.
 */
class MailFollowUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Setting::set('shop_email', 'boutique@rosabeauty.example.com');
    }

    private function order(array $extra = []): Order
    {
        $user = User::factory()->create(['email' => 'cliente@example.com']);

        return Order::create(array_merge([
            'number'           => Order::generateNumber(),
            'user_id'          => $user->id,
            'status'           => 'pending',
            'subtotal'         => 10000,
            'shipping_cost'    => 1500,
            'discount_amount'  => 0,
            'total'            => 11500,
            'currency'         => 'XOF',
            'shipping_address' => [
                'first_name' => 'Fatou', 'city' => 'Abidjan', 'commune' => 'Cocody',
                'country' => 'CI', 'phone' => '0709080706',
            ],
        ], $extra))->refresh();
    }

    // ── La copie de suivi ───────────────────────────────────────────────────

    public function test_the_confirmation_is_copied_to_the_shop(): void
    {
        $order = $this->order();

        $mail = new OrderConfirmationMail($order);

        // En copie cachée : la cliente n'a pas à voir l'adresse interne.
        $this->assertTrue($mail->hasBcc('boutique@rosabeauty.example.com'));
    }

    public function test_the_payment_reminder_is_copied_to_the_shop(): void
    {
        $order = $this->order();

        $this->assertTrue(
            (new PaymentReminderMail($order, 1))->hasBcc('boutique@rosabeauty.example.com'),
        );
    }

    public function test_the_shop_is_not_copied_on_its_own_message(): void
    {
        // Un message d'essai adressé à la boutique arriverait deux fois.
        $order = $this->order();
        $order->user->update(['email' => 'boutique@rosabeauty.example.com']);

        $this->assertSame([], ShopMailbox::followUpBcc('boutique@rosabeauty.example.com'));
    }

    public function test_an_unusable_shop_address_adds_no_copy(): void
    {
        // Une adresse de démonstration ne doit pas recevoir de courrier.
        Setting::set('shop_email', 'admin@commerce.local');

        $this->assertSame([], ShopMailbox::followUpBcc('cliente@example.com'));
    }

    // ── L'adresse joignable, une seule règle ────────────────────────────────

    public function test_the_checkout_address_wins_over_the_account(): void
    {
        $order = $this->order([
            'shipping_address' => [
                'first_name' => 'Fatou', 'city' => 'Abidjan', 'commune' => 'Cocody',
                'country' => 'CI', 'phone' => '0709080706', 'email' => 'saisie@example.com',
            ],
        ]);

        $this->assertSame('saisie@example.com', CustomerEmail::for($order));
    }

    public function test_an_invented_address_is_never_written_to(): void
    {
        // La commande rapide fabrique une adresse pour créer le compte.
        $order = $this->order();
        $order->user->update(['email' => 'quick-0709080706@clients.local']);

        $this->assertNull(CustomerEmail::for($order->fresh()));
    }

    public function test_every_non_routable_domain_is_refused(): void
    {
        // RFC 6761 : ces suffixes ne sortent jamais sur internet.
        foreach (['a@x.local', 'a@x.invalid', 'a@x.test', 'a@x.example', 'a@localhost', 'a@commerce.local'] as $adresse) {
            $this->assertFalse(CustomerEmail::isReal($adresse), $adresse);
        }

        $this->assertTrue(CustomerEmail::isReal('fatou@gmail.com'));
    }

    // ── Les feuilles du soir ────────────────────────────────────────────────

    public function test_the_evening_sheets_go_to_every_listed_address(): void
    {
        Setting::set(
            \Modules\Order\Console\SendShipmentSheetCommand::RECIPIENTS_SETTING,
            'boutique@rosabeauty.example.com, associe@rosabeauty.example.com',
        );

        // Une commande prête, pour que la feuille ait de quoi partir.
        $this->order([
            'status'  => 'processing',
            'paid_at' => now(),
        ]);
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());

        $this->artisan('shop:send-shipment-sheet --zone=abidjan')->assertSuccessful();

        Mail::assertSent(
            \Modules\Order\Mail\ShipmentSheetMail::class,
            fn ($mail) => $mail->hasTo('boutique@rosabeauty.example.com')
                && $mail->hasTo('associe@rosabeauty.example.com'),
        );
    }

    public function test_without_a_list_the_sheets_fall_back_to_the_shop(): void
    {
        Setting::set(\Modules\Order\Console\SendShipmentSheetCommand::RECIPIENTS_SETTING, '');

        $this->order(['status' => 'processing', 'paid_at' => now()]);
        ShipmentSheetScope::rememberRun('abidjan', now()->subDay());

        $this->artisan('shop:send-shipment-sheet --zone=abidjan')->assertSuccessful();

        Mail::assertSent(
            \Modules\Order\Mail\ShipmentSheetMail::class,
            fn ($mail) => $mail->hasTo('boutique@rosabeauty.example.com'),
        );
    }
}
