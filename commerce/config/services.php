<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        // Clés lues aussi par Cashier (config/cashier.php). On les redéclare ici
        // pour le StripeService du module Payment.
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        // Devise de facturation Stripe (le XOF n'est pas supporté par Stripe).
        'currency'       => env('CASHIER_CURRENCY', 'eur'),
        // Taux de conversion FIXE XOF → EUR (le franc CFA est arrimé à l'euro).
        // 1 EUR = 655.957 XOF.
        'xof_per_eur'    => (float) env('STRIPE_XOF_PER_EUR', 655.957),
        // URLs front de retour après Checkout. On réutilise la page /payment/return
        // existante (qui interroge /payment/{provider}/status/{orderNumber}).
        'success_url'    => env('STRIPE_SUCCESS_URL', env('APP_FRONTEND_URL', 'http://localhost:5174') . '/payment/return?order={order_number}'),
        'cancel_url'     => env('STRIPE_CANCEL_URL', env('APP_FRONTEND_URL', 'http://localhost:5174') . '/payment/return?order={order_number}&status=cancelled'),
    ],

    'wave' => [
        'api_key'        => env('WAVE_API_KEY'),
        'webhook_secret' => env('WAVE_WEBHOOK_SECRET'),
        'environment'    => env('WAVE_ENVIRONMENT', 'sandbox'),
        'base_url'       => 'https://api.wave.com/v1/',
        'success_url'    => env('APP_URL') . '/payment/wave/callback?status=success',
        'error_url'      => env('APP_URL') . '/payment/wave/callback?status=error',
    ],

    'whatsapp' => [
        'token'           => env('WHATSAPP_API_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        // Version de la Graph API (v18.0 est dépréciée). Configurable.
        'graph_version'   => env('WHATSAPP_GRAPH_VERSION', 'v22.0'),
        // Chaîne libre choisie par nous, à recopier à l'identique dans le
        // tableau de bord Meta : elle n'y sert qu'à prouver que l'URL nous
        // appartient au moment de l'enregistrement.
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
        // Clé secrète de l'application Meta, qui signe chaque événement.
        // Sans elle, aucun POST n'est accepté.
        'app_secret'      => env('WHATSAPP_APP_SECRET'),
        // Identifiant du WhatsApp Business Account — le compte, pas le numéro.
        // Sert à lister les modèles de message et à diagnostiquer la config.
        'waba_id'         => env('WHATSAPP_WABA_ID'),

        /*
         * Modèles de message approuvés par Meta.
         *
         * WhatsApp n'autorise le texte libre que dans les 24 h qui suivent le
         * dernier message de la cliente. Une confirmation de commande, un code
         * de connexion ou une alerte à la gérante partent presque toujours hors
         * de cette fenêtre : ils exigent un modèle validé au préalable.
         *
         * Les noms sont configurables parce qu'ils doivent correspondre au
         * caractère près à ce que Meta a approuvé — et qu'un refus oblige
         * parfois à recréer le modèle sous un autre nom.
         */
        'templates'       => [
            'order_confirmation' => env('WHATSAPP_TPL_ORDER', 'order_confirmation'),
            'admin_new_order'    => env('WHATSAPP_TPL_ADMIN_ORDER', 'admin_new_order'),
            'otp_code'           => env('WHATSAPP_TPL_OTP', 'otp_code'),
            'lang'               => env('WHATSAPP_TPL_LANG', 'fr'),
        ],
    ],

    'cinetpay' => [
        'api_key'         => env('CINETPAY_API_KEY'),
        'site_id'         => env('CINETPAY_SITE_ID'),
        // URL de base du frontend pour le retour client (différent de APP_URL en prod)
        'return_base_url' => env('CINETPAY_RETURN_BASE_URL', env('APP_FRONTEND_URL', 'http://localhost:5174')),
    ],

    'geniuspay' => [
        // Clés sandbox par défaut : versionnées ici pour ne pas être perdues en
        // prod si l'env n'est pas renseigné. Surchargées par les vraies clés live
        // via les variables d'environnement.
        'api_key'         => env('GENIUSPAY_API_KEY', 'pk_sandbox_bND0O6Im7kQpkDFrG8n2zuBXFapWHhWz'),
        'secret_key'      => env('GENIUSPAY_SECRET_KEY', 'sk_sandbox_7b85c063d37e0bf5bef5595edae5d653b0926c0d0550198e8e3ddba9ab1e8425'),
        'base_url'        => env('GENIUSPAY_BASE_URL', 'https://pay.genius.ci/api/v1/merchant'),
        'webhook_secret'  => env('GENIUSPAY_WEBHOOK_SECRET'),
        // URL de base du frontend pour le retour client après paiement.
        'return_base_url' => env('GENIUSPAY_RETURN_BASE_URL', env('APP_FRONTEND_URL', 'http://localhost:5174')),
    ],

    // ── Authentification sociale (réutilisable) ──────────────────────────────
    'facebook' => [
        'client_id'     => env('FACEBOOK_APP_ID'),
        'client_secret' => env('FACEBOOK_APP_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI', env('APP_URL') . '/auth/facebook/callback'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v19.0'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/google/callback'),
    ],

    // URL du frontend vers laquelle renvoyer le résultat OAuth (popup postMessage)
    'social_auth' => [
        'frontend_url' => env('APP_FRONTEND_URL', env('CINETPAY_RETURN_BASE_URL', 'http://localhost:5174')),
    ],

];
