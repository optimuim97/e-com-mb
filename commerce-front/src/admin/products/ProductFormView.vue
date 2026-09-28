<template>
  <div class="max-w-3xl mx-auto space-y-6">

    <!-- Header -->
    <header class="form-page-header">
      <RouterLink :to="{ name: 'admin.products' }" class="form-page-header__back" aria-label="Retour">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
      </RouterLink>
      <div>
        <span class="eyebrow">Catalogue</span>
        <h1 class="form-page-header__title">
          {{ isEdit ? 'Modifier le produit' : 'Nouveau produit' }}
        </h1>
      </div>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-7 h-7 border-2 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <form v-else @submit.prevent="submit" class="space-y-6">

      <!-- Informations générales -->
      <div class="card p-6 space-y-4">
        <h2 class="font-semibold text-gray-800">Informations générales</h2>

        <FormField :def="FIELDS.name" :error="fieldErrors.name">
          <input v-model="form.name" type="text" class="input" required placeholder="Ex. Élixir de roses pure" />
        </FormField>

        <FormField :def="FIELDS.description" :error="fieldErrors.description" optional>
          <textarea v-model="form.description" class="input min-h-[120px] resize-y" placeholder="Description détaillée du produit…"></textarea>
        </FormField>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField :def="FIELDS.sku" :error="fieldErrors.sku" optional>
            <input v-model="form.sku" type="text" class="input" placeholder="REF-001" />
          </FormField>
          <FormField :def="FIELDS.type" :error="fieldErrors.type">
            <AppSelect v-model="form.type" :options="typeOptions" />
          </FormField>
        </div>

        <FormField :def="FIELDS.category_ids" :error="fieldErrors.category_ids || fieldErrors.category_id" optional>
          <!-- Multi-sélection : un produit peut appartenir à plusieurs catégories -->
          <div class="cat-multi">
            <button
              v-for="opt in flatCategoryOptions"
              :key="opt.value"
              type="button"
              class="cat-pill"
              :class="{ 'cat-pill--on': form.category_ids.includes(opt.value) }"
              @click="toggleCategory(opt.value)"
            >
              <svg v-if="form.category_ids.includes(opt.value)" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
              {{ opt.label }}
            </button>
            <p v-if="!flatCategoryOptions.length" class="cat-multi__empty">Aucune catégorie créée.</p>
          </div>
          <p v-if="form.category_ids.length > 1" class="cat-multi__hint">
            La première sélectionnée devient la catégorie principale.
          </p>
        </FormField>

        <FormField :def="FIELDS.product_line_id" :error="fieldErrors.product_line_id" optional>
          <AppSelect v-model="form.product_line_id" :options="productLineOptions" placeholder="Aucune gamme" />
        </FormField>
      </div>

      <!-- Prix & stock -->
      <div class="card p-6 space-y-4">
        <h2 class="font-semibold text-gray-800">Prix &amp; stock</h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <FormField :def="FIELDS.price" :error="fieldErrors.price">
            <input v-model.number="form.price" type="number" min="0" step="1" class="input" required placeholder="15000" />
          </FormField>
          <FormField :def="FIELDS.compare_price" :error="fieldErrors.compare_price" optional>
            <input v-model.number="form.compare_price" type="number" min="0" step="1" class="input" placeholder="20000" />
          </FormField>
        </div>

        <FormField :def="FIELDS.stock" :error="fieldErrors.stock">
          <input v-model.number="form.stock" type="number" min="0" class="input" required placeholder="100" />
        </FormField>
      </div>

      <!-- Images -->
      <div class="card p-6 space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="font-semibold text-gray-800">Images</h2>
          <span class="text-xs text-gray-400">{{ images.length + pendingFiles.length }} image{{ images.length + pendingFiles.length !== 1 ? 's' : '' }}</span>
        </div>

        <!-- Grille images existantes + en attente -->
        <div
          v-if="images.length || pendingFiles.length"
          class="grid grid-cols-3 sm:grid-cols-4 gap-3"
        >
          <!-- Images sauvegardées -->
          <div
            v-for="img in images"
            :key="img.id"
            class="relative group aspect-square rounded-xl overflow-hidden bg-gray-100"
            :class="img.is_cover ? 'ring-2 ring-primary-500' : 'ring-1 ring-gray-200'"
          >
            <img :src="img.url" :alt="img.alt" class="w-full h-full object-cover" />

            <span
              v-if="img.is_cover"
              class="absolute top-1.5 left-1.5 bg-primary-500 text-white text-[10px] font-semibold px-1.5 py-0.5 rounded"
            >
              Couverture
            </span>

            <!-- Overlay actions -->
            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-1.5 p-2">
              <button
                v-if="!img.is_cover"
                type="button"
                @click.prevent="setCover(img)"
                class="w-full bg-white text-gray-800 rounded-md py-1 text-xs font-semibold hover:bg-primary-50 transition-colors"
              >
                Définir couverture
              </button>
              <button
                type="button"
                @click.prevent="removeImage(img)"
                class="w-full bg-red-500 text-white rounded-md py-1 text-xs font-semibold hover:bg-red-600 transition-colors"
              >
                Supprimer
              </button>
            </div>
          </div>

          <!-- Images en attente d'upload -->
          <div
            v-for="(pf, i) in pendingFiles"
            :key="'pending-' + i"
            class="relative group aspect-square rounded-xl overflow-hidden bg-gray-100 ring-2 ring-dashed ring-yellow-400"
          >
            <img :src="pf.preview" class="w-full h-full object-cover" />

            <span class="absolute top-1.5 left-1.5 bg-yellow-400 text-yellow-900 text-[10px] font-semibold px-1.5 py-0.5 rounded">
              Non enregistré
            </span>

            <button
              type="button"
              @click.prevent="removePending(i)"
              class="absolute top-1.5 right-1.5 w-5 h-5 bg-red-500 text-white rounded-full flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition-opacity"
            >
              &times;
            </button>
          </div>
        </div>

        <!-- Zone de dépôt -->
        <label
          class="flex flex-col items-center justify-center gap-2 border-2 border-dashed rounded-xl p-8 cursor-pointer transition-colors select-none"
          :class="dragOver ? 'border-primary-400 bg-primary-50' : 'border-gray-200 hover:border-primary-300 hover:bg-gray-50'"
          @dragover.prevent="dragOver = true"
          @dragleave.prevent="dragOver = false"
          @drop.prevent="onDrop"
        >
          <input
            ref="fileInput"
            type="file"
            multiple
            accept="image/*"
            class="hidden"
            @change="onFilesSelected"
          />
          <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <span class="text-sm font-medium text-gray-500">Cliquez ou glissez des images ici</span>
          <span class="text-xs text-gray-400">PNG, JPG, WEBP · max 5 Mo par image</span>
        </label>

        <!-- Barre progression upload -->
        <div v-if="uploading" class="flex items-center gap-2 text-sm text-primary-600">
          <span class="w-4 h-4 border-2 border-primary-500 border-t-transparent rounded-full animate-spin inline-block shrink-0"></span>
          Téléversement en cours…
        </div>

        <p v-if="imageError" class="text-red-500 text-sm">{{ imageError }}</p>
      </div>

      <!-- Visibilité -->
      <div class="card p-6 space-y-4">
        <h2 class="font-semibold text-gray-800">Visibilité</h2>
        <div class="flex flex-col gap-3">
          <label class="flex items-center justify-between">
            <span class="text-sm text-gray-700">Produit actif (visible en boutique)</span>
            <button
              type="button"
              @click="form.is_active = !form.is_active"
              class="relative w-11 h-6 rounded-full transition-colors"
              :class="form.is_active ? 'bg-primary-500' : 'bg-gray-200'"
            >
              <span
                class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                :class="form.is_active ? 'translate-x-5' : 'translate-x-0'"
              ></span>
            </button>
          </label>
          <label class="flex items-center justify-between">
            <span class="text-sm text-gray-700">Produit vedette (mis en avant)</span>
            <button
              type="button"
              @click="form.is_featured = !form.is_featured"
              class="relative w-11 h-6 rounded-full transition-colors"
              :class="form.is_featured ? 'bg-primary-500' : 'bg-gray-200'"
            >
              <span
                class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform"
                :class="form.is_featured ? 'translate-x-5' : 'translate-x-0'"
              ></span>
            </button>
          </label>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3 justify-end">
        <RouterLink :to="{ name: 'admin.products' }" class="btn-ghost">Annuler</RouterLink>
        <button
          type="submit"
          :disabled="saving || uploading"
          class="btn-primary px-8 py-2.5 font-semibold disabled:opacity-50"
        >
          <span v-if="saving" class="flex items-center gap-2">
            <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
            Enregistrement…
          </span>
          <span v-else>{{ isEdit ? 'Mettre à jour' : 'Créer le produit' }}</span>
        </button>
      </div>

      <p v-if="submitError" class="text-red-500 text-sm text-center">{{ submitError }}</p>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
