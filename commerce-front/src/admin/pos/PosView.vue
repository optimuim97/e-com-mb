<template>
  <div class="pos">
    <!-- ── Colonne gauche : saisie ── -->
    <div class="pos__left">
      <!-- Origine WhatsApp : la commande est rattachée au message d'origine -->
      <div v-if="waMessage" class="card pos-card pos-origin">
        <div class="pos-origin__head">
          <strong>📲 Message WhatsApp de {{ waMessage.contact_name || formatPhone(waMessage.from_phone) }}</strong>
          <RouterLink :to="{ name: 'admin.whatsapp' }" class="pos-origin__back">← Retour à la file</RouterLink>
        </div>
        <p class="pos-origin__body">{{ waMessage.body }}</p>
        <p v-if="waKnownCustomer" class="pos-origin__known">
          Cliente déjà connue : {{ waKnownCustomer.name }} — {{ waKnownCustomer.orders_count }} commande(s)
        </p>
        <p class="pos-origin__hint">
          La commande créée sera rattachée à ce message, qui quittera la file.
        </p>
      </div>

      <!-- Analyse de message -->
      <div class="card pos-card">
        <h3 class="pos-card__title">🪄 Commande depuis un message</h3>
        <p class="pos-card__hint">Collez le message du client (WhatsApp, SMS…). On en extrait les produits, la quantité et le contact.</p>
        <textarea
          v-model="message"
          class="input pos-textarea"
          rows="3"
          placeholder="Ex. Bonjour, je veux 2 eau de rose et 1 huile de ricin pour Awa au 0707080910"
        ></textarea>
        <button class="btn btn-primary btn-sm" :disabled="parsing || !message.trim()" @click="parseMessage">
          {{ parsing ? 'Analyse…' : 'Analyser le message' }}
        </button>
        <p v-if="parseInfo" class="pos-parse-info">{{ parseInfo }}</p>
      </div>

      <!-- Recherche produit -->
      <div class="card pos-card">
        <h3 class="pos-card__title">Ajouter un produit</h3>
        <input
          v-model="search"
          @input="debouncedSearch"
          class="input"
          type="text"
          placeholder="Rechercher un produit…"
        />

        <!-- Résultats de recherche (liste) -->
        <template v-if="search.trim()">
          <div v-if="searching" class="pos-search-status">Recherche…</div>
          <ul v-else-if="results.length" class="pos-results">
            <li v-for="p in results" :key="p.id" class="pos-result" @click="addItem(p)">
              <img :src="p.images?.[0]?.url || '/placeholder.png'" v-img-fallback class="pos-result__img" :alt="p.name" />
              <div class="pos-result__info">
                <strong>{{ p.name }}</strong>
                <span>{{ fmt(p.price) }} · stock {{ p.stock ?? '—' }}</span>
              </div>
              <span class="pos-result__add">+</span>
            </li>
          </ul>
          <p v-else class="pos-search-status">Aucun produit trouvé.</p>
        </template>

        <!-- Grille de tous les produits (avant recherche) -->
        <template v-else>
          <div v-if="loadingCatalog" class="pos-grid">
            <div v-for="i in 8" :key="i" class="pos-tile pos-tile--skeleton"></div>
          </div>
          <div v-else class="pos-grid">
            <button
              v-for="p in catalog"
              :key="p.id"
              type="button"
              class="pos-tile"
              :class="{ 'pos-tile--out': p.stock === 0 }"
              @click="addItem(p)"
              :title="p.stock === 0 ? 'Rupture de stock' : `Ajouter ${p.name}`"
            >
              <span class="pos-tile__img-wrap">
                <img :src="p.images?.[0]?.url || '/placeholder.png'" v-img-fallback class="pos-tile__img" :alt="p.name" />
                <span v-if="p.stock === 0" class="pos-tile__badge">Épuisé</span>
              </span>
              <span class="pos-tile__name">{{ p.name }}</span>
              <span class="pos-tile__price">{{ fmt(p.price) }}</span>
            </button>
          </div>
        </template>
      </div>
    </div>

    <!-- ── Colonne droite : panier + commande ── -->
    <aside class="pos__right card">
      <h3 class="pos-card__title">🧾 Commande</h3>

      <!-- Client -->
      <div class="pos-customer">
        <input v-model="customer.name"    class="input" placeholder="Nom du client *" />
        <input v-model="customer.phone"   class="input" placeholder="Téléphone" />
        <input v-model="customer.commune" class="input" placeholder="Commune / quartier" />
        <input v-model="customer.email"   class="input" placeholder="Email (facultatif)" />
      </div>

      <!-- Panier -->
      <div v-if="cart.length" class="pos-cart">
        <div v-for="(it, i) in cart" :key="i" class="pos-cart__row">
          <div class="pos-cart__name">{{ it.name }}<small>{{ fmt(it.price) }}</small></div>
          <div class="pos-cart__qty">
            <button @click="it.quantity > 1 && it.quantity--">−</button>
            <span>{{ it.quantity }}</span>
            <button @click="it.quantity++">+</button>
          </div>
          <div class="pos-cart__total">{{ fmt(it.price * it.quantity) }}</div>
          <button class="pos-cart__del" @click="cart.splice(i, 1)" aria-label="Retirer">✕</button>
        </div>
      </div>
      <p v-else class="pos-cart__empty">Aucun article. Ajoutez un produit ou analysez un message.</p>

      <!-- Totaux -->
      <div class="pos-totals">
        <div class="pos-totals__row"><span>Sous-total</span><span>{{ fmt(subtotal) }}</span></div>
        <div class="pos-totals__row">
          <span>Livraison</span>
          <input v-model.number="shippingCost" type="number" min="0" step="100" class="input pos-mini-input" placeholder="0" />
        </div>
        <div class="pos-totals__row">
          <span>Remise</span>
          <input v-model.number="discount" type="number" min="0" step="100" class="input pos-mini-input" placeholder="0" />
        </div>
        <div class="pos-totals__row pos-totals__row--total"><span>Total</span><span>{{ fmt(total) }}</span></div>
      </div>

      <!-- Paiement -->
      <div class="pos-pay">
        <div class="pos-pay__status">
          <button :class="{ active: paymentStatus === 'paid' }"   @click="paymentStatus = 'paid'">✓ Payé</button>
          <button :class="{ active: paymentStatus === 'unpaid' }" @click="paymentStatus = 'unpaid'">⏳ Non payé</button>
        </div>
        <select v-model="paymentMethod" class="input">
          <option value="cash">Espèces</option>
          <option value="wave">Wave</option>
          <option value="orange_money">Orange Money</option>
          <option value="stripe">Carte bancaire</option>
          <option value="cod">À la livraison</option>
        </select>
      </div>

      <input v-model="note" class="input" placeholder="Note (facultatif)" />

      <p v-if="error" class="pos-error">{{ error }}</p>

      <button class="btn btn-primary btn-lg pos-submit" :disabled="!canSubmit || submitting" @click="submit">
        {{ submitting ? 'Création…' : `Créer la commande — ${fmt(total)}` }}
      </button>

      <!-- Succès : reçu -->
      <div v-if="created" class="pos-success">
        <p class="pos-success__title">✓ Commande {{ created.number }} créée</p>
        <div class="pos-success__actions">
          <a v-if="waReceiptLink" :href="waReceiptLink" target="_blank" rel="noopener" class="btn btn-whatsapp btn-sm">
            📲 Envoyer le reçu WhatsApp
          </a>
          <button class="btn btn-outline btn-sm" :disabled="downloading" @click="downloadReceipt">
            {{ downloading ? '…' : '⬇ Reçu PDF' }}
          </button>
          <RouterLink :to="{ name: 'admin.order', params: { id: created.id } }" class="btn btn-outline btn-sm">
            Voir la commande →
          </RouterLink>
        </div>
        <button class="pos-success__new" @click="resetAll">+ Nouvelle commande</button>
      </div>
    </aside>
  </div>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'

