/**
 * blog.fields.js  (admin)
 * Contrat entre le formulaire Vue et la FormRequest Laravel StorePostRequest / UpdatePostRequest.
 *
 * - Clé    : nom du champ dans le formulaire Vue (form.xxx)
 * - api    : nom exact attendu par Laravel dans le body
 * - label  : affiché dans <FormField :def="FIELDS.xxx">
 * - type   : type de champ (text, textarea, select, datetime, toggle)
 * - required : validation Laravel (required / nullable)
 */

export const FIELDS = {
  // ── Contenu ────────────────────────────────────────────────────────────────
  title:            { api: 'title',            label: 'Titre de l\'article',   type: 'text',     required: true  },
  slug:             { api: 'slug',             label: 'Slug (URL)',             type: 'text',     required: false },
  excerpt:          { api: 'excerpt',          label: 'Extrait',               type: 'textarea', required: false },
  content:          { api: 'content',          label: 'Contenu',               type: 'wysiwyg',  required: false },

  // ── Catégorie ──────────────────────────────────────────────────────────────
  post_category_id: { api: 'post_category_id', label: 'Catégorie',             type: 'select',   required: false },

  // ── Publication ────────────────────────────────────────────────────────────
  status:           { api: 'status',           label: 'Statut',                type: 'select',   required: true  },
  published_at:     { api: 'published_at',     label: 'Date de publication',   type: 'datetime', required: false },

  // ── SEO ────────────────────────────────────────────────────────────────────
  meta_title:       { api: 'meta_title',       label: 'Meta title',            type: 'text',     required: false },
  meta_description: { api: 'meta_description', label: 'Meta description',      type: 'textarea', required: false },

  // ── Médias (géré via endpoint dédié, pas dans le payload standard) ─────────
  cover_image:      { api: 'cover_image',      label: 'Image de couverture',   type: 'image',    required: false },
}

/** Valeur initiale du formulaire */
export function makeForm() {
  return {
    title:            '',
    slug:             '',
    excerpt:          '',
    content:          '',
    post_category_id: null,
    status:           'draft',
    published_at:     null,
    meta_title:       '',
    meta_description: '',
    cover_image:      null,
  }
}

/**
 * Construit le payload à envoyer à Laravel depuis le formulaire Vue.
 * cover_image est exclue (gérée via l'endpoint /cover séparé).
 */
export function toPayload(form, statusOverride = null) {
  return {
    title:            form.title,
    slug:             form.slug             || null,
    excerpt:          form.excerpt          || null,
    content:          form.content          || null,
    post_category_id: form.post_category_id || null,
    status:           statusOverride ?? form.status,
    published_at:     form.published_at     || null,
    meta_title:       form.meta_title       || null,
    meta_description: form.meta_description || null,
  }
}

/**
 * Mappe les erreurs Laravel 422 → clés du formulaire Vue.
 * Laravel : { "meta_title": ["Ce champ est requis."] }
 * → Vue   : { "meta_title": "Ce champ est requis." }
 */
export function mapErrors(laravelErrors = {}) {
  const apiToForm = Object.fromEntries(
    Object.entries(FIELDS).map(([k, def]) => [def.api, k])
  )
  return Object.fromEntries(
    Object.entries(laravelErrors).map(([apiKey, msgs]) => [
      apiToForm[apiKey] ?? apiKey,
      Array.isArray(msgs) ? msgs[0] : msgs,
    ])
  )
}
