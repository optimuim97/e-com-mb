<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <RouterLink to="/admin/product-lines" class="admin-back-link">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M19 12H5M12 5l-7 7 7 7"/></svg>
          Gammes
        </RouterLink>
        <h1 class="page-header__title">{{ isEdit ? 'Modifier la gamme' : 'Nouvelle gamme' }}</h1>
      </div>
      <div class="header-actions">
        <RouterLink to="/admin/product-lines" class="btn btn-outline btn-sm">Annuler</RouterLink>
        <button @click="save" :disabled="saving" class="btn btn-primary btn-sm">
          {{ saving ? 'Enregistrement…' : (isEdit ? 'Enregistrer' : 'Créer la gamme') }}
        </button>
      </div>
    </header>

    <div v-if="loadingLine" class="loader-wrap"><div class="loader"></div></div>

    <div v-else class="form-layout">
      <!-- Colonne principale -->
      <div class="form-main">

        <!-- Identité -->
        <div class="card form-card">
          <h2 class="form-card__title">Identité</h2>

          <div class="form-field">
            <label class="label">Nom de la gamme <span class="required">*</span></label>
            <input v-model="form.name" @input="autoSlug" type="text" class="input" placeholder="Ex. Gamme Rose Pure" />
          </div>

          <div class="form-field">
            <label class="label">Slug URL</label>
            <div class="slug-wrap">
              <span class="slug-prefix">/gammes/</span>
              <input v-model="form.slug" type="text" class="input slug-input" placeholder="rose-pure" :disabled="isEdit" />
            </div>
            <p class="field-hint">Généré automatiquement à la création.</p>
          </div>

          <div class="form-field">
            <label class="label">Accroche courte</label>
            <input v-model="form.tagline" type="text" class="input" placeholder="Ex. La pureté de la rose à l'état pur" maxlength="255" />
            <p class="field-hint">Affiché sous le nom sur le site vitrine.</p>
          </div>

          <div class="form-field">
            <label class="label">Description</label>
            <textarea v-model="form.description" class="input textarea" rows="4"
              placeholder="Décrivez cette gamme, ses valeurs, ses ingrédients phares…"></textarea>
          </div>
        </div>

        <!-- Couleur -->
        <div class="card form-card">
          <h2 class="form-card__title">Identité visuelle</h2>

          <div class="form-field">
            <label class="label">Couleur accent</label>
            <div class="color-field">
              <input v-model="form.color_hex" type="color" class="color-picker" />
              <input v-model="form.color_hex" type="text" class="input color-text" placeholder="#E8336D" maxlength="7" />
              <div class="color-preview" :style="{ background: form.color_hex }"></div>
            </div>
            <p class="field-hint">Utilisée pour les badges et éléments de mise en avant sur le site.</p>
          </div>

          <!-- Prévisualisation -->
          <div class="line-preview" :style="{ borderColor: form.color_hex + '55', background: form.color_hex + '0d' }">
            <div class="line-preview__dot" :style="{ background: form.color_hex }"></div>
            <div>
              <p class="line-preview__name" :style="{ color: form.color_hex }">{{ form.name || 'Nom de la gamme' }}</p>
              <p class="line-preview__tag">{{ form.tagline || 'Accroche de la gamme…' }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Colonne droite -->
      <div class="form-side">

        <!-- Statut -->
        <div class="card form-card">
          <h2 class="form-card__title">Publication</h2>
          <label class="toggle-label">
            <span>Gamme active</span>
            <div class="toggle" :class="{ 'toggle--on': form.is_active }" @click="form.is_active = !form.is_active">
              <div class="toggle__knob"></div>
            </div>
          </label>
          <p class="field-hint mt-1">Une gamme inactive est masquée sur le site.</p>

          <div class="form-field mt-4">
            <label class="label">Ordre d'affichage</label>
            <input v-model.number="form.sort_order" type="number" class="input" min="0" placeholder="0" />
            <p class="field-hint">Plus le chiffre est bas, plus la gamme apparaît en premier.</p>
          </div>
        </div>

        <!-- Cover image -->
        <div class="card form-card">
          <h2 class="form-card__title">Image de couverture</h2>

          <div
            class="cover-drop"
            :class="{ 'cover-drop--active': isDragging }"
            @dragover.prevent="isDragging = true"
            @dragleave="isDragging = false"
            @drop.prevent="onDrop"
            @click="$refs.fileInput.click()"
          >
            <img v-if="coverPreview" :src="coverPreview" class="cover-drop__preview" alt="Aperçu" />
            <div v-else class="cover-drop__placeholder">
              <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
              <p>Glisser une image ou <span class="link">parcourir</span></p>
              <p class="cover-drop__hint">JPG, PNG — max 5 Mo</p>
            </div>
          </div>
          <input ref="fileInput" type="file" accept="image/*" class="sr-only" @change="onFileChange" />

          <button v-if="coverPreview && isEdit" @click="uploadCover" :disabled="uploadingCover" class="btn btn-outline btn-sm w-full mt-3">
            {{ uploadingCover ? 'Upload…' : 'Enregistrer l\'image' }}
          </button>
        </div>

        <!-- Erreur -->
        <div v-if="error" class="alert alert-danger">{{ error }}</div>
      </div>
    </div>

    <!-- ── Composition (uniquement en mode édition) ── -->
    <div v-if="isEdit" class="card form-card composition-card">
      <div class="composition-header">
        <h2 class="form-card__title" style="border:none; padding:0;">Composition de la gamme</h2>
        <span class="composition-count">{{ assignedProducts.length }} produit{{ assignedProducts.length !== 1 ? 's' : '' }}</span>
      </div>
      <p class="field-hint" style="margin-bottom: var(--space-4);">
        Choisissez les produits qui font partie de cette gamme. La modification s'applique immédiatement après sauvegarde.
      </p>

      <!-- Barre de recherche -->
      <div class="comp-search-wrap">
        <svg class="comp-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input
          v-model="productSearch"
          type="text"
          class="input comp-search"
          placeholder="Rechercher un produit à ajouter…"
          @input="searchProducts"
        />
      </div>

      <!-- Résultats de recherche -->
      <Transition name="comp-drop">
        <div v-if="searchResults.length && productSearch" class="comp-results">
          <button
            v-for="p in searchResults"
            :key="p.id"
            class="comp-result-item"
            :class="{ 'comp-result-item--assigned': isAssigned(p.id) }"
            @click="toggleProduct(p)"
          >
            <span class="comp-result-name">{{ p.name }}</span>
            <span class="comp-result-sku">{{ p.sku }}</span>
            <span class="comp-result-action">
              {{ isAssigned(p.id) ? '✓ Retiré au clic' : '+ Ajouter' }}
            </span>
          </button>
        </div>
      </Transition>

      <!-- Produits assignés -->
      <div v-if="assignedProducts.length" class="comp-assigned">
        <TransitionGroup name="comp-item" tag="div" class="comp-assigned-list">
          <div
            v-for="p in assignedProducts"
            :key="p.id"
            class="comp-chip"
          >
            <span class="comp-chip__dot"></span>
            <RouterLink :to="`/admin/products/${p.id}/edit`" class="comp-chip__name" target="_blank">
              {{ p.name }}
            </RouterLink>
            <span v-if="p.sku" class="comp-chip__sku">{{ p.sku }}</span>
            <button class="comp-chip__remove" @click="removeProduct(p)" title="Retirer de la gamme">×</button>
          </div>
        </TransitionGroup>
      </div>

      <div v-else class="comp-empty">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/><path d="M16 7V5a2 2 0 0 0-4 0v2"/></svg>
        <p>Aucun produit dans cette gamme. Utilisez la recherche ci-dessus pour en ajouter.</p>
      </div>

      <!-- Bouton de sauvegarde composition -->
      <div class="comp-footer">
        <button
          class="btn btn-primary btn-sm"
          @click="saveComposition"
          :disabled="savingComposition || !compositionDirty"
        >
          {{ savingComposition ? 'Enregistrement…' : 'Sauvegarder la composition' }}
        </button>
        <span v-if="compositionSaved" class="comp-saved-msg">✓ Composition enregistrée</span>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '@/api'

// ── Composition ──────────────────────────────────────────────────────────────
const assignedProducts  = ref([])
const productSearch     = ref('')
const searchResults     = ref([])
const savingComposition = ref(false)
const compositionSaved  = ref(false)
const compositionDirty  = ref(false)
let   searchTimer       = null

async function loadAssignedProducts() {
  if (!route.params.id) return
  try {
    const { data } = await api.get(`/admin/product-lines/${route.params.id}/products`)
    assignedProducts.value = data
  } catch { /* silencieux */ }
}

function isAssigned(id) {
  return assignedProducts.value.some(p => p.id === id)
}

function searchProducts() {
  clearTimeout(searchTimer)
  if (!productSearch.value.trim()) { searchResults.value = []; return }
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/admin/products', {
        params: { search: productSearch.value, per_page: 10, page: 1 }
      })
      searchResults.value = data.data ?? data
    } catch { searchResults.value = [] }
  }, 300)
}

