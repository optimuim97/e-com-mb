@php
    /**
     * Alerte de réassort.
     *
     * Volontairement courte : elle se lit sur un téléphone, entre deux
     * clientes. Le nom de l'article et ce qu'il en reste doivent tenir dans
     * le premier écran, avant tout le reste.
     */
    $rupture = ($event ?? '') === 'stock.out';
    $article = $payload['product_name'] ?? 'Article';
    $decl    = $payload['variant_label'] ?? null;
    $sku     = $payload['sku'] ?? null;
    $reste   = (int) ($payload['stock'] ?? 0);
    $seuil   = (int) ($payload['threshold'] ?? 0);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alerte de stock — {{ \App\Support\Shop::name() }}</title>
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
            letter-spacing: 3px;
            opacity: .85;
            margin-top: 4px;
        }
        .banner {
            padding: 20px 40px;
            text-align: center;
            font-size: 17px;
            font-weight: 700;
        }
        .banner--out { background: #fee2e2; color: #b91c1c; }
        .banner--low { background: #fef3c7; color: #92400e; }
        .body { padding: 32px 40px; }
        .article {
            font-size: 19px;
            font-weight: 700;
            color: #2d2d2d;
            margin-bottom: 4px;
        }
        .article__meta {
            font-size: 13px;
            color: #8a7f78;
            margin-bottom: 24px;
        }
        .figures {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .figures td {
            padding: 12px 0;
            border-bottom: 1px solid #f0e8e3;
        }
        .figures td:last-child {
            text-align: right;
            font-weight: 700;
        }
        .figures tr:last-child td { border-bottom: none; }
        .note {
            background: #f9f4f1;
            border-radius: 12px;
            padding: 16px 20px;
            font-size: 14px;
            color: #6b5f58;
        }
        .footer {
            padding: 24px 40px;
            text-align: center;
            font-size: 12px;
            color: #a89a92;
            border-top: 1px solid #f0e8e3;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <div class="header__logo">
                {{ \App\Support\Shop::name() }}
            </div>
        </div>

        <div class="banner {{ $rupture ? 'banner--out' : 'banner--low' }}">
            {{ $rupture ? '⛔ Rupture de stock' : '⚠️ Stock bas' }}
        </div>

        <div class="body">
            <p class="article">{{ $article }}</p>
            <p class="article__meta">
                @if ($decl){{ $decl }} · @endif
                @if ($sku)Réf. {{ $sku }}@endif
            </p>

            <table class="figures">
                <tr>
                    <td>Quantité restante</td>
                    <td>{{ $reste }}</td>
                </tr>
                @if ($seuil > 0)
                <tr>
                    <td>Seuil d'alerte</td>
                    <td>{{ $seuil }}</td>
                </tr>
                @endif
            </table>

            <div class="note">
                @if ($rupture)
                    Cet article ne peut plus être commandé en boutique. Prévoyez
                    le réassort ou retirez-le de la vente le temps de le
                    réapprovisionner.
                @else
                    Il reste peu d'exemplaires. C'est le moment de lancer le
                    réassort, avant que la rupture ne se produise.
                @endif
            </div>
        </div>

        <div class="footer">
            Message automatique — {{ \App\Support\Shop::name() }}
        </div>
    </div>
</body>
</html>
