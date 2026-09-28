<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8"/>
    <title>Facture {{ $order->number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #211f1f;
            background: #fff;
            line-height: 1.55;
        }

        .page { padding: 40px 48px; min-height: 100%; }

        /* ── Header ── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 36px;
            border-bottom: 2px solid #e8336d;
            padding-bottom: 24px;
        }
        .header-left  { display: table-cell; vertical-align: top; width: 55%; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; }

        .brand-name {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 28px;
            font-weight: 700;
            color: #e8336d;
            letter-spacing: 0.02em;
            line-height: 1;
        }
        .brand-tagline {
            font-size: 9px;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: #9e9896;
            margin-top: 4px;
        }
        .invoice-label {
            font-size: 9px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #9e9896;
            margin-bottom: 2px;
        }
        .invoice-number {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 20px;
            font-weight: 700;
            color: #211f1f;
            letter-spacing: 0.04em;
        }
        .invoice-date { font-size: 10px; color: #726d6b; margin-top: 4px; }

        /* ── Status badge ── */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 6px;
        }
        .status-pending    { background: #fef3c7; color: #92400e; }
        .status-confirmed  { background: #dbeafe; color: #1e40af; }
        .status-processing { background: #ede9fe; color: #5b21b6; }
        .status-shipped    { background: #dbeafe; color: #1e40af; }
        .status-delivered  { background: #dcfce7; color: #15803d; }
        .status-cancelled  { background: #fee2e2; color: #b91c1c; }
        .status-refunded   { background: #f3f4f6; color: #374151; }

        /* ── Addresses ── */
        .addresses        { display: table; width: 100%; margin-bottom: 28px; }
        .address-block    { display: table-cell; width: 50%; vertical-align: top; }
        .address-block + .address-block { padding-left: 24px; }

        .section-label {
            font-size: 8px;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: #e8336d;
            font-weight: 700;
            margin-bottom: 6px;
            border-bottom: 1px solid #ffd6e7;
            padding-bottom: 4px;
        }
        .address-name { font-weight: 700; font-size: 11px; color: #211f1f; }
        .address-line { font-size: 10px; color: #726d6b; line-height: 1.6; }

        /* ── Items table ── */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table thead tr { background: #e8336d; color: #fff; }
        .items-table thead th {
            padding: 8px 10px;
            font-size: 9px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 600;
        }
        .items-table thead th:first-child { border-radius: 4px 0 0 4px; text-align: left; }
        .items-table thead th:last-child  { border-radius: 0 4px 4px 0; text-align: right; }
        .items-table thead th.center { text-align: center; }
        .items-table thead th.right  { text-align: right; }
        .items-table tbody tr         { border-bottom: 1px solid #f0e8dc; }
        .items-table tbody tr:last-child { border-bottom: none; }
        .items-table tbody tr:nth-child(even) { background: #fdfaf7; }
        .items-table td { padding: 9px 10px; font-size: 10.5px; vertical-align: middle; }
        .item-name    { font-weight: 600; color: #211f1f; }
        .item-sku     { font-size: 9px; color: #9e9896; margin-top: 2px; }
        .item-variant { font-size: 9px; color: #9e9896; margin-top: 1px; font-style: italic; }
        .td-center { text-align: center; color: #726d6b; }
        .td-right  { text-align: right;  color: #726d6b; }
        .td-total  { text-align: right;  font-weight: 700; color: #211f1f; }

        /* ── Totals ── */
        .totals-wrap    { display: table; width: 100%; margin-top: 4px; }
        .totals-spacer  { display: table-cell; width: 55%; }
        .totals-box {
            display: table-cell;
            width: 45%;
            vertical-align: top;
            background: #fdfaf7;
            border: 1px solid #f0e8dc;
            border-radius: 8px;
            padding: 14px 16px;
        }
        .totals-row   { display: table; width: 100%; margin-bottom: 5px; }
        .totals-label { display: table-cell; font-size: 10px; color: #726d6b; }
        .totals-value { display: table-cell; text-align: right; font-size: 10px; color: #726d6b; }
        .totals-row-discount .totals-label,
        .totals-row-discount .totals-value { color: #15803d; }
        .totals-divider { border: none; border-top: 1px solid #e2d5c5; margin: 8px 0; }
        .totals-row-final .totals-label {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 13px; font-weight: 700; color: #211f1f;
        }
        .totals-row-final .totals-value {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 13px; font-weight: 700; color: #e8336d;
        }

        /* ── Payment ── */
        .payment-pill {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            background: #ffd6e7;
            color: #a3104a;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
        .payment-paid { background: #dcfce7; color: #15803d; }

        /* ── Note ── */
        .note-box {
            margin-top: 28px;
            background: #fff0f5;
            border-left: 3px solid #e8336d;
            border-radius: 0 6px 6px 0;
            padding: 10px 14px;
            font-size: 10px;
            color: #726d6b;
        }
        .note-box strong { color: #211f1f; }

        /* ── Footer ── */
        .footer {
            margin-top: 48px;
            border-top: 1px solid #f0e8dc;
            padding-top: 14px;
            text-align: center;
            font-size: 9px;
            color: #9e9896;
            line-height: 1.7;
        }
        .footer strong { color: #e8336d; }

        .rose-strip {
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #e8336d 0%, #f9518a 60%, #ffadd0 100%);
            margin-bottom: 32px;
        }

        /* ── Logo bloc ── */
        .logo-block  { line-height: 1; }
        .logo-frame  {
            display: block;
            width: 242px;
            border: 2px solid #e8336d;
            border-radius: 8px;
            padding: 8px 10px;
            background: #fff8fa;
            text-align: center;
        }
        .logo-img    { display: block; height: 70px; max-width: 218px; margin: 0 auto; }
        .brand-row   { margin-top: 9px; width: 242px; text-align: center; }
        .brand-name-text {
            font-family: DejaVu Serif, Georgia, serif;
            font-size: 14px;
            font-weight: 700;
            color: #e8336d;
            letter-spacing: 0.05em;
            line-height: 1;
            display: block;
        }
        .brand-tagline {
            font-size: 7.5px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #9e9896;
            margin-top: 4px;
            display: block;
        }
    </style>
</head>
<body>
<div class="page">

    <div class="rose-strip"></div>

    {{-- ── Header ── --}}
    <div class="header">
        <div class="header-left">
            <div class="logo-block">
                @if ($logoBase64)
                    <div class="logo-frame">
                        <img
                            src="data:image/png;base64,{{ $logoBase64 }}"
                            class="logo-img"
                            alt="{{ $shop['name'] }}"
                        />
                    </div>
                @endif
                <div class="brand-row">
                    <div class="brand-name-text">{{ $shop['name'] }}</div>
                    <div class="brand-tagline">{{ $shop['tagline'] }}</div>
                </div>
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-label">Facture</div>
            <div class="invoice-number">{{ $order->number }}</div>
            <div class="invoice-date">
                Émise le {{ $order->created_at->locale('fr')->isoFormat('D MMMM YYYY') }}
                @if ($order->paid_at)
                    &nbsp;·&nbsp; Payée le {{ $order->paid_at->locale('fr')->isoFormat('D MMMM YYYY') }}
                @endif
            </div>
            @php
                $statusMap = [
                    'pending'    => ['En attente', 'status-pending'],
                    'confirmed'  => ['Confirmée',  'status-confirmed'],
                    'processing' => ['En cours',   'status-processing'],
                    'shipped'    => ['Expédiée',   'status-shipped'],
                    'delivered'  => ['Livrée',     'status-delivered'],
                    'cancelled'  => ['Annulée',    'status-cancelled'],
                    'refunded'   => ['Remboursée', 'status-refunded'],
                ];
                $statusEntry = $statusMap[$order->status] ?? [$order->status, 'status-pending'];
                $statusText  = $statusEntry[0];
                $statusClass = $statusEntry[1];
            @endphp
            <div>
                <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
            </div>
        </div>
    </div>

    {{-- ── Addresses ── --}}
    <div class="addresses">
        <div class="address-block">
            <div class="section-label">Vendeur</div>
            <div class="address-name">{{ $shop['name'] }}</div>
            <div class="address-line">
                {{ $shop['tagline'] }}<br/>
                @if ($shop['address']){{ $shop['address'] }}<br/>@endif
                @if ($shop['phone'])Tél : {{ $shop['phone'] }}<br/>@endif
                {{ $shop['email'] }}<br/>
                {{ $shop['url'] }}
            </div>
        </div>

        @php $addr = $order->shipping_address; @endphp
        <div class="address-block">
            <div class="section-label">Livraison à</div>
            @if ($addr)
                <div class="address-name">
                    {{ trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? '')) }}
                </div>
                <div class="address-line">
                    {{ $addr['address_line1'] ?? '' }}<br/>
                    @if (!empty($addr['address_line2']))
                        {{ $addr['address_line2'] }}<br/>
                    @endif
                    @if (!empty($addr['city']))
                        {{ $addr['city'] }}@if (!empty($addr['zip'])), {{ $addr['zip'] }}@endif<br/>
                    @endif
                    @if (!empty($addr['country']))
                        {{ $addr['country'] }}<br/>
                    @endif
                    @if (!empty($addr['phone']))
                        Tél  : {{ $addr['phone'] }}<br/>
                    @endif
                    @if (!empty($addr['receiver_phone']))
                        Tél receveur : <strong>{{ $addr['receiver_phone'] }}</strong><br/>
                    @endif
                    @if (!empty($addr['landmark']))
                        <span style="color:#b45309;">⚑ {{ $addr['landmark'] }}</span>
                    @endif
                </div>
            @else
                <div class="address-line">—</div>
            @endif
        </div>
    </div>

    {{-- ── Items ── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th>Produit</th>
                <th class="center">Qté</th>
                <th class="right">Prix unit.</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
                <tr>
                    <td>
                        <div class="item-name">{{ $item->product_name }}</div>
                        @if ($item->variant_label)
                            <div class="item-variant">{{ $item->variant_label }}</div>
                        @endif
                        @if ($item->sku)
                            <div class="item-sku">SKU : {{ $item->sku }}</div>
                        @endif
                    </td>
                    <td class="td-center">{{ $item->quantity }}</td>
                    <td class="td-right">{{ number_format($item->unit_price, 0, ',', ' ') }} {{ $order->currency }}</td>
                    <td class="td-total">{{ number_format($item->total_price, 0, ',', ' ') }} {{ $order->currency }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Totals ── --}}
    <div class="totals-wrap">
        <div class="totals-spacer"></div>
        <div class="totals-box">
            <div class="totals-row">
                <span class="totals-label">Sous-total</span>
                <span class="totals-value">{{ number_format($order->subtotal, 0, ',', ' ') }} {{ $order->currency }}</span>
            </div>
            @if ($order->discount_amount > 0)
                <div class="totals-row totals-row-discount">
                    <span class="totals-label">
                        Réduction
                        @if ($order->coupon_code)
                            ({{ $order->coupon_code }})
                        @endif
                    </span>
                    <span class="totals-value">
                        -{{ number_format($order->discount_amount, 0, ',', ' ') }} {{ $order->currency }}
                    </span>
                </div>
            @endif
            <div class="totals-row">
                <span class="totals-label">Frais de livraison</span>
                <span class="totals-value">
                    {{-- Pas de livraison gratuite : 0 = frais à renseigner par nos agents --}}
                    @if ($order->shipping_cost > 0)
                        {{ number_format($order->shipping_cost, 0, ',', ' ') }} {{ $order->currency }}
                    @else
                        <span style="color:#b45309;font-size:9px;">À renseigner par nos agents</span>
                    @endif
                </span>
            </div>
            <hr class="totals-divider"/>
            <div class="totals-row totals-row-final">
                <span class="totals-label">Total</span>
                <span class="totals-value">{{ number_format($order->total, 0, ',', ' ') }} {{ $order->currency }}</span>
            </div>
        </div>
    </div>

    {{-- ── Payment ── --}}
    @php
        $providers = [
            'wave'         => 'Wave',
            'orange_money' => 'Orange Money',
            'stripe'       => 'Stripe / Carte bancaire',
            'cod'          => 'Paiement à la livraison',
            'virement'     => 'Virement bancaire',
            'delivery'     => 'Paiement à la livraison',
        ];
        $payment = $order->payments->first();
    @endphp
    <div style="margin-top: 20px;">
        <div class="section-label">Paiement</div>
        <div style="margin-top: 6px;">
            <span class="payment-pill">
                @if ($payment)
                    {{ $providers[$payment->provider] ?? $payment->provider }}
                @else
                    Non renseigné
                @endif
            </span>
            @if ($order->paid_at)
                <span class="payment-pill payment-paid" style="margin-left: 6px;">Payée</span>
            @endif
        </div>
    </div>

    {{-- ── Customer note ── --}}
    @if ($order->customer_note)
        <div class="note-box">
            <strong>Note client :</strong> {{ $order->customer_note }}
        </div>
    @endif

    {{-- ── Footer ── --}}
    <div class="footer">
        <strong>{{ $shop['name'] }}</strong> — {{ $shop['tagline'] }}<br/>
        Merci pour votre confiance. Cette facture a été générée automatiquement.<br/>
        Pour toute question, contactez-nous à <strong>{{ $shop['email'] }}</strong>
        @if ($shop['phone'])
            &nbsp;·&nbsp; {{ $shop['phone'] }}
        @endif
    </div>

</div>
</body>
</html>