function toggleProduct(product) {
  if (isAssigned(product.id)) {
    removeProduct(product)
  } else {
    assignedProducts.value.push(product)
    compositionDirty.value = true
  }
}

function removeProduct(product) {
  assignedProducts.value = assignedProducts.value.filter(p => p.id !== product.id)
  compositionDirty.value = true
}

async function saveComposition() {
  if (!route.params.id) return
  savingComposition.value = true
  compositionSaved.value  = false
  try {
    await api.put(`/admin/product-lines/${route.params.id}/products`, {
      product_ids: assignedProducts.value.map(p => p.id),
    })
    compositionDirty.value = false
    compositionSaved.value = true
    setTimeout(() => { compositionSaved.value = false }, 3000)
  } catch { /* gérer erreur */ }
  finally { savingComposition.value = false }
}

const route  = useRoute()
const router = useRouter()

const isEdit     = computed(() => !!route.params.id)
const saving     = ref(false)
const loadingLine = ref(false)
const error      = ref('')
const isDragging = ref(false)
const fileInput  = ref(null)
const coverFile  = ref(null)
const coverPreview = ref(null)
const uploadingCover = ref(false)

const form = reactive({
  name:        '',
  slug:        '',
  tagline:     '',
  description: '',
  color_hex:   '#E8336D',
  is_active:   true,
  sort_order:  0,
})

