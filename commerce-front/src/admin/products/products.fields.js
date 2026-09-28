/**
 * products.fields.js  (admin)
 * Contrat entre le formulaire Vue et la FormRequest Laravel StoreProductRequest / UpdateProductRequest.
 *
 * - Clé    : nom du champ dans le formulaire Vue (form.xxx)
 * - api    : nom exact attendu par Laravel dans le body
 * - label  : affiché dans <FormField :def="FIELDS.xxx">
 * - type   : type de champ (text, number, select, textarea, toggle)
 * - required : validation Laravel (required / nullable)
 */

export const FIELDS = {
  // ── Général ────────────────────────────────────────────────────────────────
  name:            { api: 'name',            label: 'Nom du produit',     type: 'text',     required: true  },
  description:     { api: 'description',     label: 'Description',        type: 'textarea', required: false },
  sku:             { api: 'sku',             label: 'SKU',                type: 'text',     required: false },
  type:            { api: 'type',            label: 'Type',               type: 'select',   required: true  },
  category_ids:    { api: 'category_ids',    label: 'Catégories',         type: 'multi',    required: false },
  product_line_id: { api: 'product_line_id', label: 'Gamme de produits',  type: 'select',   required: false },

  // ── Prix & stock ────────────────────────────────────────────────────────────
  price:           { api: 'price',           label: 'Prix (FCFA)',        type: 'number',   required: true  },
  compare_price:   { api: 'compare_price',   label: 'Prix barré (FCFA)', type: 'number',   required: false },
  stock:           { api: 'stock',           label: 'Stock',             type: 'number',   required: true  },

  // ── Visibilité ──────────────────────────────────────────────────────────────
  is_active:       { api: 'is_active',       label: 'Produit actif',     type: 'toggle',   required: false },
  is_featured:     { api: 'is_featured',     label: 'Produit vedette',   type: 'toggle',   required: false },
}

/** Valeur initiale du formulaire */
export function makeForm() {
  return {
    name: '', description: '', sku: '', type: 'physical',
    category_ids: [], product_line_id: '',
    price: '', compare_price: '', stock: 0,
    is_active: true, is_featured: false,
  }
}

/**
 * Construit le payload à envoyer à Laravel depuis le formulaire Vue.
 * Nettoie les valeurs vides → null pour les champs nullable.
 */
export function toPayload(form) {
  return {
    name:            form.name,
    description:     form.description     || null,
    sku:             form.sku             || null,
    type:            form.type,
    // Multi-catégories : le serveur synchronise le pivot et prend la
    // première comme catégorie principale (category_id).
    category_ids:    form.category_ids ?? [],
    category_id:     (form.category_ids ?? [])[0] ?? null,
    product_line_id: form.product_line_id || null,
    price:           form.price,
    compare_price:   form.compare_price   || null,
    stock:           form.stock,
    is_active:       form.is_active,
    is_featured:     form.is_featured,
  }
}

/**
 * Mappe les erreurs Laravel 422 → clés du formulaire Vue.
 * Laravel : { "category_id": ["Ce champ est requis."] }
 * → Vue   : { "category_id": "Ce champ est requis." }
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
