<?php

namespace Tests\Feature\Order;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Modules\Order\Mail\PaymentReminderMail;
use Modules\Order\Models\Order;
use Tests\TestCase;

/**
 * Two reminders for an order waiting to be paid, then nothing.
 *
 * Outside Abidjan nothing ships unpaid: without a reminder the order waits in
 * silence, and a customer who believes she has ordered receives nothing.
 *
 * What comes after the third day is a decision, not a message — the order
 * surfaces in the « À traiter » screen with a proposed cancellation.
 */
class PaymentReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow('2026-09-22 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(array $extra = [], string $city = 'Bouaké', string $country = 'CI'): Order
    {
        // Adresse unique par cliente : la fabrique s'en charge, et deux
        // commandes dans un même test ne se télescopent pas.
        $user = User::factory()->create();

        $order = Order::create(array_merge([
            'number'           => Order::generateNumber(),
            'user_id'          => $user->id,
            'status'           => 'pending',
            'subtotal'         => 10000,
            'shipping_cost'    => 1500,
            'discount_amount'  => 0,
            'total'            => 11500,
            'currency'         => 'XOF',
            'shipping_address' => [
                'first_name' => 'Fatou', 'city' => $city,
                'commune' => $city === 'Abidjan' ? 'Cocody' : null,
                'country' => $country, 'phone' => '0709080706',
            ],
        ], array_diff_key($extra, ['created_at' => true])));

        if (isset($extra['created_at'])) {
            $order->forceFill(['created_at' => $extra['created_at']])->saveQuietly();
        }

        return $order->refresh();
    }

    private function runCommand(array $options = []): void
    {
        $this->artisan('shop:send-payment-reminders', $options)->assertSuccessful();
    }

    // ── Les deux relances ───────────────────────────────────────────────────

    public function test_the_first_reminder_goes_out_the_next_day(): void
    {
        $order = $this->order(['created_at' => '2026-09-21 09:00:00']);

        $this->runCommand();

        Mail::assertSent(PaymentReminderMail::class, fn ($mail) => $mail->day === 1
            && $mail->order->is($order));
        $this->assertNotNull($order->fresh()->payment_reminder_1_sent_at);
    }

    public function test_the_same_reminder_is_never_sent_twice(): void
    {
        $this->order(['created_at' => '2026-09-21 09:00:00']);

        $this->runCommand();
        $this->runCommand();

        Mail::assertSentCount(1);
    }

    public function test_the_last_reminder_waits_for_the_third_day(): void
    {
        $order = $this->order([
            'created_at'                 => '2026-09-19 09:00:00',
            'payment_reminder_1_sent_at' => '2026-09-20 09:00:00',
        ]);

        $this->runCommand();

        Mail::assertSent(PaymentReminderMail::class, fn ($mail) => $mail->day === 3);
        $this->assertNotNull($order->fresh()->payment_reminder_3_sent_at);
    }

    public function test_the_last_reminder_never_comes_first(): void
    {
        // Trois jours, mais jamais relancée : « dernier rappel » sans premier
        // rappel serait incompréhensible.
        $this->order(['created_at' => '2026-09-19 09:00:00']);

        $this->runCommand();

        Mail::assertSent(PaymentReminderMail::class, fn ($mail) => $mail->day === 1);
        Mail::assertSentCount(1);
    }

    public function test_after_the_two_reminders_nothing_more_is_sent(): void
    {
        $this->order([
            'created_at'                 => '2026-09-10 09:00:00',
            'payment_reminder_1_sent_at' => '2026-09-11 09:00:00',
            'payment_reminder_3_sent_at' => '2026-09-13 09:00:00',
        ]);

        $this->runCommand();

        Mail::assertNothingSent();
    }

    // ── Qui n'est pas concerné ──────────────────────────────────────────────

    public function test_abidjan_is_never_chased(): void
    {
        // Réglée à la porte : rien n'attend.
        $this->order(['created_at' => '2026-09-21 09:00:00'], 'Abidjan');

        $this->runCommand();

        Mail::assertNothingSent();
    }

    public function test_a_paid_order_is_left_alone(): void
    {
        $this->order([
            'created_at' => '2026-09-21 09:00:00',
            'paid_at'    => '2026-09-21 10:00:00',
        ]);

        $this->runCommand();

        Mail::assertNothingSent();
    }

    public function test_an_order_whose_fee_is_not_set_is_not_asked_for_money(): void
    {
        // Le total n'est pas ferme : réclamer maintenant obligerait à réclamer
        // deux fois.
        $this->order([
            'created_at'    => '2026-09-21 09:00:00',
            'shipping_cost' => 0,
            'metadata'      => ['shipping_unknown' => true],
        ], 'Paris', 'FR');

        $this->runCommand();

        Mail::assertNothingSent();
    }

    public function test_an_archived_or_cancelled_order_is_left_alone(): void
    {
        $this->order(['created_at' => '2026-09-21 09:00:00', 'archived_at' => now()]);
        $this->order(['created_at' => '2026-09-21 09:00:00', 'status' => 'cancelled']);

        $this->runCommand();

        Mail::assertNothingSent();
    }

    // ── Adresses injoignables ───────────────────────────────────────────────

    public function test_a_placeholder_address_is_never_written_to(): void
    {
        // La commande rapide fabrique une adresse pour créer le compte : écrire
        // dessus ne fait que des rebonds.
        $order = $this->order(['created_at' => '2026-09-21 09:00:00']);
        $order->user->update(['email' => 'quick-0709080706@clients.local']);

        $this->runCommand();

        Mail::assertNothingSent();
        // Et surtout : pas marquée relancée, pour ne pas la croire traitée.
        $this->assertNull($order->fresh()->payment_reminder_1_sent_at);
    }

    public function test_the_address_given_at_checkout_comes_first(): void
    {
        $order = $this->order(['created_at' => '2026-09-21 09:00:00']);
        $order->update(['shipping_address' => $order->shipping_address + ['email' => 'autre@example.com']]);

        $this->runCommand();

        Mail::assertSent(PaymentReminderMail::class, fn ($mail) => $mail->hasTo('autre@example.com'));
    }

    // ── Simulation ──────────────────────────────────────────────────────────

    public function test_the_dry_run_sends_nothing_and_marks_nothing(): void
    {
        $order = $this->order(['created_at' => '2026-09-21 09:00:00']);

        $this->runCommand(['--dry-run' => true]);

        Mail::assertNothingSent();
        $this->assertNull($order->fresh()->payment_reminder_1_sent_at);
    }

    // ── Le contenu ──────────────────────────────────────────────────────────

    public function test_the_message_carries_the_amount_and_the_promised_date(): void
    {
        $order = $this->order(['created_at' => '2026-09-21 09:00:00']);

        $rendu = (new PaymentReminderMail($order, 1))->render();

        $this->assertStringContainsString('11 500', $rendu);
        $this->assertStringContainsString($order->number, $rendu);
        // La date promise donne une raison de régler maintenant.
        $this->assertStringContainsString('Arrivée prévue', $rendu);
        $this->assertStringContainsString('Fatou', $rendu);
    }
}