// ── Contrat API ← ouvre ces fichiers pour voir/modifier les champs Laravel ──
import { adminProductsApi }              from './products.api'
import { FIELDS, makeForm, toPayload, mapErrors } from './products.fields'
import FormField from '@/shared/components/ui/FormField.vue'
import api from '@/api'

const route  = useRoute()
const router = useRouter()

const isEdit      = computed(() => !!route.params.id)
const loading     = ref(false)
const saving      = ref(false)
const uploading   = ref(false)
const submitError = ref('')
const fieldErrors = ref({})   // erreurs Laravel par champ via mapErrors()
const imageError  = ref('')
const dragOver    = ref(false)
const fileInput   = ref(null)
const categories  = ref([])
const productLines = ref([])
const images      = ref([])       // images sauvegardées (depuis l'API)
const pendingFiles = ref([])      // { file, preview } en attente d'upload

// Formulaire initialisé depuis products.fields.js
const form = reactive(makeForm())

const typeOptions = [
  { value: 'physical', label: 'Physique' },
  { value: 'digital',  label: 'Numérique' },
  { value: 'service',  label: 'Service' },
]

// Aplatit l'arbre catégories → parents puis enfants indentés
function flattenCategories(list, depth = 0) {
  const out = []
  for (const c of list) {
    out.push({ value: c.id, label: (depth ? '— '.repeat(depth) : '') + c.name })
    if (c.children?.length) out.push(...flattenCategories(c.children, depth + 1))
  }
  return out
}