const settings = useSettingsStore()
import { ref, reactive, computed, onMounted } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import api from '@/api'
import { adminWhatsAppApi } from '@/admin/whatsapp/whatsapp.api'

const route = useRoute()

// Catalogue affiché en grille avant toute recherche
const catalog        = ref([])
const loadingCatalog = ref(true)

// Message WhatsApp d'origine, quand la caisse est ouverte depuis la file
// (/admin/pos?wa=42). La commande créée sera rattachée à ce message.
const waMessage       = ref(null)
const waKnownCustomer = ref(null)

onMounted(async () => {
  try {
    const { data } = await api.get('/products', { params: { per_page: 24 } })
    catalog.value = data.data ?? []
  } catch {
    catalog.value = []
  } finally {
    loadingCatalog.value = false
  }

  if (route.query.wa) await loadFromWhatsApp(route.query.wa)
})

/**
 * Charge le message, en extrait produits et cliente, et pré-remplit la caisse.
 * L'agent vérifie et corrige : le parseur propose, il ne valide pas.
 */
async function loadFromWhatsApp(id) {
  try {
    const { data: msg } = await adminWhatsAppApi.get(id)
    waMessage.value = msg
    message.value   = msg.body ?? ''

    if (msg.status !== 'pending') {
      parseInfo.value = 'Ce message a déjà été traité — vérifiez avant de créer une seconde commande.'
    }

    const { data } = await adminWhatsAppApi.parse(id)
    waKnownCustomer.value = data.known_customer ?? null

    if (data.customer?.name)  customer.name  = data.customer.name
    if (data.customer?.phone) customer.phone = data.customer.phone
    if (data.known_customer?.email) customer.email = data.known_customer.email

    for (const it of data.items ?? []) {
      addItem({ id: it.product_id, name: it.name, price: it.price }, it.quantity || 1)
    }

    parseInfo.value = data.items?.length
      ? `${data.items.length} produit(s) reconnu(s) dans le message. Vérifiez les quantités.`
      : 'Aucun produit reconnu automatiquement — ajoutez-les via la recherche.'
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Message WhatsApp introuvable.'
  }
}

