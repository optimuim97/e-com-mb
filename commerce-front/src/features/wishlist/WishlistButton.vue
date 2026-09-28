<template>
  <button
    v-if="settings.moduleEnabled('wishlist')"
    class="wishlist-btn"
    :class="{ 'wishlist-btn--active': active, 'wishlist-btn--loading': busy }"
    :aria-label="active ? $t('wishlist.removeLabel') : $t('product.addToWishlist')"
    :aria-pressed="active"
    @click.prevent="handleClick"
    :disabled="busy"
  >
    <!-- Cœur rempli -->
    <svg
      v-if="active"
      class="wishlist-btn__icon wishlist-btn__icon--filled"
      viewBox="0 0 24 24"
      fill="currentColor"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5
               2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09
               C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5
               c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
    </svg>
    <!-- Cœur outline -->
    <svg
      v-else
      class="wishlist-btn__icon"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
      stroke-linecap="round"
      stroke-linejoin="round"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06
               a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78
               1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
    </svg>

    <!-- Pulse ring au click -->
    <span v-if="justToggled" class="wishlist-btn__pulse" />
  </button>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useWishlistStore } from '@/features/wishlist/wishlist.store'
import { useSettingsStore } from '@/stores/settings'

const props = defineProps({
  productId: { type: Number, required: true },
})

const wishlist = useWishlistStore()
const settings = useSettingsStore()
const router   = useRouter()
const busy     = ref(false)
const justToggled = ref(false)

const active = computed(() => wishlist.isWishlisted(props.productId))

async function handleClick() {
  if (busy.value) return
  busy.value = true
  justToggled.value = false

  const result = await wishlist.toggle(props.productId)

  if (result?.requiresLogin) {
    router.push({ name: 'login' })
    busy.value = false
    return
  }

  justToggled.value = true
  setTimeout(() => { justToggled.value = false }, 600)
  busy.value = false
}
</script>

<style scoped>
.wishlist-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(8px);
  color: var(--gray-400);
  transition: all var(--transition-fast);
  cursor: pointer;
  flex-shrink: 0;
}

.wishlist-btn:hover {
  background: #fff;
  color: var(--rose-500);
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(var(--rose-500-rgb), 0.15);
}

.wishlist-btn--active {
  color: var(--rose-500);
  background: var(--rose-50);
}
.wishlist-btn--active:hover {
  color: var(--rose-600);
  background: var(--rose-100);
}

.wishlist-btn--loading {
  opacity: 0.6;
  pointer-events: none;
}

.wishlist-btn__icon {
  width: 18px;
  height: 18px;
  transition: transform var(--transition-fast);
}

.wishlist-btn:hover .wishlist-btn__icon {
  transform: scale(1.1);
}

.wishlist-btn--active .wishlist-btn__icon--filled {
  animation: heart-pop 0.3s ease;
}

@keyframes heart-pop {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.3); }
  100% { transform: scale(1); }
}

/* Pulse ring */
.wishlist-btn__pulse {
  position: absolute;
  inset: -4px;
  border-radius: var(--radius-full);
  border: 2px solid var(--rose-400);
  animation: pulse-ring 0.5s ease-out forwards;
  pointer-events: none;
}

@keyframes pulse-ring {
  0%   { opacity: 0.8; transform: scale(0.85); }
  100% { opacity: 0;   transform: scale(1.4); }
}
</style>
