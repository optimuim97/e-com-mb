<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Admin\Models\Setting;
use Modules\Customer\Http\Resources\UserResource;
use Modules\Delivery\Models\DeliveryZone;
use Modules\Order\Support\AbidjanZone;
use Modules\Order\Support\DeliveryEstimate;
use Modules\Order\Support\ShippingFeeRequirement;
use Modules\Order\Support\OrderStatusLabel;
use Modules\Order\Services\OrderCancellation;

class OrderResource extends JsonResource
{
    /** @var array{can_cancel: bool, until: ?string, reason: ?string, code: ?string}|null */
    private ?array $etatAnnulation = null;

    public function toArray(Request $request): array
    {
        // Date de livraison promise, calculée une fois pour la structure et
        // pour la phrase qui l'accompagne.
        $estimation = DeliveryEstimate::for($this->resource);

        // Finance privée : en contexte admin, un agent sans `finance.view` ne
        // voit pas les montants (totaux, sous-total, remise, frais). La vue
        // client (routes boutique) conserve toujours ses propres montants.
        //
        // Exception : les écrans qui préparent une feuille de livraison
        // (aperçu avant export, vue par zone). Ils prévisualisent un document
        // que la même permission (`orders.view`) autorise déjà à télécharger
        // montants compris — le livreur doit savoir combien encaisser.
        // Masquer ici ne protégeait rien et affichait « 0 XOF ».
        $prepareLivraison = $request->is('api/admin/orders/export-preview')
            || $request->is('api/admin/orders/by-zone');

        $hideMoney = $request->is('api/admin/*')
            && ! $prepareLivraison
            && $request->user()
            && ! $request->user()->can('finance.view');

        return [
            'id'               => $this->id,
            'number'           => $this->number,
            'status'           => $this->status,
            // Libellé calculé ici : « en attente » ne dit pas la même chose
            // selon qu'on attend un paiement ou notre propre validation.
            'status_label'     => OrderStatusLabel::for($this->resource),
            'awaiting_payment' => OrderStatusLabel::isAwaitingPayment($this->resource),
            'subtotal'         => $hideMoney ? null : $this->subtotal,
            'shipping_cost'    => $hideMoney ? null : $this->shipping_cost,
            'discount_amount'  => $hideMoney ? null : $this->discount_amount,
            'total'            => $hideMoney ? null : $this->total,
            'amounts_hidden'   => $hideMoney,
            'currency'         => $this->currency,
            'coupon_code'      => $this->coupon_code,
            'shipping_address' => $this->shipping_address,
            'shipping_method'  => $this->shipping_method,
            // abidjan | interior | international — figée à la création, c'est
            // elle qui range la commande dans l'écran des expéditions.
            'destination'      => $this->destination,
            'shipping_zone'    => $this->resolveShippingZone(),
            // Frais non résolus par les zones : les agents doivent les renseigner
            'shipping_unknown' => (bool) ($this->metadata['shipping_unknown'] ?? false),
            // Frais encore à fixer : le total n'est pas ferme, donc ni
            // notification ni lien de paiement. Calculé côté serveur pour que
            // l'écran de traitement et les garde-fous disent la même chose.
            'shipping_fee_pending' => ShippingFeeRequirement::isPending($this->resource),
            // Date promise à la cliente, sur le calendrier de l'extraction. La
            // phrase est fournie toute faite : l'écran, le courriel et le
            // WhatsApp doivent annoncer exactement la même chose.
            'delivery_estimate'      => $estimation,
            'delivery_estimate_text' => DeliveryEstimate::sentence($estimation),
            // Destination à frais négociés : le champ reste modifiable même
            // une fois renseigné (le transporteur peut revoir son prix).
            'shipping_fee_editable' => ShippingFeeRequirement::applies($this->resource),
            'payment_method'   => $this->payment_method,
            'tracking_number'  => $this->tracking_number,
            'customer_note'    => $this->customer_note,
            'items'            => OrderItemResource::collection($this->whenLoaded('items')),
            'payments'         => $this->whenLoaded('payments'),
            // ── Traçabilité paiement (pour le front : badge payé/non payé, bouton "Payer") ──
            'is_paid'          => (bool) $this->paid_at,
            'is_online_payment' => in_array($this->payment_method, ['wave', 'orange_money', 'geniuspay', 'cinetpay'], true),
            // Lien de paiement Wave marchand (lien + montant) — indépendant de GeniusPay
            'wave_pay_url'     => $hideMoney ? null : $this->wavePayUrl(),
            // Hors Abidjan → prépaiement obligatoire (bouton Wave mis en avant côté front)
            'is_abidjan'       => AbidjanZone::isAbidjan($this->shipping_address),
            'payment_reference' => $this->metadata['geniuspay_reference']
                                    ?? $this->metadata['cinetpay_transaction_id']
                                    ?? ($this->relationLoaded('payments') ? optional($this->payments->first())->transaction_id : null),
            // ── Fenêtre d'annulation ────────────────────────────────────
            // Calculée ici plutôt que devinée par le front : le délai est
            // réglable, et deux implémentations de la même règle finiraient
            // par diverger. Le front n'a qu'à afficher ce qu'on lui dit.
            'can_cancel'         => $this->cancellation()['can_cancel'],
            'cancellable_until'  => $this->cancellation()['until'],
            'cancel_blocked_reason' => $this->cancellation()['reason'],
            // Code stable, pour que le front traduise le refus dans sa langue.
            'cancel_blocked_code'   => $this->cancellation()['code'],

            'user'             => $this->whenLoaded('user', fn() => new UserResource($this->user)),
            'paid_at'          => $this->paid_at,
            'shipped_at'       => $this->shipped_at,
            'delivered_at'     => $this->delivered_at,
            // Dernière extraction : ce qui distingue, dans la liste, ce qui est
            // déjà parti à l'impression de ce qui reste à sortir.
            'exported_at'      => $this->exported_at,
            // Rangée : hors de la liste de travail, jamais supprimée.
            'archived_at'      => $this->archived_at,
            'created_at'       => $this->created_at,
        ];
    }

