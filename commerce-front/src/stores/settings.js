import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api'
import { useCurrencyStore } from '@/stores/currency'
// Hors composant : la langue courante et les traductions se lisent sur
// l'instance globale (voir cart.store.js, même convention).
import i18n from '@/i18n'
import { applyBrandTheme, DEFAULT_PRIMARY, DEFAULT_ACCENT } from '@/theme/brandTheme'

/* ─── Icônes SVG des réseaux sociaux ──────────────────────────────────────── */
const SOCIAL_ICONS = {
  facebook:  `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>`,
  instagram: `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>`,
  tiktok:    `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.32 6.32 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.24 8.24 0 004.84 1.56V6.81a4.85 4.85 0 01-1.07-.12z"/></svg>`,
  youtube:   `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>`,
  twitter:   `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>`,
  pinterest: `<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12c0 5.084 3.163 9.426 7.627 11.174-.105-.949-.2-2.405.042-3.441.218-.937 1.407-5.965 1.407-5.965s-.359-.719-.359-1.782c0-1.668.967-2.914 2.171-2.914 1.023 0 1.518.769 1.518 1.69 0 1.029-.655 2.568-.994 3.995-.283 1.194.599 2.169 1.777 2.169 2.133 0 3.772-2.249 3.772-5.495 0-2.873-2.064-4.882-5.012-4.882-3.414 0-5.418 2.561-5.418 5.207 0 1.031.397 2.138.893 2.738a.36.36 0 01.083.345l-.333 1.36c-.053.22-.174.267-.402.161-1.499-.698-2.436-2.889-2.436-4.649 0-3.785 2.75-7.262 7.929-7.262 4.163 0 7.398 2.967 7.398 6.931 0 4.136-2.607 7.464-6.227 7.464-1.216 0-2.359-.632-2.75-1.378l-.748 2.853c-.271 1.043-1.002 2.35-1.492 3.146C9.57 23.812 10.763 24 12 24c6.627 0 12-5.373 12-12S18.627 0 12 0z"/></svg>`,
}

