<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index, follow">
    <title>@yield('title') — {{ \App\Support\Shop::name() }}</title>
    <style>
        :root {
            --rose: #d4798f;
            --rose-dark: #3d2731;
            --bg: #fdf4f5;
            --card: #ffffff;
            --muted: #6b5560;
            --border: #f0dde1;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--rose-dark);
            line-height: 1.7;
        }
        header {
            background: var(--rose);
            color: #fff;
            padding: 28px 20px;
            text-align: center;
        }
        header .brand { font-size: 1.4rem; font-weight: 700; letter-spacing: .5px; }
        header .tagline { opacity: .9; font-size: .9rem; margin-top: 4px; }
        main {
            max-width: 820px;
            margin: -24px auto 48px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 40px 44px;
            box-shadow: 0 10px 30px rgba(61, 39, 49, .06);
        }
        h1 { font-size: 1.8rem; margin: 0 0 6px; }
        h2 { font-size: 1.2rem; margin: 32px 0 10px; color: var(--rose); }
        .updated { color: var(--muted); font-size: .9rem; margin-bottom: 28px; }
        p, li { color: var(--rose-dark); }
        a { color: var(--rose); }
        ul { padding-left: 20px; }
        li { margin-bottom: 6px; }
        .note {
            background: var(--bg);
            border-left: 3px solid var(--rose);
            padding: 14px 18px;
            border-radius: 8px;
            margin: 20px 0;
        }
        footer {
            text-align: center;
            color: var(--muted);
            font-size: .85rem;
            padding: 0 20px 40px;
        }
        footer a { color: var(--muted); }
        @media (max-width: 600px) {
            main { padding: 28px 22px; margin: -24px 14px 40px; }
        }
    </style>
</head>
<body>
    <header>
        <div class="brand">{{ \App\Support\Shop::name() }}</div>
        <div class="tagline">Soins du visage &amp; cosmétiques</div>
    </header>
    <main>
        @yield('content')
    </main>
    <footer>
        © {{ date('Y') }} {{ \App\Support\Shop::name() }} —
        <a href="/api/politique-de-confidentialite">Politique de confidentialité</a> ·
        <a href="/api/suppression-donnees">Suppression des données</a>
    </footer>
</body>
</html>
