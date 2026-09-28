<?php

namespace Modules\Cart\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Modules\Cart\Mail\AbandonedCartMail;
use Modules\Cart\Models\Cart;

class SendAbandonmentReminders extends Command
{
    protected $signature   = 'cart:send-abandonment-reminders {--dry-run : Simulate without sending emails}';
    protected $description = 'Send J+1 and J+3 abandonment reminder emails for abandoned carts (Brevo)';

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $now     = now();

        $this->info($dryRun ? '[DRY-RUN] Abandonment reminders — ' . $now : 'Sending abandonment reminders — ' . $now);

        // ── Mark newly abandoned carts ──────────────────────────────────────
        // A cart is "abandoned" when:
        //   - Has at least one item
        //   - Belongs to a user with an email
        //   - Not already marked as abandoned
        //   - Updated more than 1 hour ago (grace period for active sessions)
        //   - (No check for "converted" — handled by deleting carts on order creation)
        $newlyAbandoned = Cart::whereNull('abandoned_at')
            ->whereHas('items')
            ->whereNotNull('user_id')
            ->whereHas('user', fn($q) => $q->whereNotNull('email'))
            ->where('updated_at', '<', $now->copy()->subHour())
            ->update(['abandoned_at' => $now])
        ;

        $this->line("  Newly flagged as abandoned: {$newlyAbandoned}");

        // ── J+1 reminders (between 23h and 25h after abandonment) ──────────
        $day1Carts = Cart::with(['user', 'items.product.images', 'items.variant'])
            ->whereNotNull('abandoned_at')
            ->whereNull('reminder_1_sent_at')
            ->whereBetween('abandoned_at', [
                $now->copy()->subHours(25),
                $now->copy()->subHours(23),
            ])
            ->whereHas('user', fn($q) => $q->whereNotNull('email'))
            ->get();

        $this->line("  J+1 candidates: {$day1Carts->count()}");

        foreach ($day1Carts as $cart) {
            $this->sendReminder($cart, 1, $dryRun);
        }

        // ── J+3 reminders (between 71h and 73h after abandonment) ──────────
        $day3Carts = Cart::with(['user', 'items.product.images', 'items.variant'])
            ->whereNotNull('abandoned_at')
            ->whereNotNull('reminder_1_sent_at')  // only if J+1 was already sent
            ->whereNull('reminder_3_sent_at')
            ->whereBetween('abandoned_at', [
                $now->copy()->subHours(73),
                $now->copy()->subHours(71),
            ])
            ->whereHas('user', fn($q) => $q->whereNotNull('email'))
            ->get();

        $this->line("  J+3 candidates: {$day3Carts->count()}");

        foreach ($day3Carts as $cart) {
            $this->sendReminder($cart, 3, $dryRun);
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function sendReminder(Cart $cart, int $day, bool $dryRun): void
    {
        // Jamais d'adresse de repli : sans e-mail joignable (panier invité,
        // adresse générée d'une commande rapide), pas de relance. Un repli
        // enverrait les paniers des clients de la boutique à un tiers.
        $email = $cart->user?->email;

        if (! \Modules\Order\Support\CustomerEmail::isReal($email)) {
            return;
        }

        $label = "J+{$day} → {$email} (cart #{$cart->id}, {$cart->items->count()} items)";

        if ($dryRun) {
            $this->line("  [DRY-RUN] Would send {$label}");
            return;
        }

        try {
            Mail::to($email)->send(new AbandonedCartMail($cart, $day));

            $column = $day === 1 ? 'reminder_1_sent_at' : 'reminder_3_sent_at';
            $cart->update([$column => now()]);

            $this->line("  ✓ Sent {$label}");
            Log::info("Abandoned cart reminder J+{$day} sent", [
                'cart_id' => $cart->id,
                'user_id' => $cart->user_id,
                'email'   => $email,
            ]);
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed {$label}: " . $e->getMessage());
            Log::error("Abandoned cart reminder J+{$day} failed", [
                'cart_id' => $cart->id,
                'email'   => $email,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
