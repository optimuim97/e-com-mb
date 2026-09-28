<template>
  <Teleport to="body">
    <!-- Overlay -->
    <Transition name="overlay">
      <div v-if="cartStore.isOpen"
        class="drawer-overlay"
        @click="cartStore.close()" />
    </Transition>

    <!-- Panel -->
    <Transition name="drawer">
      <aside v-if="cartStore.isOpen" class="drawer" :data-step="step">

        <!-- Bouton fermer flottant : grand, tactile, toujours visible -->
        <button
          @click="cartStore.close()"
          class="drawer__close-floating"
          :aria-label="$t('drawer.closeCart')"
          type="button"
        >
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
            <path d="M18 6 6 18M6 6l12 12"/>
          </svg>
        </button>

        <!-- Stepper bar (shared) -->
        <div class="drawer__topbar">
          <button v-if="step > 1" @click="step--" class="drawer__icon-btn" :aria-label="$t('drawer.prevStep')">
            <ArrowLeftIcon class="w-5 h-5" />
          </button>
          <span v-else class="drawer__icon-spacer"></span>

          <div class="drawer__steps">
            <template v-for="(s, idx) in steps" :key="s.n">
              <div
                class="drawer__stepdot"
                :class="{ 'drawer__stepdot--active': step === s.n, 'drawer__stepdot--done': step > s.n }"
              >
                <span class="drawer__stepdot-circle">
                  <svg v-if="step > s.n" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round">
                    <polyline points="20 6 9 17 4 12"/>
                  </svg>
                  <span v-else>{{ s.n }}</span>
                </span>
                <span class="drawer__stepdot-label">{{ s.label }}</span>
              </div>
              <span
                v-if="idx < steps.length - 1"
                class="drawer__step-line"
                :class="{ 'drawer__step-line--active': step > s.n }"
              ></span>
            </template>
          </div>

          <button @click="cartStore.close()" class="drawer__icon-btn" :aria-label="$t('common.close')">
            <XMarkIcon class="w-5 h-5" />
          </button>
        </div>

        <!-- ── ÉTAPE 1 : PANIER ── -->
        <template v-if="step === 1">
          <header class="drawer__header">
            <span class="eyebrow">{{ $t('drawer.yourSelection') }}</span>
            <h2 class="drawer__title">{{ $t('drawer.myCart') }} <em>{{ $t('drawer.myCartEm') }}</em></h2>
          </header>

          <div class="drawer__body">
            <div v-if="cartStore.items.length === 0" class="drawer__empty">
              <div class="drawer__empty-icon"><FlowerMark /></div>
              <p>{{ $t('drawer.emptyCart') }}</p>
              <RouterLink to="/products" @click="cartStore.close()" class="btn btn-outline btn-sm">
                {{ $t('drawer.discoverCare') }}
              </RouterLink>
            </div>

            <div v-else class="drawer__items">
              <div v-for="item in cartStore.items" :key="item.id" class="drawer__item">
                <div class="drawer__item-img">
                  <img
                    v-if="item.product?.images?.[0]?.url && !erroredImages.has(item.id)"
                    :src="item.product.images[0].url"
                    :alt="item.product?.name"
                    @error="erroredImages.add(item.id)"
                  />
                  <span v-else><FlowerMark /></span>
                </div>
                <div class="drawer__item-info">
                  <span class="drawer__item-cat">{{ item.product?.category?.name }}</span>
                  <p class="drawer__item-name">{{ item.product?.name ?? item.name ?? $t('drawer.defaultProduct') }}</p>
                  <p class="drawer__item-price">{{ fmt(Number(item.unit_price ?? item.price ?? 0)) }}</p>
                </div>
                <div class="drawer__item-actions">
                  <button @click="cartStore.remove(item.id)" class="drawer__remove" :aria-label="$t('drawer.remove')">
                    <TrashIcon class="w-3.5 h-3.5" />
                  </button>
                  <div class="drawer__qty">
                    <button @click="changeQty(item, -1)" :disabled="loadingItemId === item.id"
                      class="drawer__qty-btn" :aria-label="$t('drawer.decrease')">
                      <span v-if="loadingItemId === item.id" class="drawer__mini-spin"></span>
                      <MinusIcon v-else class="w-3 h-3" />
                    </button>
                    <span class="drawer__qty-value">{{ item.quantity }}</span>
                    <button @click="changeQty(item, 1)" :disabled="loadingItemId === item.id"
                      class="drawer__qty-btn drawer__qty-btn--primary" :aria-label="$t('drawer.increase')">
                      <PlusIcon class="w-3 h-3" />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Choix commande (non-connecté) ── -->
          <Transition name="choice-slide">
            <div v-if="showAuthChoice" class="drawer__choice">
              <button class="drawer__choice-back" @click="showAuthChoice = false">
                <ArrowLeftIcon class="w-4 h-4" />
                {{ $t('drawer.backToCart') }}
              </button>
              <p class="drawer__choice-title">{{ $t('drawer.howToOrder') }}</p>

              <!-- Commande rapide (admin setting) -->
              <button
                v-if="settings.enableQuickOrder"
                class="drawer__choice-card drawer__choice-card--quick"
                @click="goQuickOrder"
              >
                <span class="drawer__choice-card__icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>
                </span>
                <div class="drawer__choice-card__body">
                  <strong>{{ $t('drawer.quickOrder') }}</strong>
                  <span>{{ $t('drawer.quickOrderDesc') }}</span>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>

              <!-- Se connecter -->
              <button class="drawer__choice-card" @click="goLogin">
                <span class="drawer__choice-card__icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 7a4 4 0 1 0-4 4M11 11l-7 7v3h3l7-7"/><circle cx="16.5" cy="7.5" r="4.5"/></svg>
                </span>
                <div class="drawer__choice-card__body">
                  <strong>{{ $t('common.login') }}</strong>
                  <span>{{ $t('drawer.loginDesc') }}</span>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>

              <!-- Créer un compte -->
              <button class="drawer__choice-card" @click="goRegister">
                <span class="drawer__choice-card__icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                </span>
                <div class="drawer__choice-card__body">
                  <strong>{{ $t('common.register') }}</strong>
                  <span>{{ $t('drawer.registerDesc') }}</span>
                </div>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>

              <!-- Connexion sociale rapide -->
              <!-- Facebook était le seul bouton ici ; il est masqué tant que
                   la configuration côté Meta n'est pas finalisée, et remplacé
                   par Google pour ne pas laisser la section vide. -->
              <div class="drawer__choice-divider"><span>{{ $t('drawer.oneClick') }}</span></div>
              <GoogleButton @success="onSocialSuccess" />
            </div>
          </Transition>

          <footer class="drawer__footer" v-show="!showAuthChoice">
            <div class="drawer__totals">
              <div>
                <span>{{ $t('drawer.subtotal') }}</span>
                <span>{{ fmt(cartStore.subtotal) }}</span>
              </div>
              <div>
                <span>{{ $t('drawer.shipping') }}</span>
                <span :class="shippingLabelClass">{{ shippingLabel }}</span>
              </div>
              <div class="drawer__totals-final">
                <span>{{ $t('drawer.estimatedTotal') }}</span>
                <span>{{ fmt(grandTotal) }}</span>
              </div>
            </div>
            <button @click="handleCheckout" :disabled="cartStore.items.length === 0"
              class="btn btn-primary btn-lg drawer__cta">
              {{ $t('drawer.checkout') }}
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </button>
          </footer>
        </template>

        <!-- ── ÉTAPE 2 : INFORMATIONS PERSONNELLES ── -->
        <template v-if="step === 2">
          <header class="drawer__header">
            <span class="eyebrow">{{ $t('drawer.yourContact') }}</span>
            <h2 class="drawer__title">{{ $t('drawer.personalInfo') }} <em>{{ $t('drawer.personalInfoEm') }}</em></h2>
          </header>

          <div class="drawer__body">
            <p class="drawer__step-hint">{{ $t('drawer.stepHint') }}</p>

            <div class="drawer__group">
              <label class="label">{{ $t('drawer.fullName') }}</label>
              <input v-model="form.name" type="text" class="input" :placeholder="$t('drawer.namePlaceholder')" />
            </div>
            <div class="drawer__group">
              <label class="label">{{ $t('drawer.whatsappNumber') }}</label>
              <PhoneInput v-model="form.phone" placeholder="07 00 00 00" />
            </div>

            <!-- CGV -->
            <label class="drawer__cgv">
              <input type="checkbox" v-model="form.cgv" />
              <span>{{ $t('drawer.cgv') }} <a href="#">{{ $t('drawer.cgvLink') }}</a> {{ $t('drawer.cgvSuffix') }}</span>
            </label>
          </div>

          <footer class="drawer__footer">
            <div class="drawer__totals">
              <div>
                <span>{{ $t('drawer.subtotal') }}</span>
                <span>{{ fmt(cartStore.subtotal) }}</span>
              </div>
              <div>
                <span>{{ $t('drawer.shipping') }}</span>
                <span :class="shippingLabelClass">{{ shippingLabel }}</span>
              </div>
              <div class="drawer__totals-final">
                <span>{{ $t('drawer.estimatedTotal') }}</span>
                <span>{{ fmt(grandTotal) }}</span>
              </div>
            </div>
            <button @click="goToDelivery"
              :disabled="!form.name || !form.phone || !form.cgv"
              class="btn btn-primary btn-lg drawer__cta">
              {{ $t('drawer.continue') }}
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M5 12h14M12 5l7 7-7 7"/>
              </svg>
            </button>
          </footer>
        </template>

        <!-- ── ÉTAPE 3 : LIVRAISON & PAIEMENT ── -->
        <template v-if="step === 3">
          <header class="drawer__header">
            <span class="eyebrow">{{ $t('drawer.lastStep') }}</span>
            <h2 class="drawer__title">{{ $t('drawer.deliveryPayment') }} <em>{{ $t('drawer.deliveryPaymentEm') }}</em></h2>
          </header>

          <div class="drawer__body">

            <!-- ── Section adresse ── -->
            <div class="drawer__section">
              <h3 class="drawer__section-title">
                <span class="drawer__section-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span> {{ $t('checkout.shippingAddress') }}
              </h3>

              <div class="drawer__group">
                <label class="label">{{ $t('drawer.country') }}</label>
                <AppSelect
                  v-model="form.country"
                  :options="countryOptions"
                  :placeholder="$t('drawer.selectCountry')"
                  @update:modelValue="onCountryChange"
                />
              </div>

              <CitySelect
                v-if="form.country === 'CI'"
                v-model:city="form.city"
                v-model:commune="form.commune"
              />
              <CityFree
                v-else-if="form.country"
                v-model:city="form.city"
                v-model:region="form.region"
                :country="form.country"
              />

              <!-- Recherche + carte -->
              <div class="drawer__search">
                <MagnifyingGlassIcon class="drawer__search-icon" />
                <input v-model="mapSearch" @keydown.enter="searchAddress"
                  type="text" class="input drawer__search-input"
                  :placeholder="$t('drawer.searchPlace')" />
              </div>
              <div ref="mapEl" class="drawer__map"></div>
              <button @click="locateMe" :disabled="locating" class="btn btn-outline drawer__locate">
                <MapPinIcon class="w-4 h-4" />
                {{ locating ? $t('drawer.locating') : $t('drawer.locateMe') }}
              </button>

              <div v-if="form.address" class="drawer__address-selected">
                <MapPinIcon class="w-4 h-4" />
                <p>{{ form.address }}</p>
              </div>
              <p v-else class="drawer__address-empty">{{ $t('drawer.addressHere') }}</p>

              <!-- Hors zone : frais communiqués manuellement -->
              <div v-if="shippingManual" class="drawer__notice drawer__notice--warn">
                <span class="drawer__notice-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                <div>
                  <strong>{{ $t('drawer.unpricedZone') }}</strong>
                  <p>{{ $t('drawer.unpricedZoneDesc') }}</p>
                </div>
              </div>

              <!--
                Point de repère — et c'est ici que vit « Ma position ».
                Le bouton était au-dessus du sélecteur de ville, où un clic
                pouvait réécrire ville et commune. Il ne remplit plus que ce
                champ. « Me localiser », juste au-dessus, reste distinct : il
                déplace le marqueur sur la carte et renseigne l'adresse.
              -->
              <div class="drawer__group">
                <label class="label">{{ $t('fields.landmark') }}</label>
                <textarea v-model="form.instructions" class="input drawer__textarea"
                  :placeholder="$t('drawer.landmarkPlaceholder')" />
              </div>
            </div>

            <!-- ── Section paiement ── -->
            <div class="drawer__section">
              <h3 class="drawer__section-title">
                <span class="drawer__section-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg></span> {{ $t('drawer.paymentMethod') }}
              </h3>
              <div class="drawer__payments">
                <label v-for="pm in paymentMethods" :key="pm.value"
                  class="drawer__payment"
                  :class="{ 'drawer__payment--active': form.payment === pm.value }">
                  <input type="radio" :value="pm.value" v-model="form.payment" class="drawer__payment-input" />
                  <span class="drawer__payment-icon" v-html="pm.icon"></span>
                  <div class="drawer__payment-info">
                    <strong>{{ pm.label }}</strong>
                    <span>{{ pm.desc }}</span>
                  </div>
                  <span v-if="pm.badge" class="badge badge-rose">{{ pm.badge }}</span>
                  <span class="drawer__payment-dot"></span>
                </label>
              </div>
            </div>

            <!-- ── Section récapitulatif + promo ── -->
            <div class="drawer__section drawer__section--recap">
              <h3 class="drawer__section-title">
                <span class="drawer__section-ico"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14l2 2 4-4M6 2h9l5 5v13a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/></svg></span> {{ $t('checkout.summary') }}
              </h3>

              <!-- Code promo -->
              <div class="drawer__coupon">
                <input v-model="couponCode" type="text" class="input drawer__coupon-input"
                  :placeholder="$t('drawer.promoCode')" :disabled="couponApplied" />
                <button @click="applyPromo" :disabled="couponLoading || couponApplied"
                  class="btn btn-outline btn-sm">
                  {{ couponApplied ? $t('checkout.applied') : $t('drawer.apply') }}
                </button>
              </div>
              <p v-if="couponError" class="drawer__msg drawer__msg--error">{{ couponError }}</p>
              <p v-if="couponApplied" class="drawer__msg drawer__msg--success">{{ $t('drawer.discountApplied') }}</p>

              <!-- Totaux -->
              <div class="drawer__recap-totals">
                <div class="drawer__recap-line">
                  <span>{{ $t('drawer.subtotal') }}</span>
                  <span>{{ fmt(cartStore.subtotal) }}</span>
                </div>
                <div v-if="couponApplied && cartStore.subtotal !== cartStore.total" class="drawer__recap-line drawer__recap-line--discount">
                  <span>{{ $t('common.discount') }}</span>
                  <span>- {{ fmt(cartStore.subtotal - cartStore.total) }}</span>
                </div>
                <div class="drawer__recap-line">
                  <span>{{ $t('drawer.shipping') }}</span>
                  <span :class="shippingLabelClass">{{ shippingLabel }}</span>
                </div>
                <div class="drawer__recap-line drawer__recap-line--total">
                  <span>{{ $t('drawer.total') }}</span>
                  <span>{{ fmt(grandTotal) }}</span>
                </div>
              </div>
            </div>

          </div>

          <footer class="drawer__footer">
            <button @click="submitOrder"
              :disabled="!form.city || !form.payment || submitting"
              class="btn btn-primary btn-lg drawer__cta">
              <span v-if="submitting" class="drawer__spinner"></span>
              <span v-else>{{ $t('drawer.confirmWithTotal', { total: fmt(grandTotal) }) }}</span>
            </button>
            <p v-if="submitError" class="drawer__msg drawer__msg--error drawer__msg--center">{{ submitError }}</p>
          </footer>
        </template>

      </aside>
    </Transition>
  </Teleport>

  <!-- Modal commande rapide -->
  <QuickOrderModal
    v-if="showQuickOrder"
    @close="showQuickOrder = false"
  />
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useCurrencyStore } from '@/stores/currency';
import CitySelect      from '@/components/shop/CitySelect.vue';
import CityFree        from '@/components/shop/CityFree.vue';
import QuickOrderModal from '@/features/checkout/QuickOrderModal.vue';
import GoogleButton    from '@/features/auth/GoogleButton.vue';
import PhoneInput      from '@/components/ui/PhoneInput.vue';
import { useRouter, RouterLink } from 'vue-router';
import api from '@/api';
import { useSettingsStore } from '@/stores/settings';
import {
  XMarkIcon, ArrowLeftIcon, TrashIcon, MinusIcon, PlusIcon,
  MapPinIcon, MagnifyingGlassIcon,
} from '@heroicons/vue/24/outline';
import { useCartStore } from '@/features/cart/cart.store';
import { useAuthStore } from '@/features/auth/auth.store';

