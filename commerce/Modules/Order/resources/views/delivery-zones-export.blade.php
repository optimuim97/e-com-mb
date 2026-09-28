<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <title>Livraisons par zone — {{ now()->format('d/m/Y') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            background: #fff;
            line-height: 1.5;
        }

        .page { padding: 32px 40px; }

        /* ══ Header document ══ */
        .doc-header {
            display: table;
            width: 100%;
            border-bottom: 3px solid #e8336d;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }
        .doc-header-left  { display: table-cell; vertical-align: middle; width: 50%; }
        .doc-header-right { display: table-cell; vertical-align: middle; text-align: right; }

        .logo-frame {
            display: block;
            width: 220px;
            border: 2px solid #e8336d;
            border-radius: 7px;
            padding: 7px 9px;
            background: #fff8fa;
            text-align: center;
        }
        .logo-img { display: block; height: 58px; max-width: 200px; margin: 0 auto; }

        .brand-name {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 13px;
            font-weight: 700;
            color: #e8336d;
            letter-spacing: 0.05em;
            line-height: 1;
            margin-top: 8px;
            width: 220px;
            text-align: center;
            display: block;
        }
        .brand-tagline {
            font-size: 7px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #a0948f;
            margin-top: 3px;
            width: 220px;
            text-align: center;
            display: block;
        }

        .doc-title {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 16px;
            font-weight: 700;
            color: #1a1a1a;
            letter-spacing: 0.02em;
        }
        .doc-meta {
            font-size: 9px;
            color: #7a6e6a;
            margin-top: 4px;
            line-height: 1.6;
        }
        .doc-meta strong { color: #1a1a1a; }

        /* ══ Summary ribbon ══ */
        .summary-ribbon {
            background: #fff5f8;
            border: 1px solid #fbd0dd;
            border-radius: 6px;
            padding: 10px 16px;
            margin-bottom: 22px;
        }
        .summary-ribbon table { width: 100%; }
        .summary-cell { text-align: center; }
        .summary-cell .val {
            font-size: 18px;
            font-weight: 700;
            color: #e8336d;
            font-family: DejaVu Serif, Georgia, serif;
        }
        .summary-cell .lbl {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #9e9896;
            margin-top: 2px;
        }
        .summary-divider {
            width: 1px;
            background: #fbd0dd;
        }

        /* ══ Zone header ══ */
        .zone-header {
            background: #e8336d;
            color: #fff;
            padding: 8px 14px;
            margin-top: 22px;
            margin-bottom: 0;
            border-radius: 5px 5px 0 0;
        }
        .zone-header table { width: 100%; }
        .zone-name {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }
        .zone-group {
            font-size: 9px;
            opacity: 0.85;
            margin-top: 2px;
        }
        .zone-stats {
            font-size: 9px;
            text-align: right;
            line-height: 1.7;
        }
        .zone-off {
            background: #6b7280;
        }

        /* ══ Orders table ══ */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #f0e8e4;
            border-top: none;
            margin-bottom: 4px;
        }
        .orders-table th {
            background: #faf6f4;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #9e9896;
            padding: 6px 10px;
            border-bottom: 1px solid #f0e8e4;
            text-align: left;
        }
        .orders-table td {
            padding: 9px 10px;
            vertical-align: top;
            border-bottom: 1px solid #f8f2f0;
            font-size: 9.5px;
        }
        .orders-table tr:last-child td {
            border-bottom: none;
        }
        .orders-table tr:nth-child(even) td {
            background: #fffbfa;
        }

        /* Col widths */
        .col-order   { width: 13%; }
        .col-contact { width: 27%; }
        .col-items   { width: 34%; }
        .col-prices  { width: 26%; text-align: right; }

        .order-number {
            font-family: DejaVu Sans, monospace;
            font-size: 9px;
            font-weight: 700;
            color: #e8336d;
            letter-spacing: 0.04em;
        }
        .order-date {
            font-size: 8px;
            color: #9e9896;
            margin-top: 3px;
        }

        .phone {
            font-size: 10px;
            font-weight: 700;
            color: #1a1a1a;
        }
        .recipient-name {
            font-size: 9px;
            color: #3d3230;
            margin-top: 3px;
        }
        .address {
            font-size: 8.5px;
            color: #7a6e6a;
            margin-top: 3px;
            line-height: 1.4;
        }
        .customer-note {
            font-size: 8px;
            color: #b45309;
            margin-top: 4px;
            font-style: italic;
        }

        .item-row {
            display: block;
            margin-bottom: 3px;
            font-size: 9px;
        }
        .item-name { color: #1a1a1a; }
        .item-variant { font-size: 8px; color: #9e9896; }
        .item-qty {
            font-weight: 700;
            color: #e8336d;
        }

        .price-subtotal { font-size: 9px; color: #7a6e6a; }
        .price-shipping { font-size: 9px; color: #7a6e6a; margin-top: 2px; }
        .price-total {
            font-size: 11px;
            font-weight: 700;
            color: #1a1a1a;
            margin-top: 5px;
            padding-top: 4px;
            border-top: 1px solid #f0e8e4;
        }
        /* Pas de livraison gratuite : 0 = frais non renseignés */
        .price-todo { color: #b45309; font-size: 9px; }

        /* Case à cocher par le livreur (remplace l'ancien badge de statut) */
        .delivered-box {
            margin-top: 5px;
            font-size: 9px;
            font-weight: 700;
            color: #3d3230;
            letter-spacing: 0.04em;
        }

        .price-paid {
            font-size: 7.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #15803d;
            margin-top: 2px;
        }

        .item-count {
            margin-top: 4px;
            font-size: 8px;
            font-weight: 700;
            color: #9e9896;
            letter-spacing: 0.06em;
        }

        .recap-header { background: #3d3230; }

        /* ══ Zone total row ══ */
        .zone-total-row td {
            background: #fff5f8 !important;
            border-top: 2px solid #fbd0dd !important;
            font-size: 9px;
            font-weight: 700;
            color: #e8336d;
            padding: 7px 10px;
            text-align: right;
        }

        /* ══ Footer ══ */
        .doc-footer {
            margin-top: 28px;
            padding-top: 12px;
            border-top: 1px solid #f0e8e4;
            text-align: center;
            font-size: 8px;
            color: #c0b4b0;
            letter-spacing: 0.06em;
        }

        /* ══ Page break ══ */
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
<div class="page">

    {{-- ── Header ── --}}
    <div class="doc-header">
        <div class="doc-header-left">
            @if($logoBase64)
                <div class="logo-frame">
                    <img src="data:image/png;base64,{{ $logoBase64 }}" class="logo-img" alt="{{ $shop['name'] }}" />
                </div>
            @endif
            <div class="brand-name">{{ $shop['name'] }}</div>
            <div class="brand-tagline">{{ $shop['tagline'] }}</div>
        </div>
        <div class="doc-header-right">
            <div class="doc-title">
                @if(!empty($exportLabel))
                    Livraison — {{ $exportLabel }}
                @else
                    Livraisons par zone
                @endif
            </div>
            <div class="doc-meta">
                Généré le <strong>{{ now()->format('d/m/Y à H:i') }}</strong><br>
                @if(!empty($filters['order_ids']))
                    <strong>{{ count($filters['order_ids']) }} commande(s) sélectionnée(s)</strong><br>
                @elseif($filters['date_from'] || $filters['date_to'])
                    Période :
                    <strong>
                        {{ $filters['date_from'] ? \Carbon\Carbon::parse($filters['date_from'])->format('d/m/Y') : '…' }}
                        →
                        {{ $filters['date_to'] ? \Carbon\Carbon::parse($filters['date_to'])->format('d/m/Y') : 'aujourd\'hui' }}
                    </strong><br>
                @endif
                @if(!empty($filters['status']))
                    Statut : <strong>{{ $statusLabels[$filters['status']] ?? $filters['status'] }}</strong>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Summary ribbon ── --}}
    <div class="summary-ribbon">
        <table>
            <tr>
                <td class="summary-cell">
                    <div class="val">{{ $totalZones }}</div>
                    <div class="lbl">Zones</div>
                </td>
                <td class="summary-divider">&nbsp;</td>
                <td class="summary-cell">
                    <div class="val">{{ $totalOrders }}</div>
                    <div class="lbl">Commandes</div>
                </td>
                @if($showItemCounts)
                    <td class="summary-divider">&nbsp;</td>
                    <td class="summary-cell">
                        <div class="val">{{ $totalItems }}</div>
                        <div class="lbl">Articles</div>
                    </td>
                @endif
                <td class="summary-divider">&nbsp;</td>
                <td class="summary-cell">
                    <div class="val">{{ number_format($totalShipping, 0, ',', ' ') }}</div>
                    <div class="lbl">Livraisons XOF</div>
                </td>
                <td class="summary-divider">&nbsp;</td>
                <td class="summary-cell">
                    <div class="val">{{ number_format($totalRevenue, 0, ',', ' ') }} XOF</div>
                    <div class="lbl">Total à encaisser</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Zones ── --}}
    @foreach($groups as $i => $group)
        @php $isOffZone = ($group['zone'] === 'Hors zone'); @endphp

        <div class="zone-header {{ $isOffZone ? 'zone-off' : '' }}">
            <table>
                <tr>
                    <td>
                        <div class="zone-name">{{ $group['zone'] }}</div>
                        @if($group['group'] && $group['group'] !== '—')
                            <div class="zone-group">{{ $group['group'] }}</div>
                        @endif
                    </td>
                    <td class="zone-stats">
                        {{ $group['count'] }} commande{{ $group['count'] > 1 ? 's' : '' }}@if($showItemCounts) · {{ $group['items_count'] }} article{{ $group['items_count'] > 1 ? 's' : '' }}@endif<br>
                        Livraisons : {{ number_format($group['shipping'], 0, ',', ' ') }} XOF
                        &nbsp;·&nbsp;
                        Total : {{ number_format($group['total'], 0, ',', ' ') }} XOF
                    </td>
                </tr>
            </table>
        </div>

        <table class="orders-table">
            <thead>
                <tr>
                    <th class="col-order">Commande</th>
                    <th class="col-contact">Destinataire</th>
                    <th class="col-items">Articles</th>
                    <th class="col-prices">Prix</th>
                </tr>
            </thead>
            <tbody>
                @foreach($group['orders'] as $order)
                    <tr>
                        {{-- Numéro + case « livré » (feuille annotable par le livreur) --}}
                        <td class="col-order">
                            <div class="order-number">{{ $order['number'] }}</div>
                            <div class="order-date">{{ $order['date'] }}</div>
                            <div class="delivered-box">&#9744; Livré</div>
                        </td>

                        {{-- Destinataire --}}
                        <td class="col-contact">
                            <div class="phone">{{ $order['phone'] }}</div>
                            @if($order['name'])
                                <div class="recipient-name">{{ $order['name'] }}</div>
                            @endif
                            @if($order['address'] || $order['locality'])
                                <div class="address">
                                    @if($order['address']){{ $order['address'] }}<br>@endif
                                    {{ $order['locality'] }}
                                </div>
                            @endif
                            @if($order['landmark'])
                                <div class="customer-note" style="color:#7c3aed;">&#128205; {{ $order['landmark'] }}</div>
                            @endif
                            @if($order['note'])
                                <div class="customer-note">&#9888; {{ $order['note'] }}</div>
                            @endif
                        </td>

                        {{-- Articles --}}
                        <td class="col-items">
                            @foreach($order['items'] as $item)
                                <div class="item-row">
                                    <span class="item-name">{{ $item['name'] }}</span>
                                    @if($item['variant'])
                                        <span class="item-variant"> ({{ $item['variant'] }})</span>
                                    @endif
                                    &nbsp;<span class="item-qty">×{{ $item['qty'] }}</span>
                                </div>
                            @endforeach
                            @if($showItemCounts)
                                <div class="item-count">{{ $order['items_count'] }} article{{ $order['items_count'] > 1 ? 's' : '' }}</div>
                            @endif
                        </td>

                        {{-- Prix --}}
                        <td class="col-prices">
                            <div class="price-subtotal">
                                Art. : {{ number_format($order['subtotal'], 0, ',', ' ') }} XOF
                            </div>
                            <div class="price-shipping">
                                @if($order['shipping'] > 0)
                                    Livr. : {{ number_format($order['shipping'], 0, ',', ' ') }} XOF
                                @else
                                    <span class="price-todo">Livr. : à renseigner</span>
                                @endif
                            </div>
                            @if($order['discount'] > 0)
                                <div class="price-shipping" style="color:#e8336d;">
                                    Remise : -{{ number_format($order['discount'], 0, ',', ' ') }} XOF
                                </div>
                            @endif
                            <div class="price-total">
                                {{ number_format($order['total'], 0, ',', ' ') }} XOF
                                @if($order['is_paid'])
                                    <div class="price-paid">déjà payée</div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach

                {{-- Zone total --}}
                <tr class="zone-total-row">
                    <td colspan="3" style="text-align:left;">
                        Total zone — {{ $group['count'] }} commande{{ $group['count'] > 1 ? 's' : '' }}@if($showItemCounts), {{ $group['items_count'] }} article{{ $group['items_count'] > 1 ? 's' : '' }}@endif
                        &nbsp;·&nbsp; Livraisons : {{ number_format($group['shipping'], 0, ',', ' ') }} XOF
                    </td>
                    <td>{{ number_format($group['total'], 0, ',', ' ') }} XOF</td>
                </tr>
            </tbody>
        </table>

        {{-- Page break toutes les 2 zones --}}
        @if(($i + 1) % 2 === 0 && ($i + 1) < count($groups))
            <div class="page-break"></div>
        @endif

    @endforeach

    {{-- ── Récap : total des prix par produit ── --}}
    @if($showProductTotals && count($productTotals))
        <div class="zone-header recap-header">
            <table>
                <tr>
                    <td><div class="zone-name">Total par produit</div></td>
                    <td class="zone-stats">
                        {{ count($productTotals) }} référence{{ count($productTotals) > 1 ? 's' : '' }}@if($showItemCounts) · {{ $totalItems }} article{{ $totalItems > 1 ? 's' : '' }}@endif
                    </td>
                </tr>
            </table>
        </div>
        <table class="orders-table">
            <thead>
                <tr>
                    <th style="width:60%;">Produit</th>
                    <th style="width:15%; text-align:right;">Qté</th>
                    <th style="width:25%; text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($productTotals as $p)
                    <tr>
                        <td>{{ $p['name'] }}</td>
                        <td style="text-align:right;" class="item-qty">×{{ $p['qty'] }}</td>
                        <td style="text-align:right; font-weight:700;">
                            {{ number_format($p['total'], 0, ',', ' ') }} XOF
                        </td>
                    </tr>
                @endforeach
                <tr class="zone-total-row">
                    <td style="text-align:left;">Total articles (hors livraison)</td>
                    <td>×{{ $totalItems }}</td>
                    <td>{{ number_format(array_sum(array_column($productTotals, 'total')), 0, ',', ' ') }} XOF</td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- ── Footer ── --}}
    <div class="doc-footer">
        {{ $shop['name'] }} — {{ $shop['url'] }} — Document généré automatiquement le {{ now()->format('d/m/Y à H:i') }}
    </div>

</div>
</body>
</html>
