<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Feuille de livraison</title>
</head>
<body style="margin:0;padding:24px;background:#fdfaf7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#2b2126;">

    <div style="max-width:600px;margin:0 auto;background:#ffffff;border:1px solid #e6dcd6;border-radius:8px;padding:28px;">

        <p style="margin:0 0 4px;font-size:12px;letter-spacing:0.08em;text-transform:uppercase;color:{{ \App\Support\Shop::primaryColorDark() }};font-weight:600;">
            {{ \App\Support\Shop::name() }}
        </p>

        <h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#2b2126;">
            @if($pending ?? false)
                À traiter — {{ $zoneLabel }}
            @else
                Feuille de livraison — {{ $zoneLabel }}
            @endif
        </h1>

        @if($pending ?? false)
            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#5a4c52;">
                {{ $ordersCount }} commande{{ $ordersCount > 1 ? 's' : '' }}
                {{ $ordersCount > 1 ? 'attendent' : 'attend' }} encore d'être
                {{ $zoneLabel === \Modules\Order\Support\HomeZone::outsideLabel() ? 'réglée' . ($ordersCount > 1 ? 's' : '') : 'traitée' . ($ordersCount > 1 ? 's' : '') }}.
                {{ $ordersCount > 1 ? 'Elles ne figurent' : 'Elle ne figure' }} sur
                aucune feuille tant que rien ne bouge — d'où ce rappel.
            </p>
        @else
            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#5a4c52;">
                {{ $ordersCount }} commande{{ $ordersCount > 1 ? 's' : '' }} à livrer.
                La feuille est jointe en texte brut : elle s'imprime telle quelle
                et se colle dans WhatsApp sans se déformer.
            </p>
        @endif

        {{-- Le code de la tournée préparée : c'est lui qu'on tape le lendemain
             matin pour confirmer le départ et désigner le livreur. --}}
        @if(! empty($round))
            <div style="margin:0 0 20px;padding:16px;background:#fdf1f5;border:1px solid #f3cfdd;border-radius:6px;">
                <p style="margin:0 0 6px;font-size:13px;color:#8a7a80;">
                    Tournée préparée
                </p>
                <p style="margin:0 0 8px;font-size:22px;font-weight:700;letter-spacing:0.06em;color:{{ \App\Support\Shop::primaryColorDark() }};">
                    {{ $round['code'] }}
                </p>
                <p style="margin:0;font-size:13px;line-height:1.6;color:#5a4c52;">
                    {{ $round['count'] }} commande{{ $round['count'] > 1 ? 's' : '' }} réservée{{ $round['count'] > 1 ? 's' : '' }}{{ isset($round['items']) ? ', ' . $round['items'] . ' article' . ($round['items'] > 1 ? 's' : '') . ' à emporter' : '' }}.
                    @if(($round['skipped'] ?? 0) > 0)
                        {{ $round['skipped'] }} écartée{{ $round['skipped'] > 1 ? 's' : '' }} —
                        frais à fixer ou règlement manquant.
                    @endif
                    Rien n'est parti : ouvrez la tournée dans le back-office pour
                    désigner le livreur et confirmer le départ.
                </p>
            </div>
        @endif

        @if(! empty($window))
            <p style="margin:0 0 20px;font-size:13px;line-height:1.6;color:#8a7a80;">
                Période couverte : du {{ $window[0]->format('d/m/Y à H:i') }}
                au {{ $window[1]->format('d/m/Y à H:i') }}.
            </p>
        @endif

        {{-- Aperçu : de quoi vérifier depuis un téléphone sans ouvrir la pièce jointe. --}}
        <pre style="margin:0 0 20px;padding:16px;background:#f6f1ed;border-radius:6px;font-family:'Courier New',Courier,monospace;font-size:12px;line-height:1.6;color:#2b2126;white-space:pre-wrap;word-break:break-word;overflow-x:auto;">{{ $sheet }}</pre>

        <p style="margin:0;font-size:13px;line-height:1.6;color:#8a7a80;border-top:1px solid #e6dcd6;padding-top:16px;">
            Envoi automatique de 21 h, du lundi au vendredi — le lundi reprend
            le week-end. Les commandes annulées, remboursées et déjà expédiées
            n'y figurent pas.
        </p>

    </div>

</body>
</html>
