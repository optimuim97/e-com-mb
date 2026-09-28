<?php

namespace Modules\Order\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Order\Mail\PaymentReminderMail;
use Modules\Order\Models\Order;
use Modules\Order\Support\CustomerEmail;
use Modules\Order\Support\ShipmentSheetScope;
use Modules\Order\Support\ShippingFeeRequirement;

/**
 * Reminds customers whose order is waiting for payment: once the next day, once
 * after three days.
 *
 * Outside Abidjan nothing ships unpaid. Without a reminder the order waits in
 * silence, and a customer who believes she has ordered receives nothing — the
 * surest way to lose her.
 *
 * Only two messages, ever. What happens after the third day is a decision, not
 * a message: the order surfaces in the « À traiter » screen with a proposed
 * cancellation, and an agent decides (see OrderAttention).
 *
 * Reachable by email only. Most quick orders carry no real address — a
 * placeholder is generated to create the account — and those cannot be emailed
 * at all. They are not lost: the same screen lists them from the first day,
 * with the customer's number ready for WhatsApp, which no automation may use
 * (Meta only allows free-form sending within 24 h of a customer message).
 */
class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'shop:send-payment-reminders
                            {--dry-run : Liste les relances sans rien envoyer}';

    protected $description = 'Relance par courriel les commandes non réglées hors Abidjan (J+1 et J+3)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $sent   = 0;

        foreach ([1 => 'payment_reminder_1_sent_at', 3 => 'payment_reminder_3_sent_at'] as $day => $column) {
            $orders = $this->due($day, $column);

            $this->line(sprintf('J+%d : %d commande(s) à relancer.', $day, $orders->count()));

            foreach ($orders as $order) {
                $address = CustomerEmail::for($order);

                if ($address === null) {
                    // Injoignable par courriel : l'écran « À traiter » la porte
                    // déjà, avec le numéro pour un message à la main.
                    $this->line("  {$order->number} — pas d'adresse utilisable, laissée à l'agente.");
                    continue;
                }

                if ($dryRun) {
                    $this->line("  [SIMULATION] {$order->number} → {$address}");
                    continue;
                }

                if ($this->send($order, $day, $address)) {
                    // Marquée seulement si l'envoi a abouti : sinon la commande
                    // repart au prochain passage plutôt que d'être oubliée.
                    $order->update([$column => now()]);
                    $sent++;
                }
            }
        }

        $this->info($dryRun ? '[SIMULATION] Aucun envoi.' : "{$sent} relance(s) envoyée(s).");

        return self::SUCCESS;
    }

    /**
     * Orders due for that reminder.
     *
     * Calendar days, not opening hours: our closing days do not give the
     * customer more time to pay. No upper bound on age: a run missed for two
     * days still sends the reminder rather than skipping it for good.
     *
     * @return Collection<int, Order>
     */
    private function due(int $day, string $column): Collection
    {
        return Order::with(['user', 'items'])
            ->whereIn('status', ['pending', 'confirmed', 'processing'])
            ->whereNull('paid_at')
            ->whereNull('archived_at')
            ->whereNull($column)
            // Le second rappel suppose le premier, et à un jour d'intervalle au
            // moins : sans cette distance, une commande de trois jours jamais
            // relancée recevait les deux messages à la même minute, dont un
            // « dernier rappel » incompréhensible.
            ->when($day === 3, fn (Builder $q) => $q
                ->whereNotNull('payment_reminder_1_sent_at')
                ->where('payment_reminder_1_sent_at', '<=', now()->subDay()))
            ->where('created_at', '<=', now()->subDays($day))
            ->orderBy('created_at')
            ->get()
            // La zone et les frais se lisent sur la commande, pas en SQL : le
            // pays décide de la zone, et les frais d'une destination négociée
            // ne se réduisent pas à une colonne.
            ->filter(fn (Order $order) => $this->concerns($order))
            ->values();
    }

    /**
     * Only orders that payment actually blocks.
     *
     * Abidjan pays at the door, so nothing is waiting there. Abroad, an order
     * whose shipping fee is not settled yet has no firm total — asking for
     * money before the amount is known would mean asking twice.
     */
    private function concerns(Order $order): bool
    {
        $zone = ShipmentSheetScope::zoneOf($order);

        return ShipmentSheetScope::requiresPayment($zone)
            && ! ShippingFeeRequirement::isPending($order);
    }

    private function send(Order $order, int $day, string $address): bool
    {
        try {
            Mail::to($address)->send(new PaymentReminderMail($order, $day));
            $this->line("  {$order->number} → {$address}");

            return true;
        } catch (\Throwable $e) {
            // Journalisé plutôt que remonté : la tâche tourne sans personne pour
            // lire une exception, et un échec ne doit pas priver les suivantes.
            Log::error('[RelanceReglement] Envoi échoué', [
                'order' => $order->number, 'error' => $e->getMessage(),
            ]);
            $this->error("  {$order->number} : {$e->getMessage()}");

            return false;
        }
    }
}