/** 2250707849883 → +225 07 07 84 98 83 */
function formatPhone(phone) {
  const d = String(phone ?? '').replace(/\D/g, '')
  const local = d.startsWith('225') ? d.slice(3) : d
  return (d.startsWith('225') ? '+225 ' : '') + local.replace(/(\d{2})(?=\d)/g, '$1 ').trim()
}

const message  = ref('')
const parsing  = ref(false)
const parseInfo = ref('')

const search    = ref('')
const results   = ref([])
const searching = ref(false)

const cart = ref([])
const customer = reactive({ name: '', phone: '', commune: '', email: '' })

const shippingCost   = ref(0)
const discount       = ref(0)
const paymentStatus  = ref('paid')
const paymentMethod  = ref('cash')
const note           = ref('')

const submitting = ref(false)
const error      = ref('')
const created    = ref(null)
const downloading = ref(false)

// ── Calculs ──────────────────────────────────────────────────────────────────
const subtotal = computed(() => cart.value.reduce((s, i) => s + i.price * i.quantity, 0))
const total    = computed(() => Math.max(0, subtotal.value - (Number(discount.value) || 0) + (Number(shippingCost.value) || 0)))
const canSubmit = computed(() => customer.name.trim() && cart.value.length > 0)

// ── Recherche produit ────────────────────────────────────────────────────────
let searchTimer = null
function debouncedSearch() {
  clearTimeout(searchTimer)
  if (!search.value.trim()) { results.value = []; return }
  searchTimer = setTimeout(runSearch, 300)
}
async function runSearch() {
  searching.value = true
  try {
    const { data } = await api.get('/products', { params: { search: search.value, per_page: 8 } })
    results.value = data.data ?? []
  } catch {
    results.value = []
  } finally {
    searching.value = false
  }
}