export const useSettingsStore = defineStore('settings', () => {
  const data  = ref({})
  const ready = ref(false)

  /* ── Fetch (idempotent) ───────────────────────────────────────────────── */
  // Une seule requête en vol : App.vue, le routeur et les layouts appellent
  // fetch() au même moment au premier chargement.
  let pending = null
  function fetch() {
    if (ready.value) return Promise.resolve()
    if (!pending) pending = load().finally(() => { pending = null })
    return pending
  }

  async function load() {
    try {
      const { data: res } = await api.get('/settings')
      data.value = res ?? {}
    } catch {
      data.value = {}
    } finally {
      ready.value = true
      // Couleurs de la marque dès que les réglages sont connus
      applyBrandTheme(data.value)
    }
  }

  /** Recharge les réglages (après enregistrement dans l'admin). */
  async function refresh() {
    ready.value = false
    await fetch()
  }

  /* ── Helpers ─────────────────────────────────────────────────────────── */
  const get = (key, fallback = '') => computed(() => data.value[key] || fallback)
  const bool = (key, defaultTrue = false) => computed(() => {
    const v = data.value[key]
    if (v === undefined || v === null || v === '') return defaultTrue
    return v === true || v === 'true' || v === '1' || v === 1
  })

  /* ── Marque blanche : thème + modules ─────────────────────────────────── */
  const brandPrimaryColor = get('brand_primary_color', DEFAULT_PRIMARY)
  const brandAccentColor  = get('brand_accent_color',  DEFAULT_ACCENT)

  /** Réapplique le thème à la volée (aperçu dans les réglages admin). */
  function applyTheme() { applyBrandTheme(data.value) }

  /**
   * Le module est-il allumé ?
   *
   * L'API publique renvoie `module_<clé>_enabled` déjà résolu par le registre
   * des modules (défaut compris). Un module inconnu du serveur, ou des réglages
   * pas encore chargés, valent « allumé » : on ne masque rien par accident.
   */
  function moduleEnabled(key) {
    const v = data.value[`module_${key}_enabled`]
    if (v === undefined || v === null || v === '') return true
    return v === true || v === 'true' || v === '1' || v === 1
  }

  /* ── Boutique ─────────────────────────────────────────────────────────── */
  const shopName     = get('shop_name',     'Ma Boutique')
  const shopTagline  = get('shop_tagline',  '')
  const shopFaviconUrl = get('shop_favicon_url', '')
  const shopEmail    = get('shop_email',    '')
  const shopPhone    = get('shop_phone',    '')
  const shopAddress  = get('shop_address',  '')
  const shopCity     = get('shop_city',     '')
  const shopCountry  = get('shop_country',  '')
  const shopLogoUrl  = get('shop_logo_url', '')
  const shopCurrency = get('shop_currency', 'XOF')
  // Affichage du sélecteur de devise (multi-devises) — activé depuis l'admin
  const shopCurrencyIsActive = bool('shop_currency_is_active', false)

  /* ── Livraison ────────────────────────────────────────────────────────── */
  const shippingDefaultCost   = computed(() => Number(data.value.shipping_default_cost   || 0))
  const shippingFreeThreshold = computed(() => Number(data.value.shipping_free_threshold || 0))
  /**
   * Délai de livraison affiché en boutique.
   *
   * C'est une phrase saisie par la boutique, pas une donnée calculable : elle
   * existe donc en deux versions. Sans la version anglaise, l'accueil traduit
   * affichait « 2 à 5 jours ouvrés delivery & tracked ».
   *
   * Repli en cascade : version de la langue courante → version française →
   * valeur par défaut traduite. La boutique qui ne remplit que le français
   * garde ainsi un affichage cohérent des deux côtés.
   */
  const shippingDelay = computed(() => {
    const enAnglais = (data.value['shipping_delay_en'] ?? '').trim()
    const enFrancais = (data.value['shipping_delay'] ?? '').trim()

    if (i18n.global.locale.value === 'en') {
      return enAnglais || enFrancais || i18n.global.t('benefits.deliveryDelayDefault')
    }

    return enFrancais || i18n.global.t('benefits.deliveryDelayDefault')
  })
  const shippingZones         = get('shipping_zones', '')
  /**
   * Ville de la zone locale : livraison par les livreurs de la boutique et
   * paiement à la livraison. Hors de cette ville, prépaiement obligatoire.
   * Réglage `delivery_home_city` (défaut : Abidjan).
   */
  const homeCity = get('delivery_home_city', 'Abidjan')
  const shippingPickupEnabled = bool('shipping_pickup_enabled', false)

  /* ── Paiement ─────────────────────────────────────────────────────────── */
  const paymentWaveEnabled        = bool('payment_wave_enabled',         true)
  const paymentOrangeMoneyEnabled = bool('payment_orange_money_enabled', true)
  const paymentDeliveryEnabled    = bool('payment_delivery_enabled',     true)
  const paymentStripeEnabled      = bool('payment_stripe_enabled',       false)
  const paymentMobileNumber             = get('payment_mobile_number', '')
  const paymentWaveNumber               = get('payment_wave_number', '')
  // Lien de paiement Wave marchand (ex : https://pay.wave.com/m/M_xxx/c/sn/)
  const paymentWaveLink                 = get('payment_wave_link', '')
  const paymentWaveInstructions         = get('payment_wave_instructions', 'Envoyez le montant exact sur ce numéro Wave, puis communiquez votre référence de commande.')
  const paymentOrangeMoneyNumber        = get('payment_orange_money_number', '')
  const paymentOrangeMoneyInstructions  = get('payment_orange_money_instructions', 'Composez #144# sur votre téléphone Orange, puis envoyez le montant exact avec votre référence.')
  const paymentMtnEnabled               = bool('payment_mtn_enabled', false)
  const paymentMtnNumber                = get('payment_mtn_number', '')
  const paymentMtnInstructions          = get('payment_mtn_instructions', 'Composez *133# (MTN MoMo), puis envoyez le montant exact avec votre référence de commande.')
  const paymentGeniuspayEnabled         = bool('payment_geniuspay_enabled', false)

  /* ── Tunnel de commande ───────────────────────────────────────────────── */
  const enableQuickOrder          = bool('enable_quick_order',           true)
  const floatingCartEnabled       = bool('floating_cart_enabled',        true)
  // Pastille d'assistance WhatsApp de la vitrine. Distincte du numéro : la
  // boutique peut garder le numéro pour les liens de commande sans afficher
  // la pastille sur chaque page.
  //
  // Réglage formulé en « masquer », comme l'interrupteur de l'administration :
  // stocker l'inverse de ce que l'écran affiche obligerait à retourner la
  // valeur dans sa tête à chaque lecture. Absent = pastille visible.
  const whatsappButtonHidden      = bool('whatsapp_button_hidden',       false)

  /* ── Page d'accueil ───────────────────────────────────────────────────── */
  const heroImageUrl              = get('home_hero_image_url',           '')
  // Liste d'images hero (séparées par des virgules ou retours à la ligne)
  const heroImages = computed(() => {
    const raw = data.value.home_hero_images || ''
    return String(raw)
      .split(/[\n,]+/)
      .map(s => s.trim())
      .filter(Boolean)
  })

  /* Méthodes actives pour le footer / checkout */
  const activePayments = computed(() => {
    const list = []
    if (paymentWaveEnabled.value)        list.push('Wave')
    if (paymentOrangeMoneyEnabled.value) list.push('Orange Money')
    if (paymentDeliveryEnabled.value)    list.push('À la livraison')
    if (paymentStripeEnabled.value)      list.push('Stripe')
    return list
  })

  /* Annonce barre (navbar) — active/désactivable via admin (défaut: activée) */
  const announceEnabled = computed(() => {
    const v = data.value.announce_bar_enabled
    // Si la clé n'existe pas encore en DB, défaut = activée (rétrocompat)
    if (v === undefined || v === null || v === '') return true
    return v === true || v === 'true' || v === '1' || v === 1
  })

  /* Annonce barre (navbar) — override par home_promo_banner si défini */
  const announceText = computed(() => {
    if (data.value.home_promo_banner) return data.value.home_promo_banner
    // Pas de promesse de livraison gratuite (aucune n'existe réellement → risque de litige)
    const parts = []
    const payNames = []
    if (paymentWaveEnabled.value)        payNames.push('Wave')
    if (paymentOrangeMoneyEnabled.value) payNames.push('Orange Money')
    if (payNames.length) parts.push(`Paiement sécurisé ${payNames.join(' & ')}`)
    return parts.join('  ·  ')
      || `Paiement sécurisé · Livraison rapide`
  })

  /* ── Réseaux sociaux ──────────────────────────────────────────────────── */
  const socialFacebook  = get('social_facebook',  '')
  const socialInstagram = get('social_instagram', '')
  const socialTikTok    = get('social_tiktok',    '')
  const socialYouTube   = get('social_youtube',   '')
  const socialTwitter   = get('social_twitter',   '')
  const socialPinterest = get('social_pinterest', '')

  /* Réseaux avec URL définie */
  const activeSocials = computed(() => {
    const map = [
      { key: 'facebook',  name: 'Facebook',  url: socialFacebook.value  },
      { key: 'instagram', name: 'Instagram', url: socialInstagram.value },
      { key: 'tiktok',    name: 'TikTok',    url: socialTikTok.value    },
      { key: 'youtube',   name: 'YouTube',   url: socialYouTube.value   },
      { key: 'twitter',   name: 'X',         url: socialTwitter.value   },
      { key: 'pinterest', name: 'Pinterest', url: socialPinterest.value },
    ]
    return map
      .filter(s => s.url)
      .map(s => ({ ...s, icon: SOCIAL_ICONS[s.key] }))
  })

  /* ── WhatsApp ─────────────────────────────────────────────────────────── */
  const whatsappNumber         = get('whatsapp_admin_number',    '')
  const whatsappNotifyCustomer = bool('whatsapp_notify_customer', false)

  /* ── SEO ──────────────────────────────────────────────────────────────── */
  const seoMetaTitle       = get('seo_meta_title',       '')
  const seoMetaDescription = get('seo_meta_description', '')
  const seoOgImage         = get('seo_og_image',         '')

  /* ── Page d'accueil (personnalisable depuis admin) ───────────────────── */
  const homeHeroEyebrow  = get('home_hero_eyebrow',  '')
  const homeHeroTitle    = get('home_hero_title',    '')
  const homeHeroSubtitle = get('home_hero_subtitle', '')
  const homeFlashLabel   = get('home_flash_label',   'Ventes flash')
  const homePromoBanner  = get('home_promo_banner',  '')
  // Variante visuelle du hero : '1' plein écran · '2' split · '3' classique
  const homeHeroVariant  = get('home_hero_variant',  '1')

  /* Met à jour une clé localement (sans refetch) — utilisé par le switcher admin */
  function setLocal(key, value) {
    data.value = { ...data.value, [key]: value }
  }

  /* ── Formatage prix : délègue au store de devise (multi-devises XOF/EUR) ─
     Le montant reçu est exprimé dans la devise de base (shop_currency) ;
     le store de devise le convertit/formate selon le choix du visiteur. */
  function formatPrice(amount) {
    return useCurrencyStore().format(amount ?? 0)
  }

  return {
    data, ready, fetch, refresh, setLocal,
    // Marque blanche
    brandPrimaryColor, brandAccentColor, applyTheme, moduleEnabled,
    // Boutique
    shopName, shopTagline, shopEmail, shopPhone,
    shopAddress, shopCity, shopCountry, shopLogoUrl, shopFaviconUrl, shopCurrency,
    shopCurrencyIsActive,
    // Livraison
    shippingDefaultCost, shippingFreeThreshold, shippingDelay,
    shippingZones, shippingPickupEnabled, homeCity,
    // Paiement
    paymentWaveEnabled, paymentOrangeMoneyEnabled,
    paymentDeliveryEnabled, paymentStripeEnabled,
    paymentMobileNumber, activePayments,
    paymentWaveNumber, paymentWaveLink, paymentWaveInstructions,
    paymentOrangeMoneyNumber, paymentOrangeMoneyInstructions,
    paymentMtnEnabled, paymentMtnNumber, paymentMtnInstructions,
    paymentGeniuspayEnabled,
    // Tunnel
    enableQuickOrder, floatingCartEnabled, whatsappButtonHidden,
    // Hero
    heroImageUrl, heroImages,
    // Announce
    announceText, announceEnabled,
    // Réseaux sociaux
    socialFacebook, socialInstagram, socialTikTok,
    socialYouTube, socialTwitter, socialPinterest,
    activeSocials,
    // WhatsApp
    whatsappNumber, whatsappNotifyCustomer,
    // SEO
    seoMetaTitle, seoMetaDescription, seoOgImage,
    // Home
    homeHeroEyebrow, homeHeroTitle, homeHeroSubtitle, homeFlashLabel, homePromoBanner,
    homeHeroVariant,
    // Helpers
    formatPrice,
  }
})