function autoSlug() {
  if (isEdit.value) return
  form.slug = form.name
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')
}

function onFileChange(e) {
  const file = e.target.files[0]
  if (!file) return
  coverFile.value = file
  coverPreview.value = URL.createObjectURL(file)
}

function onDrop(e) {
  isDragging.value = false
  const file = e.dataTransfer.files[0]
  if (!file || !file.type.startsWith('image/')) return
  coverFile.value = file
  coverPreview.value = URL.createObjectURL(file)
}

async function uploadCover() {
  if (!coverFile.value || !route.params.id) return
  uploadingCover.value = true
  const fd = new FormData()
  fd.append('cover_image', coverFile.value)
  try {
    await api.post(`/admin/product-lines/${route.params.id}/cover`, fd)
    coverFile.value = null
  } finally {
    uploadingCover.value = false
  }
}

async function save() {
  if (!form.name) { error.value = 'Le nom est requis.'; return }
  saving.value = true
  error.value  = ''
  try {
    let res
    if (isEdit.value) {
      res = await api.patch(`/admin/product-lines/${route.params.id}`, form)
    } else {
      res = await api.post('/admin/product-lines', form)
      // Upload cover après création
      if (coverFile.value && res.data.id) {
        const fd = new FormData()
        fd.append('cover_image', coverFile.value)
        await api.post(`/admin/product-lines/${res.data.id}/cover`, fd)
      }
    }
    router.push('/admin/product-lines')
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erreur lors de l\'enregistrement.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!isEdit.value) return
  loadingLine.value = true
  try {
    const [lineRes] = await Promise.all([
      api.get(`/admin/product-lines/${route.params.id}`),
      loadAssignedProducts(),
    ])
    const line = lineRes.data.data ?? lineRes.data
    Object.assign(form, {
      name:        line.name,
      slug:        line.slug,
      tagline:     line.tagline ?? '',
      description: line.description ?? '',
      color_hex:   line.color_hex ?? '#E8336D',
      is_active:   line.is_active,
      sort_order:  line.sort_order ?? 0,
    })
    if (line.cover_url) coverPreview.value = line.cover_url
  } finally {
    loadingLine.value = false
  }
})
</script>