function addItem(p, quantity = 1) {
  const existing = cart.value.find(i => i.product_id === p.id && !i.variant_id)
  if (existing) { existing.quantity += quantity; return }
  cart.value.push({
    product_id: p.id,
    variant_id: null,
    name: p.name,
    price: Number(p.price),
    quantity,
  })
}

// ── Analyse de message ───────────────────────────────────────────────────────
async function parseMessage() {
  parsing.value = true
  parseInfo.value = ''
  error.value = ''
  try {
    const { data } = await api.post('/admin/pos/parse', { message: message.value })
    // Client
    if (data.customer?.name && !customer.name)   customer.name  = data.customer.name
    if (data.customer?.phone && !customer.phone) customer.phone = data.customer.phone
    // Produits
    let added = 0
    for (const it of data.items ?? []) {
      addItem({ id: it.product_id, name: it.name, price: it.price }, it.quantity || 1)
      added++
    }
    parseInfo.value = added
      ? `${added} produit(s) ajouté(s). Vérifiez les quantités avant de valider.`
      : 'Aucun produit reconnu. Ajoutez-les manuellement via la recherche.'
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Analyse impossible.'
  } finally {
    parsing.value = false
  }
}

// ── Création ─────────────────────────────────────────────────────────────────
async function submit() {
  submitting.value = true
  error.value = ''
  try {
    const payload = {
      customer: {
        name:    customer.name.trim(),
        phone:   customer.phone.trim() || null,
        email:   customer.email.trim() || null,
        commune: customer.commune.trim() || null,
      },
      items: cart.value.map(i => ({ product_id: i.product_id, variant_id: i.variant_id, quantity: i.quantity })),
      payment_status: paymentStatus.value,
      payment_method: paymentMethod.value,
      shipping_cost:  Number(shippingCost.value) || 0,
      discount_amount: Number(discount.value) || 0,
      note: note.value.trim() || null,
      whatsapp_message_id: waMessage.value?.id ?? null,
    }
    const { data } = await api.post('/admin/pos/orders', payload)
    created.value = data.data ?? data
  } catch (e) {
    error.value = e.response?.data?.message
      || Object.values(e.response?.data?.errors ?? {})[0]?.[0]
      || 'Erreur lors de la création.'
  } finally {
    submitting.value = false
  }
}

// ── Reçu ─────────────────────────────────────────────────────────────────────
const waReceiptLink = computed(() => {
  if (!created.value) return null
  const phone = (customer.phone || '').replace(/\D/g, '')
  if (!phone) return null
  const lines = cart.value.map(i => `• ${i.name} ×${i.quantity} — ${fmt(i.price * i.quantity)}`).join('\n')
  const payState = paymentStatus.value === 'paid' ? '✅ Payée' : '⏳ À régler'
  const msg = `*${settings.shopName}* — Reçu commande\n\nN° : *${created.value.number}*\n\n${lines}\n\n` +
    (Number(shippingCost.value) ? `Livraison : ${fmt(shippingCost.value)}\n` : '') +
    (Number(discount.value) ? `Remise : -${fmt(discount.value)}\n` : '') +
    `*Total : ${fmt(total.value)}*\nStatut : ${payState}\n\nMerci de votre confiance`
  return `https://wa.me/${phone}?text=${encodeURIComponent(msg)}`
})

