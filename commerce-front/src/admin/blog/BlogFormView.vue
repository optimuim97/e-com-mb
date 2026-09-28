<template>
  <div class="blog-form-layout">

    <!-- ── Left: editor ── -->
    <div class="blog-form-main space-y-5">

      <!-- Title -->
      <div class="card p-5 space-y-4">
        <FormField :def="FIELDS.title" :error="fieldErrors.title">
          <input v-model="form.title" @input="autoSlug" type="text" class="input text-lg font-medium"
            placeholder="Mon article beauté…" />
        </FormField>

        <!-- Slug — UI spéciale avec préfixe, FormField gère label + erreur -->
        <FormField :def="FIELDS.slug" :error="fieldErrors.slug" optional>
          <div class="slug-wrap">
            <span class="slug-prefix">/blog/</span>
            <input v-model="form.slug" @input="onSlugInput" type="text" class="slug-input" placeholder="mon-article-beaute" />
          </div>
        </FormField>

        <!-- Excerpt -->
        <FormField :def="FIELDS.excerpt" :error="fieldErrors.excerpt" optional>
          <textarea v-model="form.excerpt" rows="2" class="input resize-none"
            placeholder="Courte description de l'article…" maxlength="500"></textarea>
          <p class="text-xs text-gray-400 mt-1">{{ form.excerpt?.length ?? 0 }} / 500</p>
        </FormField>
      </div>

      <!-- WYSIWYG -->
      <div class="card p-5">
        <label class="label mb-3">Contenu</label>
        <TiptapEditor v-model="form.content" />
      </div>

      <!-- SEO -->
      <div class="card p-5 space-y-4">
        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="text-rose-400">
            <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35" stroke-linecap="round"/>
          </svg>
          SEO
        </h3>
        <!-- Preview -->
        <div class="seo-preview">
          <p class="seo-preview__title">{{ form.meta_title || form.title || 'Titre de l\'article' }}</p>
          <p class="seo-preview__url">{{ siteHost }}/blog/{{ form.slug || 'slug-article' }}</p>
          <p class="seo-preview__desc">{{ form.meta_description || form.excerpt || 'Description de l\'article…' }}</p>
        </div>
        <FormField :def="FIELDS.meta_title" :error="fieldErrors.meta_title" optional>
          <input v-model="form.meta_title" type="text" class="input" maxlength="70" placeholder="Titre pour les moteurs de recherche" />
          <p class="text-xs text-gray-400">{{ form.meta_title?.length ?? 0 }}/70</p>
        </FormField>
        <FormField :def="FIELDS.meta_description" :error="fieldErrors.meta_description" optional>
          <textarea v-model="form.meta_description" rows="2" class="input resize-none" maxlength="160"
            placeholder="Description pour les moteurs de recherche…"></textarea>
          <p class="text-xs text-gray-400">{{ form.meta_description?.length ?? 0 }}/160</p>
        </FormField>
      </div>
    </div>

    <!-- ── Right: sidebar ── -->
    <aside class="blog-form-sidebar space-y-5">

      <!-- Publish -->
      <div class="card p-5 space-y-4">
        <h3 class="font-semibold text-gray-800">Publication</h3>

        <FormField :def="FIELDS.status" :error="fieldErrors.status">
          <AppSelect v-model="form.status" :options="statusOptions" />
        </FormField>

        <FormField v-if="form.status === 'published'" :def="FIELDS.published_at" :error="fieldErrors.published_at" optional>
          <input v-model="form.published_at" type="datetime-local" class="input" />
        </FormField>

        <div class="flex gap-2">
          <button @click="save('draft')" :disabled="saving" class="btn btn-ghost btn-sm flex-1">
            Brouillon
          </button>
          <button @click="save('published')" :disabled="saving" class="btn btn-primary btn-sm flex-1">
            <span v-if="saving" class="inline-flex items-center gap-1">
              <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10" stroke="white" stroke-width="4" opacity=".25"/>
                <path fill="white" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              Enregistrement…
            </span>
            <span v-else>{{ isEdit ? 'Mettre à jour' : 'Publier' }}</span>
          </button>
        </div>

        <p v-if="saveError" class="text-red-500 text-xs">{{ saveError }}</p>
        <p v-if="saveSuccess" class="text-green-600 text-xs">✓ Article sauvegardé</p>

        <RouterLink v-if="isEdit" :to="{ name: 'admin.blog' }" class="text-xs text-gray-400 hover:text-rose-500 transition-colors block text-center">
          ← Retour à la liste
        </RouterLink>
      </div>

      <!-- Category -->
      <div class="card p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">Catégorie</h3>
          <button @click="showCatForm = !showCatForm" class="text-xs text-rose-500 hover:text-rose-600">
            + Nouvelle
          </button>
        </div>

        <!-- New category inline -->
        <div v-if="showCatForm" class="new-cat-form">
          <input v-model="newCatName" type="text" class="input" placeholder="Nom de la catégorie" />
          <button @click="createCategory" :disabled="!newCatName.trim()" class="btn btn-primary btn-sm w-full mt-2">
            Créer
          </button>
        </div>

        <AppSelect v-model="form.post_category_id" :options="categoryOptions" placeholder="— Aucune catégorie —" />
      </div>

      <!-- Cover image -->
      <div class="card p-5 space-y-3">
        <h3 class="font-semibold text-gray-800">Image de couverture</h3>

        <div v-if="form.cover_image" class="cover-preview">
          <img :src="form.cover_image" alt="Couverture" />
          <button @click="form.cover_image = null" class="cover-remove">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
              <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <label class="cover-upload-zone" :class="{ dragging: isDragging }"
          @dragover.prevent="isDragging = true" @dragleave="isDragging = false"
          @drop.prevent="onDrop">
          <input type="file" accept="image/*" class="sr-only" @change="onFileChange" ref="fileInput" />
          <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" class="text-gray-300 mb-2">
            <path stroke-linecap="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14M14 8h.01"/>
            <rect x="3" y="3" width="18" height="18" rx="2"/>
          </svg>
          <span class="text-xs text-gray-400">Cliquez ou déposez une image</span>
          <span class="text-xs text-gray-300">JPG, PNG, WebP · max 5 Mo</span>
        </label>

        <div v-if="uploadingCover" class="text-xs text-gray-400 text-center">Téléchargement…</div>
      </div>
    </aside>
  </div>
