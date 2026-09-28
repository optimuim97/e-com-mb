<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $subject }}</title>
  @if($previewText)
    <span style="display:none!important;opacity:0;color:transparent;height:0;width:0;overflow:hidden">{{ $previewText }}</span>
  @endif
</head>
<body style="margin:0;padding:24px 12px;background:#fdf5f1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#3a2520;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr><td align="center">
      <table role="presentation" width="100%" style="max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 6px 28px rgba(232,51,109,0.08);" cellpadding="0" cellspacing="0" border="0">

        <!-- En-tête marque -->
        <tr>
          <td style="padding:28px 32px 8px;text-align:center;background:linear-gradient(135deg,#fdeef2,#fdf5f1);">
            @if($shopLogo)
              <img src="{{ $shopLogo }}" alt="{{ $shopName }}" style="max-height:48px;width:auto;">
            @else
              <h1 style="font-family:Georgia,serif;font-weight:500;font-size:26px;color:#a8324f;margin:0;">🌹 {{ $shopName }}</h1>
            @endif
          </td>
        </tr>

        <!-- Corps de la campagne (HTML libre saisi en admin) -->
        <tr>
          <td style="padding:24px 32px;font-size:15px;line-height:1.7;color:#4a3a36;">
            @if($subscriberName)
              <p style="margin:0 0 16px;">Bonjour {{ $subscriberName }},</p>
            @endif
            {!! $bodyHtml !!}
          </td>
        </tr>

        <!-- CTA boutique -->
        <tr>
          <td style="padding:8px 32px 28px;text-align:center;">
            <a href="{{ rtrim(config('app.url'), '/') }}"
               style="display:inline-block;padding:13px 32px;background:#e8336d;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;font-size:14px;">
              Visiter la boutique
            </a>
          </td>
        </tr>

        <!-- Pied : désabonnement (obligatoire) -->
        <tr>
          <td style="padding:18px 32px 28px;border-top:1px solid #f5e8e2;text-align:center;">
            <p style="font-size:12px;color:#a89992;margin:0 0 6px;line-height:1.6;">
              Vous recevez cet email car vous êtes inscrit(e) à la newsletter {{ $shopName }}.
            </p>
            <a href="{{ $unsubscribeUrl }}" style="font-size:12px;color:#a89992;text-decoration:underline;">
              Se désabonner
            </a>
          </td>
        </tr>
      </table>
      <p style="font-size:11px;color:#c4b5b0;margin:14px 0 0;">{{ $shopName }}</p>
    </td></tr>
  </table>
</body>
</html>
