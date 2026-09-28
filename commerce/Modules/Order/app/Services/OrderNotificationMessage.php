<?php

namespace Modules\Order\Services;

use Modules\Admin\Models\Setting;
use Modules\Order\Models\Order;
use Modules\Order\Support\DeliveryEstimate;
use App\Support\Shop;

/**
 * Rédige le message WhatsApp récapitulant une commande.
 *
 * Extrait du listener de confirmation pour être réutilisable : la même
 * formulation sert à l'envoi automatique et au renvoi manuel depuis
 * l'administration. Sans ça, deux textes différents finiraient par
 * diverger et la cliente recevrait deux versions du même récapitulatif.
 */
class OrderNotificationMessage
{
    /** Statuts commande → libellé client (français). */
    private const STATUS_LABELS = [
        'pending'    => 'En attente',
        'confirmed'  => 'Confirmée',
        'processing' => 'En préparation',
        'shipped'    => 'Expédiée',
        'delivered'  => 'Livrée',
        'cancelled'  => 'Annulée',
        'refunded'   => 'Remboursée',
    ];

    /**
     * Message complet, détail des articles inclus.
     *
     * Le détail est repris dans le texte et pas seulement dans le PDF :
     * la pièce jointe peut ne pas arriver, et une cliente qui lit son
     * récapitulatif dans la conversation n'a rien à ouvrir.
     */
    public function build(Order $order): string
    {
        $order = $order->loadMissing(['user', 'items.product']);
        $addr  = $order->shipping_address ?? [];

        $prenom = trim((string) ($addr['first_name'] ?? ''))
            ?: (explode(' ', (string) ($order->user?->name ?? ''))[0] ?: 'cliente');

        $statut = self::STATUS_LABELS[$order->status] ?? ucfirst((string) $order->status);

        $lignes = [];
        foreach ($order->items as $item) {
            // product_name est figé à la commande : le produit peut avoir été
            // renommé ou supprimé depuis, la facture doit rester fidèle.
            $nom = $item->product_name ?: ($item->product?->name ?? 'Article');
            if ($item->variant_label) {
                $nom .= " ({$item->variant_label})";
            }

            $lignes[] = sprintf(
                '• %s × %d — %s',
                $nom,
                $item->quantity,
                $this->montant((float) ($item->total_price ?: $item->unit_price * $item->quantity))
            );
        }

        $detail = $lignes ? implode("\n", $lignes) : '• (aucun article)';

        $totaux = ['Sous-total : ' . $this->montant((float) $order->subtotal)];
        if ((float) $order->discount_amount > 0) {
            $totaux[] = 'Remise : −' . $this->montant((float) $order->discount_amount);
        }
        if ((float) $order->shipping_cost > 0) {
            $totaux[] = 'Livraison : ' . $this->montant((float) $order->shipping_cost);
        }
        $totaux = implode("\n", $totaux);

        // Numéro de suivi : seule information que la cliente peut réutiliser
        // pour se renseigner elle-même, elle mérite sa ligne près du statut.
        $blocSuivi = $order->tracking_number
            ? "\nSuivi : *{$order->tracking_number}*"
            : '';

        // Promised delivery date — the very sentence the order page shows, so
        // the customer never receives two different dates.
        $eta      = DeliveryEstimate::sentence(DeliveryEstimate::for($order));
        $etaBlock = $eta ? "\n🚚 {$eta}" : '';

        $livraison = trim(implode(', ', array_filter([
            $addr['address_line1'] ?? null,
            $addr['commune'] ?? null,
            $addr['city'] ?? null,
        ])));
        // « Adresse » et non « Livraison » : le mot sert déjà à la ligne de
        // frais ci-dessus, le répéter pour l'adresse prêtait à confusion.
        $blocLivraison = $livraison !== '' ? "\n\n📍 Adresse : {$livraison}" : '';

        $blocPaiement = $this->blocPaiement($order);
        $shop = Shop::name();

        return <<<MSG
        *{$shop}*

        Bonjour {$prenom},
        Récapitulatif de votre commande n° *{$order->number}*
        Statut : {$statut}{$blocSuivi}{$etaBlock}

        *Détail :*
        {$detail}

        {$totaux}
        *Total : {$this->montant((float) $order->total)}*{$blocLivraison}{$blocPaiement}

        Merci pour votre confiance !
        {$shop}
        MSG;
    }