const flatCategoryOptions = computed(() => flattenCategories(categories.value))

function toggleCategory(id) {
  const i = form.category_ids.indexOf(id)
  if (i === -1) form.category_ids.push(id)
  else form.category_ids.splice(i, 1)
}

const productLineOptions = computed(() => [
  { value: '', label: 'Aucune gamme' },
  ...productLines.value.map(l => ({ value: l.id, label: l.name })),
])

// ─── Données ────────────────────────────────────────────────────────────────

async function fetchCategories() {
  try {
    const { data } = await api.get('/admin/categories')
    categories.value = data.data ?? data
  } catch {}
}

async function fetchProductLines() {
  try {
    const { data } = await api.get('/admin/product-lines', { params: { per_page: 100 } })
    productLines.value = data.data ?? data
  } catch {}
}

async function fetchProduct() {
  loading.value = true
  try {
    const { data } = await adminProductsApi.get(route.params.id)
    const p = data.data ?? data
    Object.assign(form, {
      name:            p.name            ?? '',
      description:     p.description     ?? '',
      sku:             p.sku             ?? '',
      type:            p.type            ?? 'physical',
      category_ids:    (p.categories?.length ? p.categories.map(c => c.id) : [p.category?.id].filter(Boolean)),
      product_line_id: p.product_line?.id ?? '',
      price:           p.price           ?? '',
      compare_price:   p.compare_price   ?? '',
      stock:           p.stock           ?? 0,
      is_active:       p.is_active       ?? true,
      is_featured:     p.is_featured     ?? false,
    })
    images.value = p.images ?? []
  } catch {
    submitError.value = 'Produit introuvable.'
  } finally {
    loading.value = false
  }
}

// ─── Gestion images ──────────────────────────────────────────────────────────

function addFiles(files) {
  imageError.value = ''
  for (const file of Array.from(files)) {
    if (!file.type.startsWith('image/')) continue
    if (file.size > 5 * 1024 * 1024) {
      imageError.value = `"${file.name}" dépasse 5 Mo.`
      continue
    }
    pendingFiles.value.push({ file, preview: URL.createObjectURL(file) })
  }
  // En mode édition : upload immédiat
  if (isEdit.value && pendingFiles.value.length) {
    uploadPendingImages(route.params.id)
  }
}

