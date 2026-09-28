<template>
  <div class="product-card card-hover group">
    <!-- Image + badges + wishlist -->
    <RouterLink :to="`/products/${product.slug}`" class="product-card__img-wrap block relative">
      <!-- Badges left -->
      <div class="absolute top-3 left-3 z-10 flex flex-col gap-1.5">
        <span v-if="unavailableKey"
          class="badge badge-gray">
          {{ $t(unavailableKey) }}
        </span>
        <span v-else-if="product.is_featured"
          class="badge badge-primary">
          Vedette
        </span>
      </div>

      <!-- Discount badge -->
      <div v-if="discountBadge" class="absolute top-3 left-3 z-10 mt-0" :class="{'mt-7': unavailableKey || product.is_featured}">
        <span class="badge badge-warning font-bold">{{ discountBadge }}</span>
      </div>

      <!-- Wishlist button (top-right) -->
      <div class="absolute top-3 right-3 z-10" @click.prevent>
        <WishlistButton :productId="product.id" />
      </div>

      <!-- Image -->
      <div class="aspect-square rounded-t-2xl overflow-hidden bg-rose-50">
        <img
          v-if="cover && !imgError"
          :src="cover"
          :alt="product.name"
          class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
          :class="{ 'opacity-50 grayscale': unavailableKey }"
          @error="imgError = true"
        />
        <div v-else class="w-full h-full flex items-center justify-center text-5xl select-none"><FlowerMark /></div>
      </div>
    </RouterLink>

    <!-- Info -->
    <div class="p-4">
      <p class="text-xs text-primary-400 font-medium mb-1">{{ product.category?.name }}</p>
      <RouterLink :to="`/products/${product.slug}`">
        <h3 class="text-sm font-semibold text-gray-800 line-clamp-2 mb-3 min-h-[2.5rem] hover:text-primary-500 transition-colors">
          {{ product.name }}
        </h3>
      </RouterLink>

      <div class="flex items-center justify-between gap-2">
        <div class="flex items-baseline gap-1.5">
          <span class="text-primary-500 font-bold text-base">{{ formatPrice(sellingPrice) }}</span>
          <span v-if="referencePrice" class="text-xs text-gray-400 line-through">
            {{ formatPrice(referencePrice) }}
          </span>
        </div>
        <!--
          Le nombre est celui du vendable en ligne : « plus que 3 » est vrai,
          et c'est le seul cas où cette mention ne ment pas.
        -->
        <div v-if="scarcity"
          class="product-card__stock-hint shrink-0">
          {{ $t('product.onlyLeft', { count: scarcity }) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import WishlistButton from '@/features/wishlist/WishlistButton.vue'
import { useCurrencyStore } from '@/stores/currency'
import * as pricing from '@/utils/pricing'
import { unavailableLabelKey, scarcityCount } from '@/utils/availability'

const props = defineProps({ product: { type: Object, required: true } })

const imgError = ref(false)
const cover    = computed(() => props.product.images?.[0]?.url ?? null)
const currency = useCurrencyStore()

/* Price shown to the customer: promotions and flash sales included. */
const sellingPrice   = computed(() => pricing.sellingPrice(props.product))
const referencePrice = computed(() => pricing.referencePrice(props.product))
const discountBadge  = computed(() => pricing.discountBadge(props.product))

/* Disponibilité : décidée par le serveur, voir utils/availability. */
const unavailableKey = computed(() => unavailableLabelKey(props.product))
const scarcity       = computed(() => scarcityCount(props.product))

function formatPrice(price) {
  return currency.format(price)
}
</script>

<style scoped>
.product-card {
  border-radius: var(--radius-xl);
  overflow: hidden;
  background: var(--color-surface);
  display: flex;
  flex-direction: column;
}

.product-card__img-wrap {
  display: block;
  text-decoration: none;
}

/* Rappel stock : chip discrète plutôt qu'un avertissement criard */
.product-card__stock-hint {
  font-size: 0.6875rem;
  font-weight: 500;
  color: var(--rose-600, #c0386b);
  background: var(--rose-50, #fdeef2);
  padding: 3px 8px;
  border-radius: 999px;
}
</style>
