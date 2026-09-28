import { watchEffect, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { sellingPrice } from '@/utils/pricing'
import { isOrderable } from '@/utils/availability'
import { useSettingsStore } from '@/stores/settings'

/**
 * Gestion des balises SEO par page (titre, description, canonique, Open Graph,
 * Twitter, données structurées).
 *
 * Écrit à la main plutôt qu'avec @unhead/vue : la seule chose qu'il nous
 * manquait était de poser quelques balises dans <head>, et une dépendance de
 * plus dans le bundle se paie sur le temps de chargement — qui est lui-même un
 * critère de classement.
 *
 * ⚠ L'origine est TOUJOURS lue sur window.location, jamais écrite en dur.
 * La même image front est promue de la recette vers la production : un domaine
 * figé dans le bundle la rendrait non promouvable, et ferait pointer les URL
 * canoniques de la recette vers la production (ou l'inverse).
 */

// Marque blanche : nom, logo et visuel viennent des réglages de la boutique.
function settingsStore() {
  try { return useSettingsStore() } catch { return null }
}
function siteName() {
  return settingsStore()?.shopName || 'Ma Boutique'
}
function siteLogo() {
  const s = settingsStore()
  return s?.shopLogoUrl || s?.shopFaviconUrl || '/logos/favicon-512.png'
}
function siteImage() {
  return settingsStore()?.seoOgImage || '/og-default.jpg'
}

/** Origine du site telle que servie (recette ou production). */
export function origin() {
  return typeof window !== 'undefined' ? window.location.origin : ''
}

/** URL absolue à partir d'un chemin ou d'une URL déjà absolue. */
export function absoluteUrl(pathOrUrl) {
  if (!pathOrUrl) return origin()
  if (/^https?:\/\//i.test(pathOrUrl)) return pathOrUrl
  return origin() + (pathOrUrl.startsWith('/') ? pathOrUrl : `/${pathOrUrl}`)
}

/** Coupe proprement une description à la longueur utile pour un extrait. */
export function excerpt(text, max = 158) {
  if (!text) return ''
  const clean = String(text)
    .replace(/<[^>]*>/g, ' ')      // le contenu produit/blog est du HTML
    .replace(/\s+/g, ' ')
    .trim()
  if (clean.length <= max) return clean
  return clean.slice(0, max - 1).replace(/[\s,;:.!-]+\S*$/, '') + '…'
}

/* ── Manipulation du <head> ─────────────────────────────────────────────── */

/** Pose (ou met à jour) une balise <meta>, identifiée par name ou property. */
function setMeta(key, value, attr = 'name') {
  const selector = `meta[${attr}="${key}"]`
  let el = document.head.querySelector(selector)

  if (!value) {
    el?.remove()
    return
  }
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }
  el.setAttribute('content', value)
}

function setLink(rel, href, extra = {}) {
  const keys = Object.entries(extra).map(([k, v]) => `[${k}="${v}"]`).join('')
  const selector = `link[rel="${rel}"]${keys}`
  let el = document.head.querySelector(selector)

  if (!href) {
    el?.remove()
    return
  }
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', rel)
    Object.entries(extra).forEach(([k, v]) => el.setAttribute(k, v))
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

/* ── Données structurées (JSON-LD) ──────────────────────────────────────── */

const JSONLD_ATTR = 'data-seo-jsonld'

/** Remplace l'ensemble des blocs JSON-LD posés par une page. */
function setJsonLd(blocks) {
  document.head
    .querySelectorAll(`script[${JSONLD_ATTR}]`)
    .forEach(el => el.remove())

  blocks.filter(Boolean).forEach((block) => {
    const el = document.createElement('script')
    el.type = 'application/ld+json'
    el.setAttribute(JSONLD_ATTR, '')
    el.textContent = JSON.stringify(block)
    document.head.appendChild(el)
  })
}

/* ── API publique ───────────────────────────────────────────────────────── */

/**
 * Applique les balises SEO de la page courante.
 *
 * @param {() => object} build  Fonction renvoyant la description SEO. Elle est
 *   réévaluée quand ses dépendances changent — indispensable sur les pages dont
 *   le contenu arrive après un appel API (produit, article).
 *
 * Champs acceptés :
 *   title, description, image, type ('website' | 'product' | 'article'),
 *   canonical (chemin ou URL), noindex (bool), jsonLd (objet ou tableau),
 *   locale ('fr' | 'en'), publishedAt, modifiedAt
 */
export function useSeo(build) {
  const route = useRoute()

  const stop = watchEffect(() => {
    const seo = (typeof build === 'function' ? build() : build) || {}

    const title = seo.title
      ? (seo.title.includes(siteName()) ? seo.title : `${seo.title} — ${siteName()}`)
      : siteName()

    const description = excerpt(seo.description)
    const canonical   = absoluteUrl(seo.canonical ?? route.path)
    const image       = absoluteUrl(seo.image || siteImage())
    const type        = seo.type || 'website'
    const locale      = seo.locale || 'fr'

    document.title = title
    document.documentElement.setAttribute('lang', locale)

    setMeta('description', description)
    setMeta('robots', seo.noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large')

    setLink('canonical', canonical)

    // Open Graph — c'est ce que lisent WhatsApp et Facebook au partage.
    setMeta('og:site_name',   siteName(), 'property')
    setMeta('og:title',       title, 'property')
    setMeta('og:description', description, 'property')
    setMeta('og:url',         canonical, 'property')
    setMeta('og:image',       image, 'property')
    setMeta('og:type',        type === 'product' ? 'product' : (type === 'article' ? 'article' : 'website'), 'property')
    setMeta('og:locale',      locale === 'en' ? 'en_US' : 'fr_FR', 'property')

    setMeta('article:published_time', seo.publishedAt || null, 'property')
    setMeta('article:modified_time',  seo.modifiedAt  || null, 'property')

    setMeta('twitter:card',        'summary_large_image')
    setMeta('twitter:title',       title)
    setMeta('twitter:description', description)
    setMeta('twitter:image',       image)

    const blocks = seo.jsonLd
      ? (Array.isArray(seo.jsonLd) ? seo.jsonLd : [seo.jsonLd])
      : []
    setJsonLd(blocks)
  })

  // Une page qui se démonte ne doit pas laisser ses données structurées
  // derrière elle : la suivante décrirait un produit qu'elle n'affiche pas.
  onUnmounted(() => {
    stop()
    setJsonLd([])
  })
}

/* ── Fabriques de données structurées ───────────────────────────────────── */

export function organizationJsonLd(settings = {}) {
  const sameAs = [settings.facebook_url, settings.instagram_url, settings.tiktok_url]
    .filter(Boolean)

  return {
    '@context': 'https://schema.org',
    '@type': 'Store',
    name: settings.shop_name || siteName(),
    url: origin(),
    logo: absoluteUrl(siteLogo()),
    image: absoluteUrl(siteImage()),
    description: settings.shop_tagline || undefined,
    telephone: settings.shop_phone || undefined,
    email: settings.shop_email || undefined,
    address: {
      '@type': 'PostalAddress',
      streetAddress: settings.shop_address || undefined,
      addressLocality: settings.shop_city || settingsStore()?.shopCity || undefined,
      addressCountry: settings.shop_country || settingsStore()?.shopCountry || undefined,
    },
    priceRange: settingsStore()?.shopCurrency || 'XOF',
    sameAs: sameAs.length ? sameAs : undefined,
  }
}

/** Bloc WebSite + recherche interne (peut donner une sitelinks searchbox). */
export function webSiteJsonLd() {
  return {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: siteName(),
    url: origin(),
    potentialAction: {
      '@type': 'SearchAction',
      target: {
        '@type': 'EntryPoint',
        urlTemplate: `${origin()}/products?search={search_term_string}`,
      },
      'query-input': 'required name=search_term_string',
    },
  }
}

/** Fil d'Ariane : [{ name, path }] — la page courante en dernier. */
export function breadcrumbJsonLd(items = []) {
  if (!items.length) return null

  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((item, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      name: item.name,
      item: absoluteUrl(item.path),
    })),
  }
}

export function productJsonLd(product, { reviews = [], rating = null } = {}) {
  if (!product) return null

  // Le prix annoncé à Google doit être celui de la page, promotions comprises :
  // un écart entre les deux fait retirer la fiche des résultats enrichis.
  const prix = sellingPrice(product)
  const image = product.images?.length
    ? product.images.map(i => absoluteUrl(i.url)).slice(0, 5)
    : [absoluteUrl(siteLogo())]

  const bloc = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: excerpt(product.short_description || product.description, 300),
    image,
    sku: product.sku || undefined,
    brand: { '@type': 'Brand', name: siteName() },
    offers: {
      '@type': 'Offer',
      url: absoluteUrl(`/products/${product.slug}`),
      priceCurrency: 'XOF',
      price: prix,
      // Quota de la semaine atteint : `OutOfStock`, et non `BackOrder` —
      // celui-ci annonce un article qu'on commande pour une livraison
      // ultérieure, ce que la boutique ne fait pas. L'état est temporaire, et
      // schema.org n'en demande pas davantage.
      availability: isOrderable(product)
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock',
      itemCondition: 'https://schema.org/NewCondition',
    },
  }

  // Note agrégée : uniquement si de vrais avis existent. Un bloc inventé est
  // une cause classique de sanction manuelle Google.
  if (rating && Number(rating.count) > 0) {
    bloc.aggregateRating = {
      '@type': 'AggregateRating',
      ratingValue: Number(rating.average).toFixed(1),
      reviewCount: Number(rating.count),
    }
  }

  if (reviews.length) {
    bloc.review = reviews.slice(0, 5).map(r => ({
      '@type': 'Review',
      author: { '@type': 'Person', name: r.user?.name || r.author || 'Cliente' },
      reviewRating: { '@type': 'Rating', ratingValue: Number(r.rating) || 5 },
      reviewBody: excerpt(r.comment, 300) || undefined,
      datePublished: r.created_at || undefined,
    }))
  }

  return bloc
}

export function articleJsonLd(post) {
  if (!post) return null

  return {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: post.meta_title || post.title,
    description: excerpt(post.meta_description || post.excerpt, 300),
    image: post.cover_url ? [absoluteUrl(post.cover_url)] : undefined,
    datePublished: post.published_at || undefined,
    dateModified: post.updated_at || post.published_at || undefined,
    author: { '@type': 'Organization', name: siteName() },
    publisher: {
      '@type': 'Organization',
      name: siteName(),
      logo: { '@type': 'ImageObject', url: absoluteUrl(siteLogo()) },
    },
    mainEntityOfPage: absoluteUrl(`/blog/${post.slug}`),
  }
}

/** Liste de produits (page catalogue / gamme). */
export function itemListJsonLd(products = []) {
  if (!products.length) return null

  return {
    '@context': 'https://schema.org',
    '@type': 'ItemList',
    itemListElement: products.slice(0, 30).map((p, i) => ({
      '@type': 'ListItem',
      position: i + 1,
      url: absoluteUrl(`/products/${p.slug}`),
      name: p.name,
    })),
  }
}

export { siteName }
