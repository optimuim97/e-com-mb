<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Votre commande attend votre règlement</title>
</head>
<body style="margin:0;padding:0;background:#fdf4f5;font-family:'Helvetica Neue',Arial,sans-serif;color:#3d2731;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fdf4f5;padding:32px 16px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(168,50,80,.08);">

                <tr>
                    <td style="background:linear-gradient(135deg,{{ \App\Support\Shop::primaryColor() }},#f06292);padding:32px 32px 24px;text-align:center;color:#ffffff;">
                        <h1 style="margin:0 0 8px;font-family:Georgia,serif;font-size:24px;font-weight:500;letter-spacing:.5px;">
                            {{ \App\Support\Shop::name() }}
                        </h1>
                        <p style="margin:0;font-size:14px;opacity:.92;">
                            {{ $day === 1 ? 'Votre commande vous attend' : 'Dernier rappel avant annulation' }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px 32px 8px;">
                        <p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#9ca3af;">Votre commande</p>
                        <h2 style="margin:0 0 16px;font-family:Georgia,serif;font-size:22px;font-weight:600;color:#3d2731;">
                            {{ $order->number }}
                        </h2>

                        @php
                            $prenom = trim((string) ($order->shipping_address['first_name'] ?? ''));
                        @endphp

                        <p style="margin:0 0 16px;font-size:15px;line-height:1.6;">
                            @if ($day === 1)
                                Bonjour{{ $prenom !== '' ? ' ' . $prenom : '' }},
                                votre commande est bien enregistrée. Elle part dès réception de votre règlement —
                                hors d'Abidjan, nous n'expédions qu'une commande réglée, pour que votre colis
                                ne voyage jamais pour rien.
                            @else
                                Votre commande attend toujours son règlement. Passé quelques jours,
                                notre équipe se permettra de l'annuler pour libérer les articles —
                                dites-nous simplement si vous souhaitez la maintenir.
                            @endif
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px;background:#fdf4f5;border-radius:10px;">
                            <tr>
                                <td style="padding:16px 20px;">
                                    <p style="margin:0 0 6px;font-size:13px;color:#6b5560;">Montant à régler</p>
                                    <p style="margin:0;font-size:22px;font-weight:700;color:{{ \App\Support\Shop::primaryColor() }};">
                                        {{ number_format((float) $order->total, 0, ',', ' ') }} {{ $order->currency }}
                                    </p>
                                    @if ($estimate)
                                        <p style="margin:10px 0 0;font-size:13px;color:#6b5560;">{{ $estimate }}</p>
                                    @endif
                                </td>
                            </tr>
                        </table>

                        <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#6b5560;">
                            Pour régler ou poser une question, répondez simplement à ce message,
                            ou écrivez-nous sur WhatsApp. Nous nous occupons du reste.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 32px 32px;text-align:center;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;">
                            {{ \App\Support\Shop::name() }}{{ \App\Support\Shop::location() ? ' — ' . \App\Support\Shop::location() : '' }}
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