    /**
     * État de la fenêtre d'annulation, calculé une seule fois par commande.
     *
     * @return array{can_cancel: bool, until: ?string, reason: ?string, code: ?string}
     */
    private function cancellation(): array
    {
        // Mémoïsé : la ressource interroge trois champs, et chacun relirait
        // sinon les réglages et refaisait les mêmes contrôles.
        if ($this->etatAnnulation !== null) {
            return $this->etatAnnulation;
        }

        $service = app(OrderCancellation::class);
        $code    = $service->refusalCode($this->resource);
        $limite  = $service->deadlineFor($this->resource);

        return $this->etatAnnulation = [
            'can_cancel' => $code === null,
            // Échéance affichée seulement tant qu'elle a un sens : une commande
            // déjà expédiée n'a pas de compte à rebours à montrer.
            'until'      => $code === null ? $limite?->toIso8601String() : null,
            'reason'     => $service->refusalReason($this->resource),
            'code'       => $code,
        ];
    }

    /**
     * Résout la zone de livraison correspondante (à la volée).
     * On utilise la commune si disponible, sinon la ville.
     * Retourne null si aucune zone ne matche (ex. commande historique
     * antérieure au système de zones).
     */
    /**
     * Lien de paiement Wave marchand : lien configuré + ?amount=<total>.
     * Uniquement pour les commandes Wave. N'interfère pas avec GeniusPay
     * (qui reste géré séparément côté paiement en ligne).
     */
    private function wavePayUrl(): ?string
    {
        if ($this->payment_method !== 'wave') return null;

        $base = trim((string) Setting::get('payment_wave_link', ''));
        if ($base === '') return null;

        $amount = (int) round((float) $this->total);
        if ($amount <= 0) return $base;

        $sep = str_contains($base, '?')
            ? (str_ends_with($base, '?') || str_ends_with($base, '&') ? '' : '&')
            : '?';

        return "{$base}{$sep}amount={$amount}";
    }

    private function resolveShippingZone(): ?array
    {
        $addr = $this->shipping_address ?? [];
        $city    = $addr['city']    ?? '';
        $commune = $addr['commune'] ?? null;
        $country = $addr['country'] ?? 'CI';

        if (!$city && !$commune) return null;

        $quote = DeliveryZone::quote($city, $country, null, $commune);
        if (!$quote) return null;

        return [
            'id'    => $quote['zone_id'],
            'name'  => $quote['zone_name'],
            'group' => $quote['group'],
        ];
    }
}
