<template>
  <div class="admin-page">
    <!-- Header -->
    <header class="page-header">
      <div>
        <span class="eyebrow">Marketing</span>
        <h1 class="page-header__title">Galerie photos</h1>
      </div>
      <button class="btn btn-primary btn-sm" :disabled="uploading" @click="fileInput?.click()">
        <span v-if="uploading" class="spinner spinner--sm"></span>
        <svg v-else width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
        </svg>
        {{ uploading ? `Envoi ${uploadDone}/${uploadTotal}…` : 'Ajouter des photos' }}
      </button>
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        multiple
        class="gallery-file-input"
        @change="onFilesPicked"
      />
    </header>

    <!-- Aide -->
    <p class="gallery-hint">
      Ces photos alimentent la <strong>galerie</strong> de la page d'accueil.
      Ajoutez un nom et une courte description à chaque photo — la première photo (ordre 1) est mise en avant.
    </p>

    <!-- Loading -->
    <div v-if="loading" class="loader-wrap"><div class="loader"></div></div>

    <!-- Empty -->
    <div v-else-if="photos.length === 0" class="card empty-state">
      <div class="empty-state__icon">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" opacity="0.35">
          <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
        </svg>
      </div>
      <p>Aucune photo publiée pour le moment.</p>
      <button class="btn btn-primary btn-sm" @click="fileInput?.click()">Publier la première photo</button>
    </div>

    <!-- Grid -->
    <div v-else class="gallery-grid">
      <div
        v-for="(photo, idx) in photos"
        :key="photo.id"
        class="photo-card card"
        :class="{ 'photo-card--inactive': !photo.is_active }"
      >
        <!-- Image -->
        <div class="photo-card__img-wrap">
          <img :src="photo.image_url" :alt="photo.title || 'Photo galerie'" loading="lazy" />
          <span class="photo-card__order">{{ idx + 1 }}</span>
          <span v-if="!photo.is_active" class="badge badge-gray photo-card__badge">Masquée</span>
          <span v-else-if="idx === 0" class="badge badge-rose photo-card__badge">En vedette</span>
        </div>

        <!-- Édition inline -->
        <div class="photo-card__body">
          <input
            v-model="photo.title"
            type="text"
            class="input photo-card__title"
            placeholder="Titre de la photo"
            maxlength="120"
            @input="markDirty(photo)"
          />
          <textarea
            v-model="photo.description"
            class="input photo-card__desc"
            rows="2"
            placeholder="Petite description…"
            maxlength="500"
            @input="markDirty(photo)"
          />

          <div class="photo-card__actions">
            <!-- Réordonner -->
            <div class="photo-card__move">
              <button class="icon-btn" :disabled="idx === 0" title="Avancer" @click="move(idx, -1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
              </button>
              <button class="icon-btn" :disabled="idx === photos.length - 1" title="Reculer" @click="move(idx, 1)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
            </div>

            <!-- Visibilité -->
            <button
              class="toggle"
              :class="{ 'toggle--on': photo.is_active }"
              :title="photo.is_active ? 'Masquer du site' : 'Afficher sur le site'"
              @click="toggleActive(photo)"
            >
              <span class="toggle__dot"></span>
            </button>

            <div class="photo-card__spacer"></div>

            <!-- Enregistrer (visible si modifié) -->
            <button
              v-if="dirty.has(photo.id)"
              class="btn-xs btn-primary"
              :disabled="saving.has(photo.id)"
              @click="save(photo)"
            >
              {{ saving.has(photo.id) ? '…' : 'Enregistrer' }}
            </button>

            <!-- Supprimer -->
            <button class="icon-btn icon-btn--delete" title="Supprimer" @click="remove(photo)">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6h14"/></svg>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useToast } from 'vue-toastification'
import api from '@/api'

const toast = useToast()

const photos    = ref([])
const loading   = ref(true)
const fileInput = ref(null)

const uploading   = ref(false)
const uploadDone  = ref(0)
const uploadTotal = ref(0)

const dirty  = ref(new Set())
const saving = ref(new Set())

async function fetchPhotos() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/gallery')
    photos.value = data.data ?? data
  } catch {
    toast.error('Impossible de charger la galerie.')
  } finally {
    loading.value = false
  }
}

