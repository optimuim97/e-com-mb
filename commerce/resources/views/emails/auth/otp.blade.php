<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Code de réinitialisation</title>
</head>
<body style="margin:0;padding:32px 16px;background:#fdf5f1;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;color:#3a2520;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td align="center">
        <table role="presentation" width="100%" style="max-width:540px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(232,51,109,0.08);" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="padding:32px 32px 16px;text-align:center;">
              <h1 style="font-family:Georgia,serif;font-weight:500;font-size:24px;color:{{ \App\Support\Shop::primaryColorDark() }};margin:0 0 8px;">{{ \App\Support\Shop::name() }}</h1>
              <p style="font-size:14px;color:#7a6663;margin:0;">Code de réinitialisation de votre {{ $label }}</p>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 32px;text-align:center;">
              <div style="display:inline-block;padding:20px 36px;background:linear-gradient(135deg,#fdeef2,#fdf5f1);border:2px dashed #f5b8c5;border-radius:14px;">
                <p style="font-size:12px;color:#7a6663;margin:0 0 6px;letter-spacing:0.08em;text-transform:uppercase;">Votre code</p>
                <p style="font-family:'Courier New',monospace;font-size:32px;font-weight:700;color:{{ \App\Support\Shop::primaryColorDark() }};letter-spacing:0.25em;margin:0;">{{ $code }}</p>
              </div>
              <p style="font-size:13px;color:#7a6663;margin:18px 0 0;">Valable {{ $ttlMinutes }} minutes — ne partagez ce code avec personne.</p>
            </td>
          </tr>
          <tr>
            <td style="padding:16px 32px 32px;border-top:1px solid #f5e8e2;">
              <p style="font-size:12px;color:#a89992;margin:0;line-height:1.6;">
                Si vous n'avez pas demandé ce code, ignorez ce message — votre compte reste sécurisé.
              </p>
            </td>
          </tr>
        </table>
        <p style="font-size:11px;color:#a89992;margin:16px 0 0;">{{ \App\Support\Shop::name() }}</p>
      </td>
    </tr>
  </table>
</body>
</html>
