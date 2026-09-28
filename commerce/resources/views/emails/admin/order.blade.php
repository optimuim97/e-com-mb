<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification commande — {{ \App\Support\Shop::name() }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f9f4f1;
            color: #2d2d2d;
            font-size: 15px;
            line-height: 1.6;
        }
        .wrapper {
            max-width: 600px;
            margin: 32px auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        /* ── Header ── */
        .header {
            background: linear-gradient(135deg, #c8a08a 0%, #b5876a 100%);
            padding: 32px 40px;
            text-align: center;
        }
        .header__logo {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .header__logo span {
            display: block;
            font-size: 11px;
            font-weight: 400;
            letter-spacing: 4px;
            opacity: 0.85;
            margin-top: 4px;
        }

        /* ── Event badge ── */
        .event-banner {
            padding: 20px 40px;
            text-align: center;
            border-bottom: 1px solid #f0e8e3;
        }
        .event-badge {
            display: inline-block;
            padding: 8px 24px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .event-badge--placed    { background: #fef3c7; color: #92400e; }
        .event-badge--shipped   { background: #d1fae5; color: #065f46; }
        .event-badge--cancelled { background: #fee2e2; color: #991b1b; }
        .event-badge--default   { background: #e0e7ff; color: #3730a3; }

        /* ── Body ── */
        .body { padding: 32px 40px; }

        .section-title {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: #b5876a;
            margin-bottom: 12px;
        }

        /* ── Info grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 28px;
        }
        .info-cell {
            background: #faf7f5;
            border-radius: 10px;
            padding: 14px 16px;
        }
        .info-cell__label {
            font-size: 11px;
            color: #9a8a80;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 4px;
        }
        .info-cell__value {
            font-size: 15px;
            font-weight: 600;
            color: #2d2d2d;
            word-break: break-word;
        }
        .info-cell--full {
            grid-column: 1 / -1;
        }
        .info-cell--highlight .info-cell__value {
            color: #b5876a;
            font-size: 18px;
        }

        /* ── Items table ── */
        .items-section { margin-bottom: 28px; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #9a8a80;
            padding: 8px 0;
            border-bottom: 1px solid #f0e8e3;
            text-align: left;
        }
        thead th:last-child { text-align: right; }
        tbody td {
            padding: 12px 0;
            border-bottom: 1px solid #faf7f5;
            font-size: 14px;
            vertical-align: top;
        }
        tbody td:last-child { text-align: right; font-weight: 600; }
        .item-name { font-weight: 500; color: #2d2d2d; }
        .item-variant { font-size: 12px; color: #9a8a80; margin-top: 2px; }
        .item-qty { color: #9a8a80; font-size: 13px; }

        /* ── Total row ── */
        .total-row {
            background: #fdf8f5;
            border-radius: 10px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 8px;
        }
        .total-label { font-weight: 600; font-size: 14px; color: #6b5b50; }
        .total-amount { font-size: 20px; font-weight: 700; color: #b5876a; }

        /* ── CTA ── */
        .cta-section {
            text-align: center;
            margin-top: 28px;
            padding-top: 24px;
            border-top: 1px solid #f0e8e3;
        }
        .cta-btn {
            display: inline-block;
            background: #b5876a;
            color: #fff !important;
            text-decoration: none;
            padding: 14px 36px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
        }

        /* ── Footer ── */
        .footer {
            background: #faf7f5;
            padding: 20px 40px;
            text-align: center;
            border-top: 1px solid #f0e8e3;
        }
        .footer p { font-size: 12px; color: #b0a09a; }

        /* ── Responsive ── */
        @media (max-width: 480px) {
            .wrapper { margin: 0; border-radius: 0; }
            .header, .body, .footer { padding-left: 20px; padding-right: 20px; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="wrapper">

    {{-- ── Header ── --}}
    <div class="header">
        <div class="header__logo">
            {{ \App\Support\Shop::name() }}
            <span>Administration</span>
        </div>
    </div>

    {{-- ── Event banner ── --}}
    <div class="event-banner">
        @php
            $badgeClass = match($event) {
                'order.placed'    => 'event-badge--placed',
                'order.shipped'   => 'event-badge--shipped',
                'order.cancelled' => 'event-badge--cancelled',
                default           => 'event-badge--default',
            };
            $label = match($event) {
                'order.placed'    => '🛒 Nouvelle commande reçue',
                'order.shipped'   => '🚚 Commande expédiée',
                'order.cancelled' => '❌ Commande annulée',
                default           => '🔔 Notification boutique',
            };
        @endphp
        <span class="event-badge {{ $badgeClass }}">{{ $label }}</span>
    </div>

    {{-- ── Body ── --}}
    <div class="body">

        {{-- Order info grid --}}
        <p class="section-title">Informations commande</p>
        <div class="info-grid">
            <div class="info-cell">
                <div class="info-cell__label">N° commande</div>
                <div class="info-cell__value"># {{ $payload['number'] ?? '—' }}</div>
            </div>
            <div class="info-cell">
                <div class="info-cell__label">Date</div>
                <div class="info-cell__value">
                    {{ isset($payload['created_at']) ? \Carbon\Carbon::parse($payload['created_at'])->setTimezone('Africa/Abidjan')->format('d/m/Y à H\hi') : '—' }}
                </div>
            </div>
            <div class="info-cell">
                <div class="info-cell__label">Statut</div>
                <div class="info-cell__value">
                    @php
                        $statusLabels = [
                            'pending'    => 'En attente',
                            'confirmed'  => 'Confirmée',
                            'processing' => 'En traitement',
                            'shipped'    => 'Expédiée',
                            'delivered'  => 'Livrée',
                            'cancelled'  => 'Annulée',
                        ];
                    @endphp
                    {{ $statusLabels[$payload['status'] ?? ''] ?? ucfirst($payload['status'] ?? '—') }}
                </div>
            </div>
            <div class="info-cell">
                <div class="info-cell__label">Paiement</div>
                <div class="info-cell__value">
                    @php
                        $methodLabels = [
                            'wave'         => 'Wave',
                            'orange_money' => 'Orange Money',
                            'mtn_momo'     => 'MTN MoMo',
                            'cash'         => 'Espèces',
                            'card'         => 'Carte',
                        ];
                    @endphp
                    {{ $methodLabels[$payload['payment_method'] ?? ''] ?? ucfirst($payload['payment_method'] ?? '—') }}
                </div>
            </div>

            {{-- Shipping method --}}
            @if (!empty($payload['shipping_method']))
            <div class="info-cell">
                <div class="info-cell__label">Livraison</div>
                <div class="info-cell__value">
                    {{ $payload['shipping_method'] === 'pickup' ? '🏠 Retrait boutique' : '🚚 Livraison domicile' }}
                </div>
            </div>
            @endif

            {{-- Commune --}}
            @if (!empty($payload['commune']))
            <div class="info-cell">
                <div class="info-cell__label">Commune</div>
                <div class="info-cell__value">{{ $payload['commune'] }}</div>
            </div>
            @endif
        </div>

        {{-- Customer info --}}
        <p class="section-title">Client</p>
        <div class="info-grid" style="margin-bottom: 28px;">
            <div class="info-cell">
                <div class="info-cell__label">Nom</div>
                <div class="info-cell__value">{{ $payload['customer_name'] ?? '—' }}</div>
            </div>
            <div class="info-cell">
                <div class="info-cell__label">Téléphone</div>
                <div class="info-cell__value">{{ $payload['customer_phone'] ?? '—' }}</div>
            </div>
            @if (!empty($payload['customer_email']))
            <div class="info-cell info-cell--full">
                <div class="info-cell__label">Email</div>
                <div class="info-cell__value">{{ $payload['customer_email'] }}</div>
            </div>
            @endif
        </div>

        {{-- Items --}}
        @if (!empty($payload['items']))
        <div class="items-section">
            <p class="section-title">Articles ({{ $payload['items_count'] ?? count($payload['items']) }})</p>
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th style="text-align:center;">Qté</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($payload['items'] as $item)
                    <tr>
                        <td>
                            <div class="item-name">{{ $item['name'] }}</div>
                            @if (!empty($item['variant']))
                            <div class="item-variant">{{ $item['variant'] }}</div>
                            @endif
                        </td>
                        <td style="text-align:center;" class="item-qty">× {{ $item['qty'] }}</td>
                        <td>{{ number_format($item['total'], 0, ',', ' ') }} {{ $payload['currency'] ?? 'XOF' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Totals --}}
            <div class="total-row">
                <span class="total-label">Total commande</span>
                <span class="total-amount">
                    {{ number_format($payload['total'] ?? 0, 0, ',', ' ') }} {{ $payload['currency'] ?? 'XOF' }}
                </span>
            </div>
        </div>
        @endif

        {{-- CTA --}}
        @if (!empty($payload['url']))
        <div class="cta-section">
            <a href="{{ $payload['url'] }}" class="cta-btn">
                Voir la commande dans l'admin →
            </a>
        </div>
        @endif

    </div>{{-- /body --}}

    {{-- ── Footer ── --}}
    <div class="footer">
        <p>{{ \App\Support\Shop::name() }} · Notification automatique · Ne pas répondre à cet e-mail</p>
        <p style="margin-top:6px;">© {{ date('Y') }} {{ \App\Support\Shop::name() }}. Tous droits réservés.</p>
    </div>

</div>

</body>
</html>
