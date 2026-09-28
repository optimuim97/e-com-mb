<template>
  <div class="admin-page nl">
    <header class="page-header">
      <div>
        <span class="eyebrow">Marketing</span>
        <h1 class="page-header__title">Newsletter</h1>
      </div>
      <div class="nl-tabs">
        <button :class="{ active: tab === 'campaigns' }" @click="tab = 'campaigns'">Campagnes</button>
        <button :class="{ active: tab === 'subscribers' }" @click="tab = 'subscribers'">
          Abonnés <span v-if="stats.subscribed" class="nl-tabs__count">{{ stats.subscribed }}</span>
        </button>
      </div>
    </header>

    <!-- ── Campagnes ── -->
    <template v-if="tab === 'campaigns'">
      <div class="nl-grid">
        <!-- Composer -->
        <div class="card nl-composer">
          <h3 class="nl-card__title">{{ editing ? 'Modifier la campagne' : 'Nouvelle campagne' }}</h3>
          <label class="label">Objet</label>
          <input v-model="form.subject" class="input" placeholder="🌹 Nos nouveautés du mois" maxlength="200" />

          <label class="label">Texte d'aperçu <span class="hint">(visible dans la boîte de réception)</span></label>
          <input v-model="form.preview_text" class="input" placeholder="Découvrez nos soins à la rose…" maxlength="200" />

          <label class="label">Contenu (HTML autorisé)</label>
          <textarea v-model="form.content" class="input nl-content" rows="10"
            placeholder="<h2>Bonjour 🌹</h2><p>Ce mois-ci...</p>"></textarea>

          <div class="nl-composer__actions">
            <button class="btn btn-primary btn-sm" :disabled="busy || !form.subject || !form.content" @click="saveCampaign">
              {{ editing ? 'Enregistrer' : 'Créer le brouillon' }}
            </button>
            <button v-if="editing" class="btn btn-outline btn-sm" @click="resetForm">Annuler</button>
            <button class="btn btn-outline btn-sm" :disabled="!form.subject || !form.content" @click="showPreview = !showPreview">
              {{ showPreview ? 'Masquer aperçu' : 'Aperçu' }}
            </button>
          </div>

          <!-- Aperçu rendu -->
          <div v-if="showPreview" class="nl-preview">
            <div class="nl-preview__subject">{{ form.subject || '(objet)' }}</div>
            <div class="nl-preview__body" v-html="form.content"></div>
          </div>

          <p v-if="msg" class="nl-msg">{{ msg }}</p>
        </div>

        <!-- Liste campagnes -->
        <div class="card nl-list">
          <h3 class="nl-card__title">Campagnes</h3>
          <div v-if="loadingCampaigns" class="nl-empty">Chargement…</div>
          <div v-else-if="!campaigns.length" class="nl-empty">Aucune campagne. Créez-en une.</div>
          <ul v-else class="nl-campaigns">
            <li v-for="c in campaigns" :key="c.id" class="nl-campaign">
              <div class="nl-campaign__main">
                <strong>{{ c.subject }}</strong>
                <span class="nl-campaign__meta">
                  <span :class="['nl-badge', `nl-badge--${c.status}`]">{{ statusLabel(c.status) }}</span>
                  <template v-if="c.status === 'sent'">· {{ c.sent_count }}/{{ c.recipients_count }} envoyés</template>
                </span>
              </div>
              <div class="nl-campaign__actions">
                <button v-if="c.status !== 'sent'" class="nl-link" @click="editCampaign(c)">Modifier</button>
                <button class="nl-link" @click="testCampaign(c)">Test</button>
                <button v-if="c.status !== 'sent'" class="nl-link nl-link--primary" @click="sendCampaign(c)">Envoyer</button>
                <button v-if="c.status !== 'sent'" class="nl-link nl-link--danger" @click="deleteCampaign(c)">✕</button>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </template>

    <!-- ── Abonnés ── -->
    <template v-else>
      <div class="nl-stats">
        <div class="nl-stat"><span>{{ stats.subscribed }}</span><small>Abonnés actifs</small></div>
        <div class="nl-stat"><span>{{ stats.unsubscribed }}</span><small>Désabonnés</small></div>
        <div class="nl-stat"><span>{{ stats.total }}</span><small>Total</small></div>
      </div>

      <div class="card nl-add">
        <input v-model="newSub.email" class="input" placeholder="Email à ajouter" />
        <input v-model="newSub.name" class="input" placeholder="Nom (facultatif)" />
        <button class="btn btn-primary btn-sm" :disabled="!newSub.email" @click="addSubscriber">Ajouter</button>
      </div>

      <div class="card">
        <input v-model="search" @input="debouncedSubs" class="input nl-search" placeholder="Rechercher par email…" />
        <div class="table-scroll">
        <table class="admin-table">
          <thead><tr><th>Email</th><th>Nom</th><th>Statut</th><th>Source</th><th>Inscrit le</th><th></th></tr></thead>
          <tbody>
            <tr v-for="s in subscribers" :key="s.id">
              <td class="admin-table__mono">{{ s.email }}</td>
              <td>{{ s.name || '—' }}</td>
              <td><span :class="['nl-badge', s.status === 'subscribed' ? 'nl-badge--sent' : 'nl-badge--draft']">{{ s.status === 'subscribed' ? 'Abonné' : 'Désabonné' }}</span></td>
              <td>{{ s.source || '—' }}</td>
              <td>{{ formatDate(s.created_at) }}</td>
              <td><button class="nl-link nl-link--danger" @click="deleteSubscriber(s)">✕</button></td>
            </tr>
          </tbody>
        </table>
        </div>
        <p v-if="!subscribers.length" class="nl-empty">Aucun abonné.</p>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import api from '@/api'