</template>

<script setup>

const siteHost = window.location.host
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import TiptapEditor from '@/admin/blog/TiptapEditor.vue'
import AppSelect    from '@/components/ui/AppSelect.vue'
import FormField    from '@/shared/components/ui/FormField.vue'
import { blogApi }  from './blog.api'
import { FIELDS, makeForm, toPayload, mapErrors } from './blog.fields'

const route  = useRoute()
const router = useRouter()

const isEdit = computed(() => !!route.params.id)

const form = reactive(makeForm())
const fieldErrors = ref({})

const saving        = ref(false)
const saveSuccess   = ref(false)
const saveError     = ref('')
const categories    = ref([])
const showCatForm   = ref(false)
const newCatName    = ref('')
const isDragging    = ref(false)
const uploadingCover= ref(false)
const fileInput     = ref(null)
let   postId        = ref(null)

const statusOptions = [
  { value: 'draft',     label: 'Brouillon' },
  { value: 'published', label: 'Publié' },
]

const categoryOptions = computed(() =>
  categories.value.map(c => ({ value: c.id, label: c.name }))
)

/* ── Slug auto-generation ── */
let slugManual = false
function autoSlug() {
  if (slugManual || isEdit.value) return
  form.slug = form.title
    .toLowerCase()
    .normalize('NFD').replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9\s-]/g, '')
    .trim().replace(/\s+/g, '-')
}
function onSlugInput() { slugManual = true }

/* ── Fetch data ── */
async function fetchCategories() {
  const { data } = await blogApi.listCategories()
  categories.value = data.data ?? data
}

async function fetchPost() {
  if (!isEdit.value) return
  const { data } = await blogApi.get(route.params.id)
  const p = data.data ?? data
  Object.assign(form, {
    title:            p.title,
    slug:             p.slug,
    excerpt:          p.excerpt ?? '',
    content:          p.content ?? '',
    post_category_id: p.category?.id ?? null,
    status:           p.status,
    published_at:     p.published_at ? p.published_at.slice(0, 16) : null,
    meta_title:       p.meta_title ?? '',
    meta_description: p.meta_description ?? '',
    cover_image:      p.cover_image ?? null,
  })
  postId.value = p.id
  slugManual = true
}