const cartStore  = useCartStore();
const authStore  = useAuthStore();
const settings   = useSettingsStore();
const { t }      = useI18n();
const router     = useRouter();

// ── Frais de livraison : quote API selon la ville/commune ──────────────────
// shippingQuote : null avant recherche, { ...zone } si trouvée, { not_found: true } sinon
const shippingQuote = ref(null)

const shippingFound  = computed(() => shippingQuote.value && !shippingQuote.value.not_found)
const shippingManual = computed(() => shippingQuote.value?.not_found === true)
// Tarif au kilo (international) : poids inconnu → agents renseignent
const shippingPerKg  = computed(() => shippingFound.value && shippingQuote.value.unit === 'per_kg')

const shippingCost = computed(() => {
  // Le prix vient directement de la zone de livraison — pas de gratuité.
  if (shippingFound.value && !shippingPerKg.value) return Number(shippingQuote.value.price) || 0
  // Hors zone / per kg / pas encore renseigné : on n'invente aucun montant.
  return 0
})

const shippingLabel = computed(() => {
  if (shippingFound.value && !shippingPerKg.value) return fmt(shippingQuote.value.price)
  if (shippingManual.value || shippingPerKg.value) return t('drawer.shippingByAgents')
  return t('checkout.shippingToFill')
})