const tab = ref('campaigns')

// ── Campagnes ──
const campaigns = ref([])
const loadingCampaigns = ref(true)
const editing = ref(null)
const showPreview = ref(false)
const busy = ref(false)
const msg = ref('')
const form = reactive({ subject: '', preview_text: '', content: '' })

async function loadCampaigns() {
  loadingCampaigns.value = true
  try {
    const { data } = await api.get('/admin/newsletter/campaigns')
    campaigns.value = data.data ?? data
  } finally {
    loadingCampaigns.value = false
  }
}

function resetForm() {
  editing.value = null
  form.subject = ''; form.preview_text = ''; form.content = ''
  showPreview.value = false
}

function editCampaign(c) {
  editing.value = c.id
  form.subject = c.subject
  form.preview_text = c.preview_text ?? ''
  form.content = c.content
}

async function saveCampaign() {
  busy.value = true; msg.value = ''
  try {
    if (editing.value) {
      await api.put(`/admin/newsletter/campaigns/${editing.value}`, { ...form })
      msg.value = '✓ Campagne enregistrée.'
    } else {
      await api.post('/admin/newsletter/campaigns', { ...form })
      msg.value = '✓ Brouillon créé.'
    }
    resetForm()
    loadCampaigns()
  } catch (e) {
    msg.value = e.response?.data?.message ?? 'Erreur.'
  } finally {
    busy.value = false
  }
}

async function deleteCampaign(c) {
  if (!confirm('Supprimer cette campagne ?')) return
  await api.delete(`/admin/newsletter/campaigns/${c.id}`)
  loadCampaigns()
}

