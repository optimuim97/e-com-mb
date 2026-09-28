<?php

namespace Modules\Admin\Services;

use App\Support\ModuleRegistry;
use Illuminate\Support\Collection;
use Modules\Admin\Models\Setting;

class SettingService
{
    public const PUBLIC_KEYS = [
        // Boutique
        'shop_name', 'shop_tagline', 'shop_email', 'shop_phone',
        'shop_address', 'shop_city', 'shop_country', 'shop_logo_url',
        'shop_favicon_url',
        // Marque blanche : couleurs appliquées au thème par le front
        'brand_primary_color', 'brand_accent_color',
        // Page « À propos » et visuel de la page contact
        'about_title', 'about_intro', 'about_story',
        'about_image_url', 'about_video_url', 'about_video_poster_url',
        'about_value1_title', 'about_value1_desc',
        'about_value2_title', 'about_value2_desc',
        'about_value3_title', 'about_value3_desc',
        'contact_image_url',
        // Devise : base + activation du sélecteur XOF/EUR côté client
        'shop_currency', 'shop_currency_is_active',
        // Réseaux sociaux
        'social_instagram', 'social_facebook', 'social_tiktok',
        'social_youtube', 'social_twitter', 'social_pinterest',
        // Livraison. Le délai est saisi par la boutique, en français et en
        // anglais : c'est une phrase (« 2 à 5 jours ouvrés »), pas un nombre,
        // et elle s'affiche telle quelle sur la page d'accueil. Sans la version
        // anglaise, l'accueil traduit annonçait « 2 à 5 jours ouvrés delivery ».
        'shipping_free_threshold', 'shipping_default_cost',
        'shipping_delay', 'shipping_delay_en',
        // Ville de la zone locale (livraison maison, paiement à la livraison)
        'delivery_home_city',
        // SEO
        'seo_meta_title', 'seo_meta_description', 'seo_og_image',
        // Paiement mobile (affiché au client pour Wave / Orange Money / MTN)
        'payment_mobile_number', 'payment_wave_enabled', 'payment_orange_money_enabled',
        'payment_delivery_enabled',
        // Numéros dédiés par opérateur (priorité sur payment_mobile_number)
        'payment_wave_number', 'payment_orange_money_number',
        'payment_mtn_number', 'payment_mtn_enabled',
        // Lien de paiement Wave marchand (bouton « Payer avec Wave »)
        'payment_wave_link',
        // Instructions de paiement personnalisables par opérateur
        'payment_wave_instructions', 'payment_orange_money_instructions',
        'payment_mtn_instructions',
        // GeniusPay : si activé, Wave/Orange Money passent par le paiement en ligne
        'payment_geniuspay_enabled',
        // Tunnel de commande
        'enable_quick_order', 'floating_cart_enabled',
        // Bouton d'assistance WhatsApp de la vitrine. Séparé du numéro : la
        // boutique peut vouloir garder le numéro pour les liens de commande
        // sans afficher la pastille flottante sur chaque page.
        'whatsapp_button_hidden',
        // Promotions : cumul coupon + promo, et plafond de remise au comptoir.
        // Publics car la boutique doit pouvoir expliquer pourquoi un code ne
        // s'applique pas à un article déjà remisé.
        'promo_allow_stacking', 'pos_max_discount_percent',
        // WhatsApp. Attention : `whatsapp_notify_customer` commande l'affichage
        // d'un lien sur la page de confirmation, pas l'envoi d'un message — son
        // nom est trompeur, il est conservé pour ne rien casser.
        // Les deux clés `whatsapp_order_notify_*` commandent, elles, les envois.
        // Un interrupteur par destinataire : couper les alertes de la gérante
        // pendant un essai ne doit pas priver la clientèle de sa confirmation.
        'whatsapp_admin_number', 'whatsapp_notify_customer',
        'whatsapp_order_notify_customer', 'whatsapp_order_notify_admin',
        // Page d'accueil
        'home_hero_eyebrow', 'home_hero_title', 'home_hero_subtitle', 'home_hero_cta_label',
        'home_flash_label', 'home_promo_banner',
        'home_hero_image_url', 'home_hero_images',
        // Bande d'annonce (toggle visiteur)
        'announce_bar_enabled',
        // Variante visuelle du hero d'accueil ('1' | '2' | '3')
        'home_hero_variant',
    ];

    /**
     * Réglages publics, plus l'état résolu de chaque module
     * (`module_<clé>_enabled`).
     *
     * Les interrupteurs de modules ne sont pas lus tels quels en base : un
     * module jamais touché vaut le défaut du registre, et c'est au serveur de
     * le trancher. Le menu d'administration et la boutique lisent ainsi la même
     * réponse que le middleware `module:` qui ferme les routes.
     */
    public function getPublic(): Collection
    {
        return Setting::whereIn('key', self::PUBLIC_KEYS)
            ->pluck('value', 'key')
            ->merge(ModuleRegistry::publicStates());
    }

    public function getAll(): Collection
    {
        return Setting::all()->pluck('value', 'key');
    }

    public function updateMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