const shippingLabelClass = computed(() =>
  (shippingManual.value || shippingPerKg.value) ? 'drawer__amber' : ''
)

const grandTotal = computed(() => cartStore.total + shippingCost.value)

// ── Continuité du checkout après login ────────────────────────────────────
const DRAFT_KEY = 'shop_checkout_draft'

function saveDraft() {
  // On sauvegarde aussi les articles afin de pouvoir les restaurer si le serveur
  // retourne un panier vide (rechargement de page, etc.)
  sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
    form:  form.value,
    step:  step.value,
    items: cartStore.items.map(i => ({
      product_id: i.product_id,
      variant_id: i.variant_id ?? null,
      quantity:   i.quantity,
    })),
  }))
}

async function restoreCheckoutDraft() {
  const raw = sessionStorage.getItem(DRAFT_KEY)
  if (!raw) return
  try {
    const draft = JSON.parse(raw)
    sessionStorage.removeItem(DRAFT_KEY)

    // Restaurer le formulaire
    form.value = { ...form.value, ...draft.form }

    // Compléter avec le profil connecté si les champs sont vides
    // (cas inscription : le nom/téléphone saisi à l'inscription est disponible)
    if (!form.value.name  && authStore.user?.name)  form.value.name  = authStore.user.name
    if (!form.value.phone && authStore.user?.phone) form.value.phone = authStore.user.phone

    // Aller directement à l'étape paiement — l'utilisateur vient de s'authentifier
    step.value = 2

    // ── Fusion panier hors-ligne → panier authentifié ────────────────────
    // On rejoue les items du brouillon sur le serveur (qui dédoublonne par
    // product_id+variant_id et incrémente la quantité). Les items qui étaient
    // déjà dans le panier auth sont conservés.
    if (draft.items?.length) {
      // Index des items déjà présents pour éviter de doubler une quantité
      // identique (cas du même panier rejoué après un simple rechargement)
      const existing = new Map()
      for (const it of cartStore.items) {
        const key = `${it.product_id}::${it.variant_id ?? ''}`
        existing.set(key, it.quantity)
      }

      let mergedCount = 0
      for (const item of draft.items) {
        const key = `${item.product_id}::${item.variant_id ?? ''}`
        const alreadyHere = existing.get(key) ?? 0
        // Si l'item du brouillon est déjà couvert (qty serveur >= qty brouillon),
        // pas la peine de réajouter (évite un double après refresh)
        if (alreadyHere >= item.quantity) continue
        const delta = item.quantity - alreadyHere
        try {
          await cartStore.add(item.product_id, delta, item.variant_id, { silent: true })
          mergedCount++
        } catch { /* produit indisponible / stock épuisé : on ignore */ }
      }
      if (mergedCount > 0) {
        // Re-fetch pour s'assurer que le panier reflète exactement l'état serveur
        try { await cartStore.fetch() } catch { /* ignore */ }
      }
    }

    nextTick(() => cartStore.open())
  } catch { /* ignore */ }
}