async function testCampaign(c) {
  const email = prompt('Envoyer un test à quelle adresse ?')
  if (!email) return
  try {
    await api.post(`/admin/newsletter/campaigns/${c.id}/test`, { email })
    alert('Email de test envoyé.')
  } catch (e) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

async function sendCampaign(c) {
  if (!confirm(`Envoyer "${c.subject}" à tous les abonnés actifs ?`)) return
  try {
    const { data } = await api.post(`/admin/newsletter/campaigns/${c.id}/send`)
    alert(data.message)
    loadCampaigns()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

// ── Abonnés ──
const subscribers = ref([])
const stats = reactive({ subscribed: 0, unsubscribed: 0, total: 0 })
// Conservée d'une visite à l'autre, comme sur les autres listes.
const search = usePersistedRef('newsletter.search', '')
const newSub = reactive({ email: '', name: '' })

async function loadSubscribers() {
  const { data } = await api.get('/admin/newsletter/subscribers', { params: { search: search.value, per_page: 50 } })
  subscribers.value = data.data?.data ?? []
  Object.assign(stats, data.stats ?? {})
}

let subsTimer = null
function debouncedSubs() {
  clearTimeout(subsTimer)
  subsTimer = setTimeout(loadSubscribers, 300)
}

async function addSubscriber() {
  try {
    await api.post('/admin/newsletter/subscribers', { ...newSub })
    newSub.email = ''; newSub.name = ''
    loadSubscribers()
  } catch (e) {
    alert(e.response?.data?.message ?? 'Erreur.')
  }
}

async function deleteSubscriber(s) {
  if (!confirm(`Supprimer ${s.email} ?`)) return
  await api.delete(`/admin/newsletter/subscribers/${s.id}`)
  loadSubscribers()
}

// ── Helpers ──
const STATUS = { draft: 'Brouillon', sending: 'Envoi en cours', sent: 'Envoyée' }
const statusLabel = (s) => STATUS[s] ?? s
function formatDate(v) {
  return v ? new Date(v).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'
}

onMounted(() => { loadCampaigns(); loadSubscribers() })
</script>

<style scoped>
.nl { display: flex; flex-direction: column; gap: var(--space-5); }
.page-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: var(--space-4); }

.nl-tabs { display: inline-flex; border: 1.5px solid var(--cream-300); border-radius: var(--radius-full); background: #fff; overflow: hidden; }
.nl-tabs button { padding: 8px 18px; background: transparent; border: none; font-size: 0.8125rem; font-weight: 500; color: var(--gray-500); cursor: pointer; }
.nl-tabs button.active { background: var(--rose-500); color: #fff; }
.nl-tabs__count { font-size: 0.6875rem; opacity: 0.8; }

.nl-grid { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-5); align-items: start; }
.nl-card__title { font-family: var(--font-display); font-size: 1rem; font-weight: 600; margin-bottom: var(--space-3); }
.nl-composer, .nl-list { padding: var(--space-5); }
.nl-composer .label { margin-top: var(--space-3); }
.nl-content { font-family: ui-monospace, monospace; font-size: 0.8125rem; resize: vertical; }
.nl-composer__actions { display: flex; gap: var(--space-2); margin-top: var(--space-3); flex-wrap: wrap; }
.nl-msg { font-size: 0.8125rem; color: #15803d; margin-top: var(--space-2); }

.nl-preview { margin-top: var(--space-3); border: 1px solid var(--cream-200); border-radius: var(--radius-md); overflow: hidden; }
.nl-preview__subject { padding: 10px 14px; background: var(--cream-50); font-weight: 600; font-size: 0.875rem; border-bottom: 1px solid var(--cream-200); }
.nl-preview__body { padding: var(--space-4); font-size: 0.875rem; line-height: 1.6; }

.nl-campaigns { list-style: none; display: flex; flex-direction: column; gap: var(--space-2); }
.nl-campaign { display: flex; justify-content: space-between; align-items: center; gap: var(--space-3); padding: var(--space-3); border: 1px solid var(--cream-200); border-radius: var(--radius-md); }
.nl-campaign__main { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.nl-campaign__main strong { font-size: 0.875rem; color: var(--gray-800); }
.nl-campaign__meta { font-size: 0.75rem; color: var(--gray-400); display: flex; align-items: center; gap: 6px; }
.nl-campaign__actions { display: flex; gap: var(--space-2); flex-shrink: 0; }

.nl-badge { font-size: 0.625rem; font-weight: 600; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.04em; }
.nl-badge--draft { background: var(--cream-200); color: var(--gray-600); }
.nl-badge--sending { background: #fef3c7; color: #92400e; }
.nl-badge--sent { background: #dcfce7; color: #15803d; }

.nl-link { background: none; border: none; cursor: pointer; font-size: 0.75rem; font-weight: 500; color: var(--gray-500); }
.nl-link:hover { color: var(--rose-600); }
.nl-link--primary { color: var(--rose-600); }
.nl-link--danger:hover { color: #ef4444; }

.nl-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--space-3); }
.nl-stat { background: #fff; border: 1px solid var(--cream-200); border-radius: var(--radius-md); padding: var(--space-4); text-align: center; }
.nl-stat span { display: block; font-family: var(--font-display); font-size: 1.75rem; font-weight: 600; color: var(--rose-600); }
.nl-stat small { font-size: 0.75rem; color: var(--gray-400); }

.nl-add { padding: var(--space-4); display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap; }
.nl-add .input { flex: 1; min-width: 180px; }
.nl-search { margin-bottom: var(--space-3); }
.nl-empty { text-align: center; color: var(--gray-400); padding: var(--space-6); font-size: 0.875rem; }

@media (max-width: 900px) { .nl-grid { grid-template-columns: 1fr; } }
</style>