<style scoped>
.form-layout {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: var(--space-6);
  align-items: start;
}
@media (max-width: 900px) { .form-layout { grid-template-columns: 1fr; } }

.form-card { padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-4); }
.form-card__title { font-size: 0.9375rem; font-weight: 600; color: var(--gray-700); padding-bottom: var(--space-3); border-bottom: 1px solid var(--cream-200); }

.form-main { display: flex; flex-direction: column; gap: var(--space-5); }
.form-side { display: flex; flex-direction: column; gap: var(--space-5); }

.form-field { display: flex; flex-direction: column; gap: 6px; }
.required { color: var(--rose-500); }
.field-hint { font-size: 0.75rem; color: var(--gray-400); }

.slug-wrap { display: flex; align-items: center; gap: 0; }
.slug-prefix {
  padding: 0 10px;
  height: 40px;
  display: flex;
  align-items: center;
  background: var(--cream-50);
  border: 1.5px solid var(--cream-300);
  border-right: none;
  border-radius: var(--radius-md) 0 0 var(--radius-md);
  font-size: 0.8125rem;
  color: var(--gray-400);
  white-space: nowrap;
}
.slug-input { border-radius: 0 var(--radius-md) var(--radius-md) 0; }

.textarea { resize: vertical; min-height: 100px; font-family: inherit; }

/* Color picker */
.color-field { display: flex; align-items: center; gap: var(--space-3); }
.color-picker { width: 44px; height: 44px; padding: 2px; border: 1.5px solid var(--cream-300); border-radius: var(--radius-md); cursor: pointer; background: none; }
.color-text { flex: 1; font-family: monospace; }
.color-preview { width: 32px; height: 32px; border-radius: 50%; flex-shrink: 0; border: 2px solid rgba(0,0,0,0.08); }

/* Line preview */
.line-preview {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  border: 1.5px solid;
}
.line-preview__dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.line-preview__name { font-weight: 600; font-size: 0.9375rem; }
.line-preview__tag { font-size: 0.8125rem; color: var(--gray-500); margin-top: 2px; }

/* Toggle */
.toggle-label { display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; font-weight: 500; color: var(--gray-700); cursor: pointer; }
.toggle { width: 44px; height: 24px; border-radius: 12px; background: var(--cream-300); position: relative; transition: background var(--transition-fast); cursor: pointer; }
.toggle--on { background: var(--rose-400); }
.toggle__knob { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: var(--shadow-sm); transition: transform var(--transition-fast); }
.toggle--on .toggle__knob { transform: translateX(20px); }

/* Cover drop */
.cover-drop {
  border: 2px dashed var(--cream-300);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all var(--transition-fast);
  overflow: hidden;
  min-height: 140px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.cover-drop:hover, .cover-drop--active { border-color: var(--rose-300); background: var(--rose-50); }
.cover-drop__preview { width: 100%; height: 160px; object-fit: cover; display: block; }
.cover-drop__placeholder { display: flex; flex-direction: column; align-items: center; gap: var(--space-2); padding: var(--space-5); color: var(--gray-400); text-align: center; }
.cover-drop__placeholder p { font-size: 0.8125rem; }
.cover-drop__hint { font-size: 0.7rem !important; color: var(--gray-300); }
.link { color: var(--rose-500); text-decoration: underline; }
.sr-only { display: none; }

.header-actions { display: flex; gap: var(--space-3); }
.admin-back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 0.8125rem; color: var(--gray-400); margin-bottom: 4px; }
.admin-back-link:hover { color: var(--rose-500); }

