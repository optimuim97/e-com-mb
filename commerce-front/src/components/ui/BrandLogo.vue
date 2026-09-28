<template>
  <img
    v-if="logoUrl && !broken"
    :src="logoUrl"
    :alt="settings.shopName"
    :class="imgClass"
    :width="width"
    :height="height"
    @error="broken = true"
  />
  <span v-else class="brand-wordmark" :class="[`brand-wordmark--${size}`, textClass]">
    {{ settings.shopName }}
  </span>
</template>

<script setup>
/**
 * Logo de la boutique (marque blanche).
 *
 * Affiche le logo réglé dans l'administration (`shop_logo_url`). Tant qu'il
 * n'y en a pas, ou s'il ne se charge pas, le nom de la boutique fait office
 * de logo : une boutique fraîchement installée n'affiche jamais la marque
 * d'une autre.
 */
import { computed, ref, watch } from 'vue'
import { useSettingsStore } from '@/stores/settings'

defineOptions({ inheritAttrs: false })

const props = defineProps({
  imgClass:  { type: [String, Array, Object], default: '' },
  textClass: { type: [String, Array, Object], default: '' },
  size:      { type: String, default: 'md' }, // sm | md | lg
  width:     { type: [Number, String], default: undefined },
  height:    { type: [Number, String], default: undefined },
})

const settings = useSettingsStore()
const broken   = ref(false)

const logoUrl = computed(() => settings.shopLogoUrl)
watch(logoUrl, () => { broken.value = false })
</script>

<style scoped>
.brand-wordmark {
  display: inline-block;
  font-family: var(--font-display, Georgia, serif);
  font-weight: 600;
  line-height: 1.1;
  letter-spacing: 0.01em;
  color: var(--rose-600);
  white-space: nowrap;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
}
.brand-wordmark--sm { font-size: 1.05rem; }
.brand-wordmark--md { font-size: 1.5rem; }
.brand-wordmark--lg { font-size: 2rem; }
</style>