const steps = computed(() => [
  { n: 1, label: t('drawer.stepCart') },
  { n: 2, label: t('drawer.stepInfo') },
  { n: 3, label: t('drawer.stepOrder') },
]);

const step           = ref(1);
const loadingItemId  = ref(null);
const showQuickOrder = ref(false);
const showAuthChoice = ref(false);
const erroredImages  = reactive(new Set()); // IDs des items avec image cassée

const form = ref({
  name: '', phone: '', payment: '',
  cgv: false,
  // Livraison
  country: 'CI', city: '', commune: '', region: '',
  address: '', lat: null, lng: null, instructions: '',
});

// ── Quote livraison API : déclenchée dès (pays, ville, commune) ────────────
let quoteTimer = null
async function fetchShippingQuote() {
  const city    = form.value.city
  const commune = form.value.commune
  const country = form.value.country || 'CI'
  // CI : il faut au moins une ville ou commune. Étranger : pays seul suffit.
  if (country === 'CI' && !city && !commune) { shippingQuote.value = null; return }
  try {
    const { data } = await api.get('/shipping/quote', {
      params: { city, commune, country, subtotal: cartStore.subtotal },
    })
    shippingQuote.value = data?.found ? data : { not_found: true }
  } catch (e) {
    // 404 = aucune zone configurée pour cette destination
    shippingQuote.value = e.response?.status === 404 ? { not_found: true } : null
  }
}

watch(
  () => [form.value.city, form.value.commune, form.value.country, cartStore.subtotal],
  () => {
    clearTimeout(quoteTimer)
    quoteTimer = setTimeout(fetchShippingQuote, 250)
  }
)

const couponCode    = ref('');
const couponApplied = ref(false);
const couponLoading = ref(false);
const couponError   = ref('');
const payError      = ref('');
const submitError   = ref('');
const submitting    = ref(false);
const mapSearch     = ref('');
const locating      = ref(false);
const mapEl         = ref(null);

let leafletMap    = null;
let leafletMarker = null;

const ICON_MOBILE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>'
const ICON_CARD   = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>'
const ICON_TRUCK  = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>'

const paymentMethods = computed(() => [
  { value: 'wave',         label: t('drawer.wave'),        icon: ICON_MOBILE, desc: t('drawer.waveDesc'),   badge: t('drawer.waveBadge') },
  { value: 'orange_money', label: t('drawer.orangeMoney'), icon: ICON_MOBILE, desc: t('drawer.orangeDesc'), badge: null },
  { value: 'card',         label: t('drawer.card'),        icon: ICON_CARD,   desc: t('drawer.cardDesc'),   badge: null },
  { value: 'cod',          label: t('drawer.cod'),         icon: ICON_TRUCK,  desc: t('drawer.codDesc'),    badge: null },
]);