.mt-1 { margin-top: 4px; }
.mt-4 { margin-top: var(--space-4); }
.w-full { width: 100%; }

.alert-danger { background: var(--rose-50); border: 1px solid var(--rose-200); color: var(--rose-700); padding: var(--space-3) var(--space-4); border-radius: var(--radius-md); font-size: 0.875rem; }

/* ── Composition ── */
.composition-card {
  margin-top: var(--space-6);
  grid-column: 1 / -1;
}

.composition-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--cream-200);
  margin-bottom: var(--space-4);
}

.composition-count {
  font-size: 0.75rem;
  font-weight: 600;
  background: var(--rose-100);
  color: var(--rose-600);
  padding: 3px 10px;
  border-radius: var(--radius-full);
}

/* Search */
.comp-search-wrap {
  position: relative;
  margin-bottom: var(--space-3);
}
.comp-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--gray-400);
  pointer-events: none;
}
.comp-search { padding-left: 36px; }

/* Search results dropdown */
.comp-results {
  background: #fff;
  border: 1.5px solid var(--cream-200);
  border-radius: var(--radius-lg);
  overflow: hidden;
  margin-bottom: var(--space-3);
  box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.comp-result-item {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 10px 14px;
  width: 100%;
  text-align: left;
  border-bottom: 1px solid var(--cream-50);
  transition: background var(--transition-fast);
  font-size: 0.875rem;
}
.comp-result-item:last-child { border-bottom: none; }
.comp-result-item:hover { background: var(--rose-50); }
.comp-result-item--assigned { opacity: 0.6; }
.comp-result-name { flex: 1; font-weight: 500; color: var(--gray-800); }
.comp-result-sku  { font-size: 0.75rem; color: var(--gray-400); }
.comp-result-action { font-size: 0.75rem; font-weight: 600; color: var(--rose-500); margin-left: auto; }

/* Assigned chips */
.comp-assigned { margin-bottom: var(--space-4); }
.comp-assigned-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
.comp-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px 6px 8px;
  background: var(--cream-50);
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-full);
  font-size: 0.8125rem;
  transition: border-color var(--transition-fast);
}
.comp-chip:hover { border-color: var(--rose-300); }
.comp-chip__dot {
  width: 6px; height: 6px;
  border-radius: 50%;
  background: var(--rose-400);
  flex-shrink: 0;
}
.comp-chip__name {
  font-weight: 500;
  color: var(--gray-700);
  text-decoration: none;
}
.comp-chip__name:hover { color: var(--rose-500); text-decoration: underline; }
.comp-chip__sku { font-size: 0.7rem; color: var(--gray-400); }
.comp-chip__remove {
  width: 18px; height: 18px;
  border-radius: 50%;
  background: var(--cream-200);
  color: var(--gray-500);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.875rem;
  line-height: 1;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}
.comp-chip__remove:hover { background: #fecaca; color: #ef4444; }

/* Empty */
.comp-empty {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-6);
  background: var(--cream-50);
  border-radius: var(--radius-lg);
  color: var(--gray-400);
  font-size: 0.875rem;
  margin-bottom: var(--space-4);
}

/* Footer */
.comp-footer {
  display: flex;
  align-items: center;
  gap: var(--space-4);
  padding-top: var(--space-4);
  border-top: 1px solid var(--cream-200);
}
.comp-saved-msg {
  font-size: 0.8125rem;
  font-weight: 500;
  color: #16a34a;
}

/* Transitions */
.comp-drop-enter-active,
.comp-drop-leave-active { transition: all 0.2s ease; overflow: hidden; }
.comp-drop-enter-from,
.comp-drop-leave-to { opacity: 0; transform: translateY(-4px); }

.comp-item-enter-active { transition: all 0.2s ease; }
.comp-item-leave-active { transition: all 0.15s ease; }
.comp-item-enter-from  { opacity: 0; transform: scale(0.85); }
.comp-item-leave-to    { opacity: 0; transform: scale(0.85); }
</style>
