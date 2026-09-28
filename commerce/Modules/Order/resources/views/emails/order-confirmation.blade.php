<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Confirmation de commande</title>
</head>
<body style="margin:0;padding:0;background:#fdf4f5;font-family:'Helvetica Neue',Arial,sans-serif;color:#3d2731;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf4f5;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(168,50,80,.08);">

                <!-- Header -->
                <tr>
                    <td style="background:linear-gradient(135deg,{{ \App\Support\Shop::primaryColor() }},#f06292);padding:32px 32px 24px;text-align:center;color:#ffffff;">
                        <h1 style="margin:0 0 8px;font-family:Georgia,serif;font-size:24px;font-weight:500;letter-spacing:.5px;">
                            {{ \App\Support\Shop::name() }}
                        </h1>
                        <p style="margin:0;font-size:14px;opacity:.92;">
                            Merci pour votre commande {{ $order->user?->name ? ', ' . explode(' ', $order->user->name)[0] : '' }} !
                        </p>
                    </td>
                </tr>

                <!-- Numéro de commande -->
                <tr>
                    <td style="padding:28px 32px 8px;">
                        <p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;">Votre commande</p>
                        <h2 style="margin:0;font-family:Georgia,serif;font-size:22px;font-weight:600;color:#3d2731;">
                            {{ $order->number }}
                        </h2>
                        <p style="margin:6px 0 0;font-size:13px;color:#6b7280;">
                            Passée le {{ $order->created_at?->format('d/m/Y à H:i') }}
                        </p>
                    </td>
                </tr>

                <!-- Intro -->
                <tr>
                    <td style="padding:16px 32px 8px;font-size:14px;line-height:1.6;color:#4b5563;">
                        <p style="margin:0 0 12px;">
                            Nous avons bien reçu votre commande et notre équipe la prépare avec soin.
                            Vous recevrez un nouveau message dès qu'elle sera expédiée, avec votre numéro de suivi.
                        </p>
                        <p style="margin:0;">
                            Votre <strong>facture en PDF</strong> est jointe à cet e-mail.
                        </p>
                    </td>
                </tr>

                <!-- Articles -->
                <tr>
                    <td style="padding:16px 32px 0;">
                        <h3 style="margin:0 0 12px;font-family:Georgia,serif;font-size:14px;font-weight:600;color:#3d2731;border-bottom:1px solid #f3e5e9;padding-bottom:8px;">
                            Articles commandés
                        </h3>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            @foreach($order->items as $item)
                                <tr>
                                    <td style="padding:8px 0;font-size:13px;color:#4b5563;border-bottom:1px solid #fdf4f5;">
                                        <strong style="color:#3d2731;">{{ $item->product?->name ?? $item->name ?? 'Produit' }}</strong>
                                        <span style="color:#9ca3af;"> × {{ $item->quantity }}</span>
                                    </td>
                                    <td style="padding:8px 0;font-size:13px;color:#3d2731;text-align:right;border-bottom:1px solid #fdf4f5;white-space:nowrap;">
                                        {{ number_format($item->unit_price * $item->quantity, 0, ',', ' ') }} FCFA
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </td>
                </tr>

                <!-- Totaux -->
                <tr>
                    <td style="padding:16px 32px 8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:4px 0;font-size:13px;color:#6b7280;">Sous-total</td>
                                <td style="padding:4px 0;font-size:13px;color:#3d2731;text-align:right;">
                                    {{ number_format($order->subtotal, 0, ',', ' ') }} FCFA
                                </td>
                            </tr>
                            @if($order->discount_amount > 0)
                                <tr>
                                    <td style="padding:4px 0;font-size:13px;color:#15803d;">Réduction</td>
                                    <td style="padding:4px 0;font-size:13px;color:#15803d;text-align:right;">
                                        − {{ number_format($order->discount_amount, 0, ',', ' ') }} FCFA
                                    </td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:4px 0;font-size:13px;color:#6b7280;">Livraison</td>
                                <td style="padding:4px 0;font-size:13px;color:#3d2731;text-align:right;">
                                    @if($order->shipping_cost > 0)
                                        {{ number_format($order->shipping_cost, 0, ',', ' ') }} FCFA
                                    @else
                                        À confirmer
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" style="padding-top:12px;border-top:2px solid {{ \App\Support\Shop::primaryColor() }};"></td>
                            </tr>
                            <tr>
                                <td style="padding:8px 0 0;font-family:Georgia,serif;font-size:16px;font-weight:600;color:#3d2731;">Total</td>
                                <td style="padding:8px 0 0;font-family:Georgia,serif;font-size:18px;font-weight:700;color:{{ \App\Support\Shop::primaryColor() }};text-align:right;">
                                    {{ number_format($order->total, 0, ',', ' ') }} FCFA
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Adresse + paiement -->
                <tr>
                    <td style="padding:24px 32px 8px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td valign="top" style="width:50%;padding-right:8px;font-size:12px;color:#6b7280;">
                                    <strong style="display:block;margin-bottom:4px;color:#3d2731;font-size:11px;text-transform:uppercase;letter-spacing:.08em;">
                                        Livraison
                                    </strong>
                                    @php $addr = $order->shipping_address ?? []; @endphp
                                    {{ ($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '') }}<br>
                                    {{ $addr['phone'] ?? '' }}<br>
                                    {{ $addr['address_line1'] ?? '' }}<br>
                                    {{ $addr['commune'] ?? '' }} {{ $addr['city'] ?? '' }} — {{ $addr['country'] ?? '' }}
                                </td>
                                <td valign="top" style="width:50%;padding-left:8px;font-size:12px;color:#6b7280;">
                                    <strong style="display:block;margin-bottom:4px;color:#3d2731;font-size:11px;text-transform:uppercase;letter-spacing:.08em;">
                                        Paiement
                                    </strong>
                                    {{ $order->payment_method ?? '—' }}<br>
                                    @if($order->paid_at)
                                        <span style="color:#15803d;">✓ Payée le {{ $order->paid_at->format('d/m/Y') }}</span>
                                    @else
                                        <span style="color:#b45309;">⏳ En attente de paiement</span>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <!-- Note -->
                @if($order->customer_note)
                    <tr>
                        <td style="padding:16px 32px 0;font-size:12px;color:#6b7280;">
                            <strong>Note :</strong> {{ $order->customer_note }}
                        </td>
                    </tr>
                @endif

                <!-- CTA -->
                <tr>
                    <td align="center" style="padding:28px 32px 8px;">
                        <a href="{{ config('app.shop_url', config('app.url')) }}/orders/{{ $order->number }}"
                           style="display:inline-block;background:{{ \App\Support\Shop::primaryColor() }};color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:9999px;font-weight:600;font-size:14px;">
                            Suivre ma commande
                        </a>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:32px;text-align:center;font-size:11px;color:#9ca3af;line-height:1.6;">
                        Une question ? Répondez simplement à cet e-mail
                        @if (\App\Support\Shop::phone())
                            ou contactez-nous au
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', \App\Support\Shop::phone()) }}" style="color:{{ \App\Support\Shop::primaryColor() }};text-decoration:none;">{{ \App\Support\Shop::phone() }}</a>
                        @endif
                        <br>
                        {{ \App\Support\Shop::name() }}{{ \App\Support\Shop::location() ? ' — ' . \App\Support\Shop::location() : '' }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

</body>
</html>