async function downloadReceipt() {
  if (!created.value) return
  downloading.value = true
  try {
    const res = await api.get(`/admin/orders/${created.value.id}/invoice`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `recu-${created.value.number}.pdf`
    document.body.appendChild(a); a.click(); a.remove()
    URL.revokeObjectURL(url)
  } catch {
    error.value = 'Impossible de générer le PDF.'
  } finally {
    downloading.value = false
  }
}

function resetAll() {
  message.value = ''; parseInfo.value = ''
  search.value = ''; results.value = []
  cart.value = []
  customer.name = ''; customer.phone = ''; customer.commune = ''; customer.email = ''
  shippingCost.value = 0; discount.value = 0
  paymentStatus.value = 'paid'; paymentMethod.value = 'cash'; note.value = ''
  created.value = null; error.value = ''
  // Le message d'origine est consommé : la commande suivante repart neutre.
  waMessage.value = null; waKnownCustomer.value = null
}

function fmt(v) {
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', maximumFractionDigits: 0 }).format(Number(v) || 0)
}
</script>

<style scoped>
.pos {
  display: grid;
  grid-template-columns: 1fr 420px;
  gap: var(--space-5);
  align-items: start;
}

.pos-card { padding: var(--space-4); margin-bottom: var(--space-4); display: flex; flex-direction: column; gap: var(--space-2); }

/* Origine WhatsApp */
.pos-origin { border-left: 3px solid #25d366; }
.pos-origin__head { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); flex-wrap: wrap; }
.pos-origin__head strong { font-size: 0.9375rem; color: var(--gray-800); }
.pos-origin__back { font-size: 0.8125rem; color: var(--rose-600); text-decoration: none; }
.pos-origin__body {
  font-size: 0.875rem; color: var(--gray-700); white-space: pre-wrap; word-break: break-word;
  background: var(--cream-100); border-radius: var(--radius-md); padding: var(--space-2) var(--space-3);
}
.pos-origin__known { font-size: 0.8125rem; color: #15803d; font-weight: 600; }
.pos-origin__hint  { font-size: 0.75rem; color: var(--gray-500); }
.pos-card__title { font-family: var(--font-display); font-size: 1rem; font-weight: 600; color: var(--gray-800); }
.pos-card__hint { font-size: 0.8125rem; color: var(--gray-500); }
.pos-textarea { resize: vertical; }
.pos-parse-info { font-size: 0.8125rem; color: var(--rose-600); }

/* Recherche */
.pos-search-status { font-size: 0.8125rem; color: var(--gray-400); padding: var(--space-2) 0; }
.pos-results { list-style: none; display: flex; flex-direction: column; gap: 4px; max-height: 360px; overflow-y: auto; }
.pos-result {
  display: flex; align-items: center; gap: var(--space-3);
  padding: 8px; border-radius: var(--radius-md); cursor: pointer;
  transition: background var(--transition-fast);
}
.pos-result:hover { background: var(--rose-50); }
.pos-result__img { width: 40px; height: 40px; border-radius: var(--radius-sm); object-fit: cover; background: var(--cream-100); }
.pos-result__info { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.pos-result__info strong { font-size: 0.875rem; color: var(--gray-800); }
.pos-result__info span { font-size: 0.75rem; color: var(--gray-400); }
.pos-result__add { width: 26px; height: 26px; border-radius: 50%; background: var(--rose-500); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0; }

/* Grille produits (avant recherche) */
.pos-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
  gap: var(--space-2);
  max-height: 460px;
  overflow-y: auto;
  padding-top: 4px;
}
.pos-tile {
  display: flex; flex-direction: column; gap: 4px;
  padding: 6px; border: 1px solid var(--cream-200); border-radius: var(--radius-md);
  background: #fff; cursor: pointer; text-align: left;
  transition: all var(--transition-fast);
}
.pos-tile:hover { border-color: var(--rose-400); box-shadow: 0 4px 12px rgba(var(--rose-500-rgb),0.1); transform: translateY(-1px); }
.pos-tile--out { opacity: 0.55; }
.pos-tile__img-wrap { position: relative; aspect-ratio: 1; border-radius: var(--radius-sm); overflow: hidden; background: var(--cream-100); }
.pos-tile__img { width: 100%; height: 100%; object-fit: cover; }
.pos-tile__badge {
  position: absolute; top: 4px; left: 4px;
  background: rgba(0,0,0,0.65); color: #fff; font-size: 0.5625rem; font-weight: 600;
  padding: 2px 6px; border-radius: 999px;
}
.pos-tile__name {
  font-size: 0.75rem; font-weight: 500; color: var(--gray-800); line-height: 1.25;
  display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.pos-tile__price { font-size: 0.75rem; font-weight: 700; color: var(--rose-600); }
.pos-tile--skeleton { aspect-ratio: 3/4; background: var(--cream-100); border: none; animation: pulse 1.5s ease-in-out infinite; }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }

/* Colonne droite */
.pos__right {
  padding: var(--space-5);
  display: flex; flex-direction: column; gap: var(--space-4);
  position: sticky; top: calc(var(--navbar-height, 80px) + var(--space-4));
}
.pos-customer { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-2); }

.pos-cart { display: flex; flex-direction: column; gap: var(--space-2); }
.pos-cart__row { display: grid; grid-template-columns: 1fr auto auto auto; gap: var(--space-2); align-items: center; }
.pos-cart__name { font-size: 0.875rem; color: var(--gray-800); display: flex; flex-direction: column; }
.pos-cart__name small { font-size: 0.6875rem; color: var(--gray-400); }
.pos-cart__qty { display: inline-flex; align-items: center; gap: 6px; }
.pos-cart__qty button { width: 24px; height: 24px; border-radius: 50%; border: 1px solid var(--cream-300); background: #fff; cursor: pointer; }
.pos-cart__qty span { min-width: 20px; text-align: center; font-weight: 600; font-size: 0.875rem; }
.pos-cart__total { font-weight: 600; font-size: 0.875rem; color: var(--gray-800); white-space: nowrap; }
.pos-cart__del { color: var(--gray-400); cursor: pointer; background: none; border: none; }
.pos-cart__del:hover { color: #ef4444; }
.pos-cart__empty { font-size: 0.8125rem; color: var(--gray-400); text-align: center; padding: var(--space-3) 0; }

.pos-totals { display: flex; flex-direction: column; gap: 6px; padding: var(--space-3) 0; border-top: 1px solid var(--cream-200); }
.pos-totals__row { display: flex; justify-content: space-between; align-items: center; font-size: 0.875rem; color: var(--gray-600); }
.pos-totals__row--total { font-weight: 700; font-size: 1rem; color: var(--gray-900); padding-top: 6px; border-top: 1px solid var(--cream-200); }
.pos-totals__row--total span:last-child { color: var(--rose-600); }
.pos-mini-input { width: 110px; text-align: right; padding: 6px 10px; font-size: 0.8125rem; }

.pos-pay { display: flex; flex-direction: column; gap: var(--space-2); }
.pos-pay__status { display: flex; gap: var(--space-2); }
.pos-pay__status button {
  flex: 1; padding: 10px; border-radius: var(--radius-md);
  border: 1.5px solid var(--cream-300); background: #fff; cursor: pointer;
  font-size: 0.8125rem; font-weight: 600; color: var(--gray-600);
}
.pos-pay__status button.active { border-color: var(--rose-500); background: var(--rose-50); color: var(--rose-700); }

.pos-error { color: #b91c1c; font-size: 0.8125rem; }
.pos-submit { width: 100%; justify-content: center; }

.pos-success {
  background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md);
  padding: var(--space-4); display: flex; flex-direction: column; gap: var(--space-3);
}
.pos-success__title { color: #15803d; font-weight: 600; }
.pos-success__actions { display: flex; flex-wrap: wrap; gap: var(--space-2); }
.btn-whatsapp { background: #25d366; color: #fff; }
.pos-success__new { color: var(--rose-600); font-size: 0.8125rem; font-weight: 600; background: none; border: none; cursor: pointer; align-self: flex-start; }

@media (max-width: 1024px) {
  .pos { grid-template-columns: 1fr; }
  .pos__right { position: static; }
}
@media (max-width: 480px) {
  .pos-customer { grid-template-columns: 1fr; }
}
</style>