watch(() => step.value, async (val) => {
  if (val === 3) {
    await nextTick();
    document.activeElement?.blur() // évite le focus auto sur le premier champ (pays)
    initMap();
  }
});

watch(() => cartStore.isOpen, (val) => {
  if (!val) {
    step.value = 1;
    showAuthChoice.value = false;
    erroredImages.clear();
    // Reset livraison
    form.value.country = 'CI';
    form.value.city    = '';
    form.value.commune = '';
    form.value.region  = '';
    form.value.address = '';
    form.value.lat     = null;
    form.value.lng     = null;
    shippingQuote.value = null;
    leafletMap?.remove();
    leafletMap = null;
  }
});

// Restaurer le brouillon de commande après connexion
onMounted(() => {
  if (authStore.isLoggedIn) restoreCheckoutDraft()
})

watch(() => authStore.isLoggedIn, (val) => {
  if (val) restoreCheckoutDraft()
})

function handleCheckout() {
  if (cartStore.items.length === 0) return;
  if (authStore.isLoggedIn) {
    showAuthChoice.value = false;
    step.value = 2;
  } else {
    showAuthChoice.value = true;
  }
}

function goQuickOrder() {
  showAuthChoice.value = false;
  showQuickOrder.value = true;
}

function goLogin() {
  saveDraft()
  cartStore.close();
  router.push('/login');
}

function goRegister() {
  saveDraft()
  cartStore.close();
  router.push('/register');
}

// Connexion Facebook réussie depuis le panneau de choix → on enchaîne le checkout
function onSocialSuccess() {
  showAuthChoice.value = false;
  if (!form.value.name  && authStore.user?.name)  form.value.name  = authStore.user.name;
  if (!form.value.phone && authStore.user?.phone) form.value.phone = authStore.user.phone;
  step.value = 2;
}

const COUNTRY_CODES = ['CI','SN','ML','BF','GN','TG','BJ','GH','NG','FR','BE','CH','DE','GB','CA','US','OTHER']
const countryOptions = computed(() =>
  COUNTRY_CODES.map(code => ({ value: code, label: t(`checkout.countries.${code}`) }))
)

function onCountryChange() {
  form.value.city    = '';
  form.value.commune = '';
  form.value.region  = '';
}

function initMap() {
  if (leafletMap || !mapEl.value) return;
  import('leaflet').then(({ default: L }) => {
    import('leaflet/dist/leaflet.css');
    leafletMap = L.map(mapEl.value).setView([5.3485, -4.0137], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
    }).addTo(leafletMap);

    leafletMap.on('click', async (e) => {
      placeMarker(L, e.latlng.lat, e.latlng.lng);
      await resolveAddress(e.latlng.lat, e.latlng.lng);
    });
  });
}

function placeMarker(L, lat, lng) {
  if (leafletMarker) leafletMarker.remove();
  const icon = L.divIcon({
    html: `<div style="width:20px;height:20px;background:var(--rose-500);border:3px solid white;border-radius:50%;box-shadow:0 2px 8px rgba(var(--rose-500-rgb),.4)"></div>`,
    className: '',
    iconSize: [20, 20],
    iconAnchor: [10, 10],
  });
  leafletMarker = L.marker([lat, lng], { icon }).addTo(leafletMap);
  form.value.lat = lat;
  form.value.lng = lng;
}

async function resolveAddress(lat, lng) {
  try {
    const res  = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=fr`);
    const data = await res.json();
    form.value.address = data.display_name ?? `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  } catch {
    form.value.address = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
  }
}

