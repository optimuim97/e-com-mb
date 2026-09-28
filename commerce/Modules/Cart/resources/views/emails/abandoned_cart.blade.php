<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <title>Votre panier {{ \App\Support\Shop::name() }}</title>
  <style>
    /* ── Reset ── */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { margin: 0; padding: 0; background-color: #fdf6f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; color: #2d1b15; }
    a { text-decoration: none; }
    img { border: 0; display: block; max-width: 100%; height: auto; }

    /* ── Wrapper ── */
    .email-wrapper { width: 100%; background-color: #fdf6f0; padding: 32px 16px; }
    .email-body    { max-width: 580px; margin: 0 auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 24px rgba(232,51,109,0.08); }

    /* ── Header ── */
    .header {
      background: linear-gradient(135deg, {{ \App\Support\Shop::primaryColor() }} 0%, {{ \App\Support\Shop::primaryColorDark() }} 100%);
      padding: 40px 40px 32px;
      text-align: center;
    }
    .header__logo {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 24px;
    }
    .header__logo-circle {
      width: 44px; height: 44px;
      background: rgba(255,255,255,0.15);
      border: 1.5px solid rgba(255,255,255,0.4);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
    }
    .header__logo-name {
      color: #fff;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: -0.3px;
    }
    .header__emoji {
      font-size: 48px;
      display: block;
      margin-bottom: 16px;
    }
    .header__title {
      color: #fff;
      font-size: 24px;
      font-weight: 700;
      line-height: 1.3;
      margin-bottom: 8px;
    }
    .header__sub {
      color: rgba(255,255,255,0.85);
      font-size: 15px;
      line-height: 1.5;
    }

    /* ── Content ── */
    .content { padding: 36px 40px; }

    .greeting {
      font-size: 17px;
      color: #2d1b15;
      margin-bottom: 12px;
      font-weight: 500;
    }

    .intro {
      font-size: 15px;
      color: #7a5c52;
      line-height: 1.7;
      margin-bottom: 28px;
    }

    /* ── Products table ── */
    .products-title {
      font-size: 13px;
      font-weight: 700;
      color: {{ \App\Support\Shop::primaryColor() }};
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 12px;
    }

    .products-list {
      border: 1.5px solid #f3e0e8;
      border-radius: 14px;
      overflow: hidden;
      margin-bottom: 28px;
    }

    .product-row {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px 20px;
      border-bottom: 1px solid #fce8f0;
    }
    .product-row:last-child { border-bottom: none; }

    .product-thumb {
      width: 64px; height: 64px;
      border-radius: 10px;
      object-fit: cover;
      flex-shrink: 0;
      background: #fdf0f5;
    }
    .product-thumb-placeholder {
      width: 64px; height: 64px;
      border-radius: 10px;
      background: linear-gradient(135deg, #fce8f0, #fdf0f5);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
    }

    .product-info { flex: 1; min-width: 0; }
    .product-name {
      font-size: 14px;
      font-weight: 600;
      color: #2d1b15;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 4px;
    }
    .product-meta {
      font-size: 12px;
      color: #b07a7a;
    }
    .product-price {
      font-size: 15px;
      font-weight: 700;
      color: {{ \App\Support\Shop::primaryColor() }};
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* Total row */
    .cart-total {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 14px 20px;
      background: #fdf0f5;
      border-top: 1.5px solid #f3e0e8;
    }
    .cart-total__label {
      font-size: 14px;
      font-weight: 600;
      color: #7a5c52;
    }
    .cart-total__value {
      font-size: 18px;
      font-weight: 800;
      color: {{ \App\Support\Shop::primaryColor() }};
    }

    /* ── CTA ── */
    .cta-wrap { text-align: center; margin-bottom: 32px; }
    .cta-btn {
      display: inline-block;
      background: linear-gradient(135deg, {{ \App\Support\Shop::primaryColor() }}, {{ \App\Support\Shop::primaryColorDark() }});
      color: #ffffff !important;
      font-size: 16px;
      font-weight: 700;
      padding: 16px 40px;
      border-radius: 50px;
      letter-spacing: 0.2px;
      box-shadow: 0 4px 16px rgba(232,51,109,0.35);
    }
    .cta-hint {
      font-size: 12px;
      color: #b07a7a;
      margin-top: 10px;
    }

    /* ── Urgency (J+3 only) ── */
    .urgency-box {
      background: linear-gradient(135deg, #fff1f5, #fce8f0);
      border: 1.5px solid #f7b8cf;
      border-radius: 12px;
      padding: 16px 20px;
      margin-bottom: 28px;
      text-align: center;
    }
    .urgency-box__icon { font-size: 24px; margin-bottom: 6px; }
    .urgency-box__text { font-size: 14px; color: {{ \App\Support\Shop::primaryColorDark() }}; font-weight: 600; }

    /* ── Trust badges ── */
    .trust-badges {
      display: flex;
      gap: 0;
      margin-bottom: 32px;
      border: 1px solid #f3e0e8;
      border-radius: 12px;
      overflow: hidden;
    }
    .trust-badge {
      flex: 1;
      text-align: center;
      padding: 14px 8px;
      border-right: 1px solid #f3e0e8;
    }
    .trust-badge:last-child { border-right: none; }
    .trust-badge__icon { font-size: 20px; margin-bottom: 4px; }
    .trust-badge__text { font-size: 11px; color: #7a5c52; font-weight: 500; }

    /* ── Footer ── */
    .footer {
      background: #fdf6f0;
      padding: 28px 40px;
      text-align: center;
      border-top: 1px solid #f3e0e8;
    }
    .footer__brand {
      font-size: 16px;
      font-weight: 700;
      color: {{ \App\Support\Shop::primaryColor() }};
      margin-bottom: 8px;
    }
    .footer__text {
      font-size: 12px;
      color: #b07a7a;
      line-height: 1.6;
      margin-bottom: 16px;
    }
    .footer__links {
      font-size: 12px;
      color: #b07a7a;
    }
    .footer__links a {
      color: {{ \App\Support\Shop::primaryColor() }};
      text-decoration: underline;
    }

    /* ── Responsive ── */
    @media (max-width: 480px) {
      .content { padding: 24px 20px; }
      .header  { padding: 32px 20px 24px; }
      .footer  { padding: 24px 20px; }
      .product-row { padding: 12px 16px; }
      .cart-total { padding: 12px 16px; }
      .trust-badges { flex-direction: column; }
      .trust-badge { border-right: none; border-bottom: 1px solid #f3e0e8; }
      .trust-badge:last-child { border-bottom: none; }
      .cta-btn { padding: 14px 28px; font-size: 15px; }
    }
  </style>
</head>
<body>
<div class="email-wrapper">
  <div class="email-body">

    <!-- Header -->
    <div class="header">
      <div class="header__logo">
        <div class="header__logo-circle">🌹</div>
        <span class="header__logo-name">{{ \App\Support\Shop::name() }}</span>
      </div>

      @if($day === 1)
        <span class="header__emoji">🛒</span>
        <h1 class="header__title">Votre panier vous attend !</h1>
        <p class="header__sub">Vous avez laissé de belles choses derrière vous…</p>
      @else
        <span class="header__emoji">✨</span>
        <h1 class="header__title">Dernière chance !</h1>
        <p class="header__sub">Votre panier expire bientôt. Ne laissez pas filer ces trésors.</p>
      @endif
    </div>

    <!-- Content -->
    <div class="content">

      <p class="greeting">Bonjour {{ $customerName }} 💕</p>

      @if($day === 1)
        <p class="intro">
          Vous avez ajouté des produits à votre panier mais n'avez pas finalisé votre commande.
          Votre sélection est toujours là, prête à vous rejoindre.
          Il suffit d'un clic pour retrouver vos articles et profiter de votre routine beauté préférée.
        </p>
      @else
        <p class="intro">
          Il y a quelques jours, vous avez sélectionné de belles choses chez {{ \App\Support\Shop::name() }}.
          Nous voulions vous rappeler une dernière fois que votre panier vous attend encore.
          Les stocks peuvent évoluer — profitez-en pendant qu'il est encore temps !
        </p>
      @endif

      <!-- Urgency banner for J+3 -->
      @if($day === 3)
        <div class="urgency-box">
          <div class="urgency-box__icon">⏰</div>
          <p class="urgency-box__text">Les stocks sont limités — votre sélection pourrait ne plus être disponible !</p>
        </div>
      @endif

      <!-- Products -->
      <p class="products-title">Votre sélection</p>

      <div class="products-list">
        @foreach($cart->items as $item)
          <div class="product-row">
            @php
              $imgUrl = $item->product->images->first()?->url ?? null;
            @endphp
            @if($imgUrl)
              <img src="{{ $imgUrl }}" alt="{{ $item->product->name }}" class="product-thumb" />
            @else
              <div class="product-thumb-placeholder">💄</div>
            @endif

            <div class="product-info">
              <p class="product-name">{{ $item->product->name }}</p>
              <p class="product-meta">
                Qté : {{ $item->quantity }}
                @if($item->variant)
                  &bull; {{ $item->variant->name }}
                @endif
              </p>
            </div>

            <p class="product-price">
              {{ number_format($item->total_price, 0, ',', ' ') }} FCFA
            </p>
          </div>
        @endforeach

        <!-- Total -->
        <div class="cart-total">
          <span class="cart-total__label">Total estimé</span>
          <span class="cart-total__value">
            {{ number_format($cart->subtotal, 0, ',', ' ') }} FCFA
          </span>
        </div>
      </div>

      <!-- CTA -->
      <div class="cta-wrap">
        <a href="{{ $cartUrl }}" class="cta-btn">
          🛍️ &nbsp; Reprendre mon panier
        </a>
        <p class="cta-hint">
          @if($day === 1)
            Votre panier est sauvegardé. Finalisez votre commande en quelques secondes.
          @else
            Ne tardez pas — les stocks sont limités !
          @endif
        </p>
      </div>

      <!-- Trust badges -->
      <div class="trust-badges">
        <div class="trust-badge">
          <p class="trust-badge__text">100 % naturel</p>
        </div>
        <div class="trust-badge">
          <p class="trust-badge__text">Livraison 48h</p>
        </div>
        <div class="trust-badge">
          <p class="trust-badge__text">Votre satisfaction garantie</p>
        </div>
        <div class="trust-badge">
          <p class="trust-badge__text">Paiement sécurisé</p>
        </div>
      </div>

    </div>

    <!-- Footer -->
    <div class="footer">
      <p class="footer__brand">{{ \App\Support\Shop::name() }}</p>
      <p class="footer__text">
        Vous recevez cet email car vous avez un panier en attente sur notre boutique.<br>
        Si vous avez déjà passé commande, ignorez simplement cet email.
      </p>
      <p class="footer__links">
        <a href="{{ $shopUrl }}">Visiter la boutique</a>
        &bull;
        <a href="{{ $shopUrl }}/account">Mon compte</a>
      </p>
    </div>

  </div>

  <!-- Pre-header spacing -->
  <p style="color:#fdf6f0;font-size:1px;margin:0;">&nbsp;</p>
</div>
</body>
</html>