function onFilesSelected(e) {
  addFiles(e.target.files)
  if (fileInput.value) fileInput.value.value = ''
}

function onDrop(e) {
  dragOver.value = false
  addFiles(e.dataTransfer.files)
}

function removePending(i) {
  URL.revokeObjectURL(pendingFiles.value[i].preview)
  pendingFiles.value.splice(i, 1)
}

async function uploadPendingImages(productId) {
  if (!pendingFiles.value.length) return
  uploading.value  = true
  imageError.value = ''
  const toUpload   = pendingFiles.value.splice(0)   // vide la liste

  for (const pf of toUpload) {
    try {
      const fd = new FormData()
      fd.append('image', pf.file)
      const { data } = await adminProductsApi.addImage(productId, fd)
      images.value.push(data)
    } catch {
      imageError.value = 'Erreur lors du téléversement d\'une image.'
    } finally {
      URL.revokeObjectURL(pf.preview)
    }
  }
  uploading.value = false
}

async function removeImage(img) {
  imageError.value = ''
  try {
    await adminProductsApi.removeImage(route.params.id, img.id)
    images.value = images.value.filter(i => i.id !== img.id)
    if (img.is_cover && images.value.length) images.value[0].is_cover = true
  } catch {
    imageError.value = 'Impossible de supprimer cette image.'
  }
}

async function setCover(img) {
  imageError.value = ''
  try {
    await adminProductsApi.setCover(route.params.id, img.id)
    images.value.forEach(i => { i.is_cover = (i.id === img.id) })
  } catch {
    imageError.value = "Impossible de définir l'image de couverture."
  }
}

// ─── Soumission ──────────────────────────────────────────────────────────────

async function submit() {
  saving.value      = true
  submitError.value = ''
  fieldErrors.value = {}
  try {
    // toPayload() construit le body Laravel depuis le formulaire Vue (voir products.fields.js)
    const payload = toPayload(form)

    if (isEdit.value) {
      await adminProductsApi.update(route.params.id, payload)
    } else {
      const { data } = await adminProductsApi.create(payload)
      const productId = (data.data ?? data).id
      await uploadPendingImages(productId)
    }

    router.push({ name: 'admin.products' })
  } catch (e) {
    // Erreurs Laravel 422 → chaque champ affiche sa propre erreur (voir FormField)
    fieldErrors.value = mapErrors(e.response?.data?.errors ?? {})
    submitError.value = e.response?.data?.message ?? 'Une erreur est survenue.'
  } finally {
    saving.value = false
  }
}

// ─── Init ────────────────────────────────────────────────────────────────────

onMounted(async () => {
  await Promise.all([fetchCategories(), fetchProductLines()])
  if (isEdit.value) await fetchProduct()
})
</script>

<style scoped>
.form-page-header {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}
.form-page-header__back {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #fff;
  border: 1.5px solid var(--cream-300);
  color: var(--gray-600);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
}
.form-page-header__back:hover {
  border-color: var(--rose-300);
  color: var(--rose-500);
}
.form-page-header__title {
  font-family: var(--font-display);
  font-size: 1.625rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-top: 4px;
}

/* ── Multi-sélection catégories ── */
.cat-multi {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.cat-pill {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  padding: 6px 13px;
  border-radius: 999px;
  border: 1.5px solid var(--cream-300);
  background: var(--cream-50);
  font-size: 0.8125rem;
  color: var(--gray-600);
  cursor: pointer;
  transition: all var(--transition-fast);
}
.cat-pill:hover { border-color: var(--rose-300); color: var(--rose-600); }
.cat-pill--on {
  background: var(--rose-50);
  border-color: var(--rose-400);
  color: var(--rose-700);
  font-weight: 600;
}
.cat-multi__empty { font-size: 0.8125rem; color: var(--gray-400); }
.cat-multi__hint {
  margin-top: 6px;
  font-size: 0.75rem;
  color: var(--gray-400);
  font-style: italic;
}
</style>