async function searchAddress() {
  if (!mapSearch.value.trim() || !leafletMap) return;
  try {
    const res  = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(mapSearch.value)}&limit=1&accept-language=fr`);
    const data = await res.json();
    if (data[0]) {
      const { lat, lon, display_name } = data[0];
      import('leaflet').then(({ default: L }) => {
        leafletMap.setView([+lat, +lon], 16);
        placeMarker(L, +lat, +lon);
        form.value.address = display_name;
      });
    }
  } catch { /* ignore */ }
}

async function locateMe() {
  if (!leafletMap) return;
  locating.value = true;
  leafletMap.locate({ setView: true, maxZoom: 16 });
  leafletMap.once('locationfound', async (e) => {
    import('leaflet').then(({ default: L }) => placeMarker(L, e.latlng.lat, e.latlng.lng));
    await resolveAddress(e.latlng.lat, e.latlng.lng);
    locating.value = false;
  });
  leafletMap.once('locationerror', () => { locating.value = false; });
}

async function applyPromo() {
  if (!couponCode.value.trim()) return;
  couponLoading.value = true;
  couponError.value = '';
  try {
    await cartStore.applyCoupon(couponCode.value);
    couponApplied.value = true;
  } catch (e) {
    couponError.value = e.response?.data?.message ?? 'Code invalide.';
  } finally {
    couponLoading.value = false;
  }
}

function goToDelivery() {
  payError.value = '';
  step.value = 3;
}

async function submitOrder() {
  submitting.value = true;
  submitError.value = '';
  try {
    const nameParts = form.value.name.trim().split(/\s+/);
    const firstName = nameParts[0];
    const lastName  = nameParts.length > 1 ? nameParts.slice(1).join(' ') : nameParts[0];

    // Payload items as fallback when DB cart was already cleared
    const items = cartStore.items.map(item => ({
      product_id: item.product_id,
      variant_id: item.variant_id ?? null,
      quantity:   item.quantity,
    }))

    const { data } = await api.post('/orders', {
      payment_method: form.value.payment,
      coupon_code:    couponApplied.value ? couponCode.value : null,
      customer_note:  form.value.instructions || null,
      items,
      shipping_address: {
        first_name:    firstName,
        last_name:     lastName,
        phone:         form.value.phone,
        address_line1: form.value.address || `${form.value.city}${form.value.commune ? ', ' + form.value.commune : ''}`,
        city:          form.value.city,
        commune:       form.value.commune  || undefined,
        region:        form.value.region   || undefined,
        country:       form.value.country,
        lat:           form.value.lat      || undefined,
        lng:           form.value.lng      || undefined,
      },
    });
    // Paiement en ligne (CinetPay) — rediriger vers la plateforme de paiement
    if (data.payment_url) {
      cartStore.clear();
      cartStore.close();
      window.location.href = data.payment_url;
      return;
    }

    cartStore.clear();
    cartStore.close();
    // Le backend retourne { number: 'ORD-xxx', ... } — on protège contre un format inattendu
    const orderNumber = data?.number ?? data?.data?.number;
    if (orderNumber) {
      router.push({ name: 'order', params: { number: orderNumber } });
    } else {
      router.push({ name: 'orders' });
    }
  } catch (e) {
    if (!e._serverError) {
      submitError.value = e.response?.data?.message ?? t('drawer.submitError');
    }
  } finally {
    submitting.value = false;
  }
}

async function changeQty(item, delta) {
  if (loadingItemId.value) return;
  loadingItemId.value = item.id;
  try {
    const newQty = item.quantity + delta;
    if (newQty <= 0) await cartStore.remove(item.id);
    else await cartStore.update(item.id, newQty);
  } finally {
    loadingItemId.value = null;
  }
}

function fmt(val) {
  return useCurrencyStore().format(Number(val ?? 0));
}
</script>


<style scoped>
/* ── Overlay & drawer ── */
.drawer-overlay {
  position: fixed;
  inset: 0;
  height: 100vh;
  height: 100dvh;
  z-index: 290; /* au-dessus du navbar (var(--z-sticky)+1 / 200) */
  background: rgba(20, 18, 19, 0.4);
  backdrop-filter: blur(6px);
}

.drawer {
  position: fixed;
  top: 0;
  right: 0;
  height: 100vh;
  height: 100dvh; /* iOS Chrome : suit la hauteur dynamique du viewport */
  width: 100%;
  max-width: 460px;
  z-index: 300; /* au-dessus du navbar et de son menu mobile (200) */
  background: var(--color-bg);
  display: flex;
  flex-direction: column;
  box-shadow: -16px 0 60px rgba(168, 50, 80, 0.2);
  overflow: hidden;
  /* Pas de transform: translateZ ici — sur iOS Chrome ça bloque les events
     tactiles des enfants (boutons, scroll). Le 100dvh suffit pour la stabilité. */
}

/* ── Bouton fermer flottant ── */
.drawer__close-floating {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 30;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: #fff;
  color: var(--gray-700, #374151);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
  border: 1px solid var(--cream-200, #e5e7eb);
  transition: transform 0.12s ease, background 0.12s ease, color 0.12s ease;
  -webkit-tap-highlight-color: transparent;
}
.drawer__close-floating:hover,
.drawer__close-floating:active {
  background: var(--rose-500, var(--rose-500));
  color: #fff;
  transform: scale(1.05);
}

/* Mobile : plus grand, plus tactile */
@media (max-width: 640px) {
  .drawer__close-floating {
    width: 44px;
    height: 44px;
    top: 12px;
    right: 12px;
  }
  /* L'ancien bouton fermer du topbar fait doublon → on le masque */
  .drawer__topbar > .drawer__icon-btn[aria-label="Fermer"],
  .drawer__topbar > .drawer__icon-spacer:last-child {
    display: none;
  }
}

/* ── Topbar (stepper) ── */
.drawer__topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-3) var(--space-4);
  background: #fff;
  border-bottom: 1px solid var(--cream-200);
  gap: var(--space-3);
}

.drawer__icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gray-500);
  transition: all var(--transition-fast);
}
.drawer__icon-btn:hover { background: var(--cream-200); color: var(--rose-500); }
.drawer__icon-spacer { width: 36px; height: 36px; }

.drawer__steps {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-1);
}
.drawer__step-line {
  flex: 0 0 24px;
  height: 1.5px;
  background: var(--cream-300);
  transition: background var(--transition-normal);
}
.drawer__step-line--active { background: var(--rose-500); }

.drawer__stepdot {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.drawer__stepdot-circle {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: var(--cream-200);
  color: var(--gray-400);
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-display);
  font-weight: 600;
  font-size: 0.8125rem;
  transition: all var(--transition-normal);
  border: 1.5px solid transparent;
}
.drawer__stepdot--active .drawer__stepdot-circle {
  background: var(--rose-500);
  color: #fff;
  box-shadow: var(--shadow-rose);
}
.drawer__stepdot--done .drawer__stepdot-circle {
  background: var(--rose-100);
  color: var(--rose-600);
  border-color: var(--rose-300);
}
.drawer__stepdot-label {
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--gray-400);
}
.drawer__stepdot--active .drawer__stepdot-label,
.drawer__stepdot--done .drawer__stepdot-label {
  color: var(--rose-600);
}

/* ── Header ── */
.drawer__header {
  padding: var(--space-5) var(--space-5) var(--space-3);
  margin-top : 3.7rem;
}
.drawer__title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  font-weight: 500;
  color: var(--gray-800);
  letter-spacing: -0.01em;
  margin-top: 4px;
}
.drawer__title em { font-style: italic; color: var(--color-primary); }

/* ── Body ── */
.drawer__body {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  overscroll-behavior: contain;
  padding: 0 var(--space-5) var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

/* ── Overlay : suit aussi le viewport dynamique ── */

/* ── Empty ── */
.drawer__empty {
  text-align: center;
  padding: var(--space-12) var(--space-4);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  color: var(--gray-500);
}
.drawer__empty-icon { font-size: 3rem; opacity: 0.5; }

/* ── Items ── */
.drawer__items {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.drawer__item {
  display: grid;
  grid-template-columns: 64px 1fr auto;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-3);
  background: #fff;
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
  transition: border-color var(--transition-fast);
}
.drawer__item:hover { border-color: var(--rose-200); }

.drawer__item-img {
  width: 64px;
  height: 64px;
  border-radius: var(--radius-sm);
  overflow: hidden;
  background: linear-gradient(145deg, var(--rose-50), var(--cream-100));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
}
.drawer__item-img img { width: 100%; height: 100%; object-fit: cover; }

.drawer__item-info {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.drawer__item-cat {
  font-size: 0.625rem;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--rose-400);
}
.drawer__item-name {
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 500;
  color: var(--gray-800);
  line-height: 1.3;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.drawer__item-price {
  font-family: var(--font-sans);
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--rose-600);
  margin-top: 2px;
}

.drawer__item-actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-2);
}
.drawer__remove {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  color: var(--gray-300);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
}
.drawer__remove:hover { background: #fee2e2; color: #ef4444; }

.drawer__qty {
  display: flex;
  align-items: center;
  gap: 4px;
}
.drawer__qty-btn {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  border: 1.5px solid var(--cream-300);
  background: #fff;
  color: var(--gray-600);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all var(--transition-fast);
}
.drawer__qty-btn:hover:not(:disabled) {
  border-color: var(--rose-400);
  color: var(--rose-500);
  background: var(--rose-50);
}
.drawer__qty-btn--primary {
  background: var(--rose-500);
  border-color: var(--rose-500);
  color: #fff;
}
.drawer__qty-btn--primary:hover:not(:disabled) {
  background: var(--rose-600);
  border-color: var(--rose-600);
}
.drawer__qty-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.drawer__qty-value {
  min-width: 16px;
  text-align: center;
  font-weight: 600;
  font-size: 0.8125rem;
  color: var(--gray-800);
}
.drawer__mini-spin {
  width: 8px; height: 8px;
  border: 1.5px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* ── Hint étape ── */
.drawer__step-hint {
  font-size: 0.8125rem;
  color: var(--gray-400);
  line-height: 1.5;
  padding: var(--space-3) var(--space-4);
  background: var(--cream-50);
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
}

/* ── Sections (étape 3) ── */
.drawer__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  background: #fff;
  border-radius: var(--radius-lg);
  border: 1px solid var(--cream-200);
}
.drawer__section--recap {
  background: var(--cream-50);
}

.drawer__section-title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--gray-700);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--cream-200);
}

/* ── Récapitulatif totaux ── */
.drawer__recap-totals {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-top: var(--space-2);
}
.drawer__recap-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--gray-600);
}
.drawer__recap-line--discount { color: #15803d; }
.drawer__recap-line--total {
  font-family: var(--font-sans);
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-800);
  padding-top: var(--space-2);
  border-top: 1px solid var(--cream-300);
}
.drawer__recap-line--total span:last-child { color: var(--rose-600); }

/* ── Form groups ── */
.drawer__group { display: flex; flex-direction: column; gap: var(--space-2); }

.drawer__section {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  background: #fff;
  border-radius: var(--radius-lg);
  border: 1px solid var(--cream-200);
}
.drawer__section--recap {
  background: var(--cream-50);
}

.drawer__section-title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-family: var(--font-display);
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--gray-700);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--cream-200);
}

/* ── Récapitulatif totaux ── */
.drawer__recap-totals {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-top: var(--space-2);
}
.drawer__recap-line {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--gray-600);
}
.drawer__recap-line--discount { color: #15803d; }
.drawer__recap-line--total {
  font-family: var(--font-sans);
  font-size: 1rem;
  font-weight: 600;
  color: var(--gray-800);
  padding-top: var(--space-2);
  border-top: 1px solid var(--cream-300);
}
.drawer__recap-line--total span:last-child { color: var(--rose-600); }

/* ── Form groups ── */
.drawer__group { display: flex; flex-direction: column; gap: var(--space-2); }

/* Ligne de label pouvant accueillir le bouton « Ma position » à droite. */
.drawer__group-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  flex-wrap: wrap;
}
.drawer__group-head .label { margin-bottom: 0; }

/* Retour de la détection, sous le champ qu'elle vient de remplir. */
.drawer__geo-msg {
  padding: 6px 10px;
  border-radius: var(--radius-sm, 6px);
  font-size: 0.75rem;
  line-height: 1.45;
  background: #f5f5f4;
  color: #57534e;
}
.drawer__geo-msg--success    { background: #dcfce7; color: #15803d; }
.drawer__geo-msg--partial    { background: #fef9c3; color: #854d0e; }
.drawer__geo-msg--error      { background: #fef2f2; color: #dc2626; }

.drawer__coupon {
  display: flex;
  gap: var(--space-2);
}
.drawer__coupon-input { text-transform: uppercase; letter-spacing: 0.05em; flex: 1; }

.drawer__msg {
  font-size: 0.75rem;
  margin-top: 2px;
}
.drawer__msg--error { color: #ef4444; }
.drawer__msg--success { color: #15803d; }
.drawer__msg--center { text-align: center; }

/* ── Notice ── */
.drawer__notice {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  align-items: center;
}
.drawer__notice strong {
  display: block;
  font-size: 0.8125rem;
  margin-bottom: 2px;
}
.drawer__notice p {
  font-size: 0.75rem;
  line-height: 1.5;
}
.drawer__notice > div { flex: 1; }
.drawer__notice--info {
  background: var(--rose-50);
  border: 1px solid var(--rose-100);
}
.drawer__notice--info strong { color: var(--rose-700); }
.drawer__notice--info p { color: var(--rose-600); }
.drawer__notice--warn {
  background: var(--gold-100);
  border: 1px solid var(--gold-200);
}
.drawer__notice--warn strong { color: var(--gold-600); }
.drawer__notice--warn p { color: var(--gold-600); }

/* ── Payments ── */
.drawer__payments {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.drawer__payment {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  cursor: pointer;
  transition: all var(--transition-fast);
  position: relative;
}
.drawer__payment:hover { border-color: var(--rose-300); }
.drawer__payment--active {
  border-color: var(--rose-500);
  background: var(--rose-50);
}
.drawer__payment-input { display: none; }
.drawer__payment-icon {
  width: 36px;
  height: 36px;
  background: var(--cream-100);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.125rem;
  flex-shrink: 0;
}
.drawer__payment--active .drawer__payment-icon { background: #fff; }
.drawer__payment-info { flex: 1; }
.drawer__payment-info strong {
  display: block;
  font-size: 0.875rem;
  color: var(--gray-800);
  font-weight: 600;
}
.drawer__payment-info span {
  font-size: 0.75rem;
  color: var(--gray-400);
}
.drawer__payment-dot {
  width: 18px;
  height: 18px;
  border: 1.5px solid var(--cream-300);
  border-radius: 50%;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}
.drawer__payment--active .drawer__payment-dot {
  border-color: var(--rose-500);
  background: var(--rose-500);
  box-shadow: inset 0 0 0 3px #fff;
}

/* ── CGV ── */
.drawer__cgv {
  display: flex;
  gap: var(--space-3);
  cursor: pointer;
  align-items: flex-start;
  padding: var(--space-2) 0;
}
.drawer__cgv input { flex-shrink: 0; margin-top: 1px; }
.drawer__cgv span {
  font-size: 0.75rem;
  color: var(--gray-500);
  line-height: 1.6;
}
.drawer__cgv a {
  color: var(--rose-600);
  text-decoration: underline;
  text-underline-offset: 2px;
}

/* ── Delivery (step 3) ── */
.drawer__delivery-option {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-3) var(--space-4);
  background: var(--gray-800);
  color: #fff;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}
.drawer__delivery-icon { font-size: 1.125rem; }

.drawer__search { position: relative; }
.drawer__search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  width: 16px;
  height: 16px;
  color: var(--gray-400);
  pointer-events: none;
}
.drawer__search-input { padding-left: 38px; }

.drawer__map {
  width: 100%;
  height: 200px;
  border-radius: var(--radius-md);
  overflow: hidden;
  border: 1px solid var(--cream-300);
  position: relative;
  z-index: 0;
}

.drawer__locate {
  width: 100%;
  justify-content: center;
  padding: 10px 16px;
}

.drawer__address-selected {
  display: flex;
  gap: var(--space-2);
  align-items: flex-start;
  padding: var(--space-3) var(--space-4);
  background: var(--rose-50);
  border: 1px solid var(--rose-200);
  border-radius: var(--radius-md);
  color: var(--rose-700);
}
.drawer__address-selected svg { color: var(--rose-500); flex-shrink: 0; margin-top: 2px; }
.drawer__address-selected p {
  font-size: 0.8125rem;
  line-height: 1.5;
}

.drawer__address-empty {
  text-align: center;
  font-size: 0.75rem;
  color: var(--gray-400);
  padding: var(--space-2) 0;
}

.drawer__textarea { height: 80px; resize: none; }

/* ── Footer ── */
.drawer__footer {
  background: #fff;
  border-top: 1px solid var(--cream-200);
  padding: var(--space-4) var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  box-shadow: 0 -8px 24px rgba(168, 50, 80, 0.06);
}

.drawer__totals {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.drawer__totals > div {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--gray-600);
}
.drawer__amber { color: var(--gold-600); font-weight: 500; }
.drawer__green { color: #15803d; font-weight: 500; }
.drawer__totals-final {
  font-family: var(--font-sans);
  font-size: 1.125rem !important;
  font-weight: 600;
  color: var(--gray-800) !important;
  padding-top: var(--space-2);
  border-top: 1px solid var(--cream-200);
}
.drawer__totals-final span:last-child { color: var(--rose-600); }

.drawer__cta {
  width: 100%;
  justify-content: center;
}

/* ── Auth choice panel ── */
.drawer__choice {
  position: absolute;
  inset: 0;
  background: #fff;
  z-index: 10;
  padding: var(--space-5);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  justify-content: center;
}

.drawer__choice-back {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  color: var(--gray-500);
  font-weight: 500;
  width: fit-content;
  margin-bottom: var(--space-2);
  transition: color var(--transition-fast);
}
.drawer__choice-back:hover { color: var(--rose-500); }

.drawer__choice-title {
  font-family: var(--font-display);
  font-size: 1.25rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: var(--space-2);
}

.drawer__choice-divider {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  color: var(--gray-400);
  font-size: 0.75rem;
  margin: var(--space-2) 0;
}
.drawer__choice-divider::before,
.drawer__choice-divider::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--cream-300);
}

.drawer__choice-card {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-4);
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-lg);
  background: #fff;
  text-align: left;
  cursor: pointer;
  transition: all var(--transition-fast);
  width: 100%;
}
.drawer__choice-card:hover {
  border-color: var(--rose-300);
  background: var(--rose-50);
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(var(--rose-500-rgb), 0.1);
}
.drawer__choice-card--quick {
  border-color: var(--rose-400);
  background: linear-gradient(135deg, var(--rose-50), #fff9fb);
}
.drawer__choice-card--quick:hover {
  border-color: var(--rose-500);
  background: var(--rose-50);
}

.drawer__choice-card__icon {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-md);
  background: var(--cream-100);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.375rem;
  flex-shrink: 0;
}
.drawer__choice-card--quick .drawer__choice-card__icon {
  background: var(--rose-100);
}

.drawer__choice-card__body {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.drawer__choice-card__body strong {
  font-size: 0.9375rem;
  font-weight: 600;
  color: var(--gray-800);
  display: block;
}
.drawer__choice-card__body span {
  font-size: 0.75rem;
  color: var(--gray-400);
}
.drawer__choice-card svg {
  color: var(--gray-300);
  flex-shrink: 0;
  transition: color var(--transition-fast);
}
.drawer__choice-card:hover svg { color: var(--rose-400); }

/* ── Choice slide transition ── */
.choice-slide-enter-active, .choice-slide-leave-active {
  transition: opacity 0.22s ease, transform 0.22s ease;
}
.choice-slide-enter-from { opacity: 0; transform: translateY(8px); }
.choice-slide-leave-to   { opacity: 0; transform: translateY(8px); }

.drawer__spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Transitions ── */
.overlay-enter-active, .overlay-leave-active { transition: opacity 0.25s ease; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }
.drawer-enter-active, .drawer-leave-active { transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1); }
.drawer-enter-from, .drawer-leave-to { transform: translateX(100%); }

@media (max-width: 540px) {
  .drawer__stepdot-label { display: none; }
}
</style>