    /**
     * Instructions de règlement, adaptées au moyen retenu.
     *
     * Une cliente à qui on annonce « payez par Wave » sans lui donner le lien
     * ni le numéro doit écrire pour les demander. On joint donc de quoi payer
     * tout de suite — lien marchand Wave (montant prérempli) ou numéro Orange
     * Money — tant que la commande n'est pas réglée.
     */
    private function blocPaiement(Order $order): string
    {
        if ($order->paid_at) {
            return "\n\n✅ Commande déjà réglée, merci !";
        }

        $montant = $this->montant((float) $order->total);

        return match ($order->payment_method) {
            'wave'         => $this->blocWave($order, $montant),
            'orange_money' => $this->blocOperateur(
                '🟠 *Paiement Orange Money*',
                ['payment_orange_money_number', 'payment_mobile_number'],
                'payment_orange_money_instructions',
                $montant,
                $order,
            ),
            'mtn'          => $this->blocOperateur(
                '💛 *Paiement MTN MoMo*',
                ['payment_mtn_number', 'payment_mobile_number'],
                'payment_mtn_instructions',
                $montant,
                $order,
            ),
            'cod', 'delivery' => "\n\n💵 *Paiement à la livraison*\nPréparez {$montant} à remettre au livreur.",
            // Moyen non encore fixé (commande hors zone) : nos agents reviennent
            // vers la cliente, inutile de lui donner des instructions vides.
            default        => '',
        };
    }

    /** Wave : lien marchand avec montant prérempli si configuré, sinon numéro. */
    private function blocWave(Order $order, string $montant): string
    {
        $bloc = "\n\n🌊 *Paiement Wave*\nMontant : *{$montant}*";

        $lien = trim((string) Setting::get('payment_wave_link', ''));
        if ($lien !== '') {
            $sep     = str_contains($lien, '?') ? '&' : '?';
            $centime = (int) round((float) $order->total);
            $bloc   .= "\nPayer ici : {$lien}{$sep}amount={$centime}";
        }

        $numero = trim((string) (Setting::get('payment_wave_number', '') ?: Setting::get('payment_mobile_number', '')));
        if ($numero !== '') {
            $bloc .= "\nOu envoyez au : *{$numero}*";
        }

        $consignes = trim((string) Setting::get('payment_wave_instructions', ''));
        if ($consignes !== '') {
            $bloc .= "\n{$consignes}";
        }

        return $bloc . "\nMerci d'indiquer la référence *{$order->number}*.";
    }

    /**
     * Bloc générique pour un opérateur mobile : premier numéro renseigné parmi
     * les réglages proposés, puis consignes éventuelles.
     *
     * @param  string[]  $clesNumero
     */
    private function blocOperateur(
        string $titre,
        array $clesNumero,
        string $cleConsignes,
        string $montant,
        Order $order,
    ): string {
        $numero = '';
        foreach ($clesNumero as $cle) {
            $numero = trim((string) Setting::get($cle, ''));
            if ($numero !== '') {
                break;
            }
        }

        $bloc = "\n\n{$titre}\nMontant : *{$montant}*";
        if ($numero !== '') {
            $bloc .= "\nNuméro : *{$numero}*";
        }

        $consignes = trim((string) Setting::get($cleConsignes, ''));
        if ($consignes !== '') {
            $bloc .= "\n{$consignes}";
        }

        return $bloc . "\nMerci d'indiquer la référence *{$order->number}*.";
    }

    private function montant(float $valeur): string
    {
        return number_format($valeur, 0, ',', ' ') . ' F CFA';
    }
}
