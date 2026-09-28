import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { useSettingsStore } from '@/stores/settings'

// Visuels neutres par défaut, remplacés par les photos réglées dans l'admin
// (Paramètres → Accueil → Photos du hero).
const FALLBACK_HERO_PHOTOS = [
  '/placeholders/photo-1.jpg',
  '/placeholders/photo-2.jpg',
  '/placeholders/photo-3.jpg',
]

/**
 * Logique de carrousel hero partagée entre les variantes.
 * Source : settings.heroImages (admin) sinon fallback local.
 */
export function useHeroPhotos(duration = 7000) {
  const settings = useSettingsStore()

  const heroPhotos = computed(() => {
    const configured = settings.heroImages
    if (Array.isArray(configured) && configured.length) return configured
    return FALLBACK_HERO_PHOTOS
  })

  const currentPhoto = ref(0)
  let timer = null

  function next() {
    currentPhoto.value = (currentPhoto.value + 1) % heroPhotos.value.length
  }
  function onPhotoError(i) {
    const fb = FALLBACK_HERO_PHOTOS[i % FALLBACK_HERO_PHOTOS.length]
    if (heroPhotos.value[i] !== fb) heroPhotos.value[i] = fb
  }

  onMounted(() => {
    if (heroPhotos.value.length > 1) timer = setInterval(next, duration)
  })
  onBeforeUnmount(() => clearInterval(timer))

  return { heroPhotos, currentPhoto, onPhotoError }
}

export { FALLBACK_HERO_PHOTOS }