/* ── Save ── */
async function save(statusOverride) {
  saving.value = true
  saveError.value = ''
  saveSuccess.value = false
  fieldErrors.value = {}
  try {
    const payload = toPayload(form, statusOverride)

    let savedPost
    if (isEdit.value) {
      const { data } = await blogApi.update(route.params.id, payload)
      savedPost = data.data ?? data
    } else {
      const { data } = await blogApi.create(payload)
      savedPost = data.data ?? data
      postId.value = savedPost.id
      // Upload cover if pending
      if (pendingCoverFile.value) await uploadCover(savedPost.id, pendingCoverFile.value)
      router.replace({ name: 'admin.blog.edit', params: { id: savedPost.id } })
    }
    form.status = savedPost.status
    saveSuccess.value = true
    setTimeout(() => { saveSuccess.value = false }, 3000)
  } catch (e) {
    if (e.response?.status === 422) {
      fieldErrors.value = mapErrors(e.response.data?.errors ?? {})
    }
    saveError.value = e.response?.data?.message ?? 'Erreur lors de la sauvegarde.'
  } finally {
    saving.value = false
  }
}

/* ── Category creation ── */
async function createCategory() {
  if (!newCatName.value.trim()) return
  const { data } = await blogApi.createCategory({ name: newCatName.value })
  const cat = data.data ?? data
  categories.value.push(cat)
  form.post_category_id = cat.id
  newCatName.value = ''
  showCatForm.value = false
}

/* ── Cover image ── */
const pendingCoverFile = ref(null)

function onFileChange(e) { handleFile(e.target.files[0]) }
function onDrop(e) { isDragging.value = false; handleFile(e.dataTransfer.files[0]) }

async function handleFile(file) {
  if (!file) return
  if (isEdit.value || postId.value) {
    await uploadCover(postId.value ?? route.params.id, file)
  } else {
    // Store preview and upload after first save
    pendingCoverFile.value = file
    form.cover_image = URL.createObjectURL(file)
  }
}

async function uploadCover(id, file) {
  uploadingCover.value = true
  try {
    const fd = new FormData()
    fd.append('image', file)
    const { data } = await blogApi.uploadCover(id, fd)
    form.cover_image = data.cover_image
    pendingCoverFile.value = null
  } finally {
    uploadingCover.value = false
  }
}

onMounted(async () => {
  await Promise.all([fetchCategories(), fetchPost()])
})
</script>

<style scoped>
.blog-form-layout {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: var(--space-6);
  align-items: start;
}
@media (max-width: 1024px) {
  .blog-form-layout { grid-template-columns: 1fr; }
  .blog-form-sidebar { order: -1; }
}

/* ── Slug ── */
.slug-wrap {
  display: flex;
  align-items: center;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-md);
  overflow: hidden;
  background: #fff;
  transition: border-color var(--transition-fast);
}
.slug-wrap:focus-within { border-color: var(--rose-400); }
.slug-prefix {
  padding: 10px 12px;
  background: var(--cream-100);
  color: var(--gray-400);
  font-size: 0.8125rem;
  border-right: 1.5px solid var(--cream-200);
  white-space: nowrap;
  flex-shrink: 0;
}
.slug-input {
  flex: 1;
  padding: 10px 12px;
  border: none;
  outline: none;
  font-size: 0.8125rem;
  color: var(--gray-700);
  background: transparent;
}

/* ── SEO preview ── */
.seo-preview {
  background: var(--cream-50);
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-md);
  padding: var(--space-3) var(--space-4);
}
.seo-preview__title {
  font-size: 0.9375rem;
  color: #1a0dab;
  font-weight: 500;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.seo-preview__url {
  font-size: 0.75rem;
  color: #006621;
  margin-bottom: 3px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.seo-preview__desc {
  font-size: 0.75rem;
  color: #545454;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* ── Cover image ── */
.cover-preview {
  position: relative;
  border-radius: var(--radius-md);
  overflow: hidden;
  aspect-ratio: 16/9;
  background: var(--cream-100);
}
.cover-preview img { width: 100%; height: 100%; object-fit: cover; }
.cover-remove {
  position: absolute;
  top: 6px; right: 6px;
  width: 22px; height: 22px;
  border-radius: 50%;
  background: rgba(0,0,0,0.5);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background var(--transition-fast);
}
.cover-remove:hover { background: rgba(239,68,68,0.85); }

.cover-upload-zone {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  border: 2px dashed var(--cream-300);
  border-radius: var(--radius-md);
  padding: var(--space-5);
  cursor: pointer;
  transition: all var(--transition-fast);
  background: var(--cream-50);
}
.cover-upload-zone:hover,
.cover-upload-zone.dragging {
  border-color: var(--rose-300);
  background: var(--rose-50);
}

/* ── New category inline form ── */
.new-cat-form {
  padding: var(--space-3);
  background: var(--cream-50);
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
}
</style>