/* ── Upload (multiple, séquentiel) ── */
async function onFilesPicked(e) {
  const files = Array.from(e.target.files ?? [])
  e.target.value = '' // permet de re-sélectionner les mêmes fichiers
  if (!files.length) return

  uploading.value   = true
  uploadTotal.value = files.length
  uploadDone.value  = 0
  let failed = 0

  for (const file of files) {
    const fd = new FormData()
    fd.append('image', file)
    try {
      const { data } = await api.post('/admin/gallery', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      photos.value.push(data.data ?? data)
    } catch {
      failed++
    } finally {
      uploadDone.value++
    }
  }

  uploading.value = false
  if (failed) toast.warning(`${failed} photo(s) n'ont pas pu être envoyées.`)
  else toast.success(`${files.length} photo(s) publiée(s). Ajoutez nom et description !`)
}

/* ── Édition inline ── */
function markDirty(photo) {
  dirty.value = new Set(dirty.value).add(photo.id)
}

async function save(photo) {
  saving.value = new Set(saving.value).add(photo.id)
  try {
    await api.put(`/admin/gallery/${photo.id}`, {
      title:       photo.title || null,
      description: photo.description || null,
    })
    const next = new Set(dirty.value)
    next.delete(photo.id)
    dirty.value = next
    toast.success('Photo mise à jour.')
  } catch {
    toast.error('Enregistrement impossible.')
  } finally {
    const next = new Set(saving.value)
    next.delete(photo.id)
    saving.value = next
  }
}

async function toggleActive(photo) {
  const previous = photo.is_active
  photo.is_active = !previous
  try {
    await api.put(`/admin/gallery/${photo.id}`, { is_active: photo.is_active })
  } catch {
    photo.is_active = previous
    toast.error('Changement de visibilité impossible.')
  }
}

/* ── Réordonner ── */
async function move(idx, delta) {
  const target = idx + delta
  if (target < 0 || target >= photos.value.length) return
  const list = [...photos.value]
  ;[list[idx], list[target]] = [list[target], list[idx]]
  photos.value = list
  try {
    await api.put('/admin/gallery-reorder', { ids: list.map(p => p.id) })
  } catch {
    toast.error("L'ordre n'a pas pu être enregistré.")
    fetchPhotos()
  }
}

/* ── Supprimer ── */
async function remove(photo) {
  if (!confirm(`Supprimer cette photo${photo.title ? ` (${photo.title})` : ''} ? Cette action est définitive.`)) return
  try {
    await api.delete(`/admin/gallery/${photo.id}`)
    photos.value = photos.value.filter(p => p.id !== photo.id)
    toast.success('Photo supprimée.')
  } catch {
    toast.error('Suppression impossible.')
  }
}

onMounted(fetchPhotos)
</script>

<style scoped>
.gallery-file-input { display: none; }

.gallery-hint {
  font-size: 0.8125rem;
  color: var(--gray-500);
  background: var(--cream-50);
  border: 1px solid var(--cream-200);
  border-radius: var(--radius-md);
  padding: 10px 14px;
  margin-bottom: 16px;
  line-height: 1.5;
}

/* ── Grille ── */
.gallery-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
}

.photo-card {
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: opacity 0.15s;
}
.photo-card--inactive { opacity: 0.6; }

.photo-card__img-wrap {
  position: relative;
  aspect-ratio: 4/3;
  background: var(--cream-100);
}
.photo-card__img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

.photo-card__order {
  position: absolute;
  top: 8px;
  left: 8px;
  min-width: 22px;
  height: 22px;
  border-radius: 999px;
  background: rgba(20, 12, 16, 0.65);
  color: #fff;
  font-size: 0.6875rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 6px;
}

.photo-card__badge {
  position: absolute;
  top: 8px;
  right: 8px;
}

.photo-card__body {
  padding: 10px 12px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.photo-card__title {
  font-size: 0.8125rem !important;
  padding: 7px 12px !important;
  font-weight: 600;
}
.photo-card__desc {
  font-size: 0.75rem !important;
  padding: 7px 12px !important;
  resize: none;
  min-height: 48px;
  line-height: 1.45 !important;
}

.photo-card__actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.photo-card__move { display: flex; gap: 2px; }
.photo-card__move .icon-btn { width: 28px; height: 28px; color: var(--gray-500); }
.photo-card__move .icon-btn:hover:not(:disabled) { background: var(--cream-100); color: var(--gray-700); }
.photo-card__move .icon-btn:disabled { opacity: 0.3; cursor: default; }
.photo-card__spacer { flex: 1; }
</style>
