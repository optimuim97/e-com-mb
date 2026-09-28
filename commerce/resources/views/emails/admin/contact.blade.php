<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Nouveau message — {{ \App\Support\Shop::name() }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Helvetica Neue', Arial, sans-serif; background: #f9f4f1; color: #2d2d2d; font-size: 15px; line-height: 1.6; }
    .wrapper { max-width: 560px; margin: 32px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .header { background: linear-gradient(135deg, #c8a08a 0%, #b5876a 100%); padding: 28px 36px; }
    .header-title { font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 1px; }
    .header-sub { font-size: 11px; color: rgba(255,255,255,0.8); margin-top: 4px; letter-spacing: 3px; text-transform: uppercase; }
    .badge { display: inline-block; margin: 20px 36px 0; padding: 8px 20px; background: #fef3c7; color: #92400e; border-radius: 999px; font-size: 13px; font-weight: 600; }
    .body { padding: 28px 36px; }
    .field { margin-bottom: 18px; }
    .field-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #b5876a; font-weight: 700; margin-bottom: 4px; }
    .field-value { font-size: 15px; color: #2d2d2d; }
    .message-box { background: #faf7f5; border-radius: 10px; padding: 16px; font-size: 14px; color: #4a4a4a; line-height: 1.7; white-space: pre-wrap; }
    .footer { background: #faf7f5; padding: 16px 36px; text-align: center; border-top: 1px solid #f0e8e3; font-size: 11px; color: #b0a09a; }
  </style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="header-title">{{ \App\Support\Shop::name() }}</div>
    <div class="header-sub">Administration</div>
  </div>
  <div class="badge">📩 Nouveau message reçu</div>
  <div class="body">
    <div class="field">
      <div class="field-label">Nom</div>
      <div class="field-value">{{ $inquiry->name }}</div>
    </div>
    <div class="field">
      <div class="field-label">Email</div>
      <div class="field-value">{{ $inquiry->email }}</div>
    </div>
    @if($inquiry->phone)
    <div class="field">
      <div class="field-label">Téléphone</div>
      <div class="field-value">{{ $inquiry->phone }}</div>
    </div>
    @endif
    <div class="field">
      <div class="field-label">Sujet</div>
      <div class="field-value">{{ $inquiry->subject }}</div>
    </div>
    <div class="field">
      <div class="field-label">Message</div>
      <div class="message-box">{{ $inquiry->message }}</div>
    </div>
    <div class="field" style="margin-top: 24px; color: #9a8a80; font-size: 12px;">
      Reçu le {{ $inquiry->created_at->setTimezone('Africa/Abidjan')->format('d/m/Y à H\hi') }}
    </div>
  </div>
  <div class="footer">{{ \App\Support\Shop::name() }} · Formulaire de contact · © {{ date('Y') }}</div>
</div>
</body>
</html>
