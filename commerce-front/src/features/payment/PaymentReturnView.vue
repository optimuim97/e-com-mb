<template>
  <main class="pay-return">
    <div class="pay-return__card">
      <!-- Loading -->
      <template v-if="checking">
        <div class="pay-return__icon pay-return__icon--spin"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg></div>
        <h1 class="display-sm">{{ $t('paymentReturn.checkingTitle') }}</h1>
        <p class="pay-return__desc">{{ $t('paymentReturn.checkingDesc') }}</p>
        <div class="pay-return__spinner"></div>
      </template>

      <!-- Succès -->
      <template v-else-if="paid">
        <div class="pay-return__icon pay-return__icon--ok"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/></svg></div>
        <h1 class="display-sm">{{ $t('paymentReturn.paidTitle') }}</h1>
        <p class="pay-return__desc">
          {{ $t('paymentReturn.paidDescBefore') }} <strong>{{ orderNumber }}</strong> {{ $t('paymentReturn.paidDescAfter') }}<br>
          {{ $t('paymentReturn.paidDescMore') }}
        </p>
        <RouterLink :to="`/orders/${orderNumber}`" class="btn btn-primary">
          {{ $t('paymentReturn.trackOrder') }}
        </RouterLink>
      </template>

      <!-- En attente (paiement en cours de vérification) -->
      <template v-else-if="pending">
        <div class="pay-return__icon pay-return__icon--wait"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
        <h1 class="display-sm">{{ $t('paymentReturn.pendingTitle') }}</h1>
        <p class="pay-return__desc">
          {{ $t('paymentReturn.pendingDesc') }}
        </p>
        <RouterLink :to="`/orders/${orderNumber}`" class="btn btn-primary">
          {{ $t('paymentReturn.viewOrder') }}
        </RouterLink>
      </template>

      <!-- Échec -->
      <template v-else>
        <div class="pay-return__icon pay-return__icon--err"><svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg></div>
        <h1 class="display-sm">{{ $t('paymentReturn.failedTitle') }}</h1>
        <p class="pay-return__desc">
          {{ $t('paymentReturn.failedDesc') }}
        </p>
        <div class="pay-return__actions">
          <RouterLink :to="`/orders/${orderNumber}`" class="btn btn-outline">
            {{ $t('paymentReturn.viewOrder') }}
          </RouterLink>
          <RouterLink to="/" class="btn btn-primary">
            {{ $t('paymentReturn.backHome') }}
          </RouterLink>
        </div>
      </template>
    </div>
  </main>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import api from '@/api'

const route       = useRoute()
const router      = useRouter()
const orderNumber = route.query.order
const checking    = ref(true)
const paid        = ref(false)
const pending     = ref(false)

onMounted(async () => {
  if (!orderNumber) { checking.value = false; return }

  // Annulation explicite (ex: Stripe Checkout abandonné) → échec immédiat,
  // pas la peine d'interroger le backend.
  if (route.query.status === 'cancelled') { checking.value = false; return }

  // Interroger le backend pour le statut (max 3 tentatives, 2s entre chaque).
  // Les endpoints /status renvoient tous le statut de la commande.
  async function fetchStatus() {
    for (const provider of ['stripe', 'geniuspay']) {
      try {
        const { data } = await api.get(`/payment/${provider}/status/${orderNumber}`)
        return data
      } catch {
        // provider suivant
      }
    }
    return null
  }

  for (let i = 0; i < 3; i++) {
    const data = await fetchStatus()
    if (data) {
      if (data.paid) { paid.value = true; break }
      else if (['payment_failed', 'cancelled'].includes(data.status)) { break }
      else { pending.value = true; break }
    }
    if (i < 2) await new Promise(r => setTimeout(r, 2000))
  }

  checking.value = false

  // Paiement confirmé → on ramène l'utilisateur sur le détail de SA commande
  // après un court instant (il voit le message de succès puis est redirigé).
  if (paid.value && orderNumber) {
    setTimeout(() => {
      router.push({ name: 'order', params: { number: orderNumber } })
    }, 2500)
  }
})
</script>

<style scoped>
.pay-return {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-8) var(--space-4);
  background: var(--gray-50);
}

.pay-return__card {
  background: white;
  border-radius: var(--radius-2xl);
  padding: var(--space-12) var(--space-8);
  text-align: center;
  max-width: 480px;
  width: 100%;
  box-shadow: 0 8px 32px rgba(0,0,0,0.08);
}

.pay-return__icon {
  margin-bottom: var(--space-4);
  display: flex;
  justify-content: center;
  color: var(--gray-400);
}
.pay-return__icon--ok   { color: #15803d; }
.pay-return__icon--err  { color: #ef4444; }
.pay-return__icon--wait { color: var(--gold-600, #b45309); }

.pay-return__icon--spin svg {
  animation: spin 2s linear infinite;
}

@keyframes spin { to { transform: rotate(360deg); } }

.pay-return__desc {
  color: var(--gray-600);
  margin: var(--space-4) 0 var(--space-6);
  line-height: 1.6;
}

.pay-return__spinner {
  width: 36px;
  height: 36px;
  border: 3px solid var(--gray-200);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: var(--space-4) auto 0;
}

.pay-return__actions {
  display: flex;
  gap: var(--space-3);
  justify-content: center;
  flex-wrap: wrap;
}
</style>
