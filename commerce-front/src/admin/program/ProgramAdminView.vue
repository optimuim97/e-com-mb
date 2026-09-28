<template>
  <div class="program-admin">

    <!-- Header -->
    <div class="program-admin__header">
      <div>
        <h1 class="page-title">Club fidélité</h1>
        <p class="page-subtitle">Gestion du programme de fidélité</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid" v-if="stats">
      <div class="stat-card card">
        <p class="stat-card__value">{{ stats.members_total ?? 0 }}</p>
        <p class="stat-card__label">Membres au total</p>
      </div>
      <div class="stat-card card">
        <p class="stat-card__value">{{ stats.members_bronze ?? 0 }}</p>
        <p class="stat-card__label">🥉 Bronze</p>
      </div>
      <div class="stat-card card">
        <p class="stat-card__value">{{ stats.members_silver ?? 0 }}</p>
        <p class="stat-card__label">🥈 Argent</p>
      </div>
      <div class="stat-card card">
        <p class="stat-card__value">{{ stats.members_gold ?? 0 }}</p>
        <p class="stat-card__label">🥇 Or</p>
      </div>
      <div class="stat-card card">
        <p class="stat-card__value">{{ stats.consultations_pending ?? 0 }}</p>
        <p class="stat-card__label">Consultations en attente</p>
      </div>
    </div>

    <!-- Onglets -->
    <div class="tabs">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        @click="activeTab = tab.key"
        class="tab"
        :class="{ 'tab--active': activeTab === tab.key }"
      >
        {{ tab.icon }} {{ tab.label }}
        <span v-if="tab.badge" class="tab-badge">{{ tab.badge }}</span>
      </button>
    </div>

    <!-- ── Onglet : Membres ── -->
    <div v-if="activeTab === 'members'" class="tab-content">
      <div class="tab-toolbar">
        <input v-model="memberSearch" type="text" class="input search-input" placeholder="Rechercher un membre…" />
        <select v-model="memberTierFilter" class="input input--sm">
          <option value="">Tous les niveaux</option>
          <option value="bronze">🥉 Bronze</option>
          <option value="silver">🥈 Argent</option>
          <option value="gold">🥇 Or</option>
        </select>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Membre</th>
              <th>Niveau</th>
              <th>Points</th>
              <th>Commandes</th>
              <th>Depuis le</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="membersLoading">
              <td colspan="6" class="table-empty">Chargement…</td>
            </tr>
            <tr v-else-if="!filteredMembers.length">
              <td colspan="6" class="table-empty">Aucun membre trouvé.</td>
            </tr>
            <tr v-for="m in filteredMembers" :key="m.id">
              <td>
                <div class="member-cell">
                  <span class="member-cell__avatar">{{ initials(m.user?.name) }}</span>
                  <div>
                    <p class="member-cell__name">{{ m.user?.name }}</p>
                    <p class="member-cell__email">{{ m.user?.email }}</p>
                  </div>
                </div>
              </td>
              <td>
                <span class="tier-badge" :class="`tier-badge--${m.tier}`">
                  {{ tierLabel(m.tier) }}
                </span>
              </td>
              <td class="font-mono font-semibold">{{ m.points }}</td>
              <td>{{ m.orders_count ?? 0 }}</td>
              <td class="text-gray-400 text-sm">{{ formatDate(m.created_at) }}</td>
              <td>
                <button @click="openMemberEdit(m)" class="btn btn-ghost btn-xs">Modifier points</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Onglet : Consultations ── -->
    <div v-if="activeTab === 'consultations'" class="tab-content">
      <div class="tab-toolbar">
        <select v-model="consultStatusFilter" class="input input--sm">
          <option value="">Tous les statuts</option>
          <option v-for="s in CONSULTATION_STATUS_OPTIONS" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </div>

      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Client</th>
              <th>Type</th>
              <th>Canal</th>
              <th>Créneau</th>
              <th>Message</th>
              <th>Statut</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="consultationsLoading">
              <td colspan="7" class="table-empty">Chargement…</td>
            </tr>
            <tr v-else-if="!filteredConsultations.length">
              <td colspan="7" class="table-empty">Aucune consultation.</td>
            </tr>
            <tr v-for="c in filteredConsultations" :key="c.id">
              <td>
                <div class="member-cell">
                  <span class="member-cell__avatar">{{ initials(c.user?.name) }}</span>
                  <div>
                    <p class="member-cell__name">{{ c.user?.name }}</p>
                    <p class="member-cell__email">{{ c.phone || c.user?.email }}</p>
                  </div>
                </div>
              </td>
              <td>{{ consultTypeLabel(c.type) }}</td>
              <td>{{ consultChannelLabel(c.channel) }}</td>
              <td class="text-sm text-gray-500">{{ c.preferred_at ? formatDate(c.preferred_at) : '—' }}</td>
              <td class="text-sm text-gray-600 max-w-xs truncate" :title="c.message">{{ c.message || '—' }}</td>
              <td>
                <span class="consult-status" :class="`consult-status--${c.status}`">
                  {{ statusLabel(c.status) }}
                </span>
              </td>
              <td>
                <select
                  :value="c.status"
                  @change="updateConsultStatus(c, $event.target.value)"
                  class="input input--xs"
                >
                  <option v-for="s in CONSULTATION_STATUS_OPTIONS" :key="s.value" :value="s.value">
                    {{ s.label }}
                  </option>
                </select>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ── Onglet : Avantages ── -->
    <div v-if="activeTab === 'benefits'" class="tab-content">
      <div class="tab-toolbar">
        <button @click="openBenefitForm(null)" class="btn btn-primary btn-sm">+ Ajouter un avantage</button>
        <select v-model="benefitTierFilter" class="input input--sm">
          <option value="">Tous les niveaux</option>
          <option value="bronze">🥉 Bronze</option>
          <option value="silver">🥈 Argent</option>
          <option value="gold">🥇 Or</option>
        </select>
      </div>

      <div class="benefits-grid">
        <div v-if="benefitsLoading" class="table-empty">Chargement…</div>
        <template v-else>
          <div
            v-for="b in filteredBenefits"
            :key="b.id"
            class="benefit-card card"
            :class="{ 'benefit-card--inactive': !b.is_active }"
          >
            <div class="benefit-card__top">
              <span class="benefit-card__icon">{{ b.icon || '✨' }}</span>
              <span class="tier-badge tier-badge--sm" :class="`tier-badge--${b.tier}`">{{ tierLabel(b.tier) }}</span>
            </div>
            <p class="benefit-card__title">{{ b.title }}</p>
            <p v-if="b.description" class="benefit-card__desc">{{ b.description }}</p>
            <div class="benefit-card__actions">
              <button @click="openBenefitForm(b)" class="btn btn-ghost btn-xs">Modifier</button>
              <button @click="deleteBenefit(b.id)" class="btn btn-ghost btn-xs text-red-500">Supprimer</button>
              <button @click="toggleBenefit(b)" class="btn btn-ghost btn-xs">
                {{ b.is_active ? 'Désactiver' : 'Activer' }}
              </button>
            </div>
          </div>

          <button @click="openBenefitForm(null)" class="benefit-add-card card">
            <span class="text-2xl">+</span>
            <span class="text-sm text-gray-400">Ajouter un avantage</span>
          </button>
        </template>
      </div>
    </div>

    <!-- ── Onglet : Niveaux ── -->
    <div v-if="activeTab === 'tiers'" class="tab-content">
      <div class="tiers-admin-grid">
        <div v-for="tier in tiers" :key="tier.id ?? tier.slug" class="tier-admin-card card">
          <div class="tier-admin-card__header" :class="`tier-admin-header--${tier.slug}`">
            <span class="tier-admin-card__icon">{{ tier.icon }}</span>
            <h3 class="tier-admin-card__name">{{ tier.name }}</h3>
          </div>
          <div class="tier-admin-card__body">
            <FormField :def="TIER_FIELDS.points_min" :error="''">
              <input
                v-model.number="tier.points_min"
                type="number" min="0" class="input"
                @change="saveTier(tier)"
              />
            </FormField>
            <FormField :def="TIER_FIELDS.points_max" :error="''">
              <input
                v-model.number="tier.points_max"
                type="number" min="0" class="input"
                placeholder="∞ (illimité)"
                @change="saveTier(tier)"
              />
            </FormField>
            <FormField :def="TIER_FIELDS.discount_pct" :error="''">
              <div class="input-group">
                <input
                  v-model.number="tier.discount_pct"
                  type="number" min="0" max="100" class="input"
                  @change="saveTier(tier)"
                />
                <span class="input-group__addon">%</span>
              </div>
            </FormField>
            <p v-if="tier._saved" class="text-green-600 text-xs">✓ Sauvegardé</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Modal : avantage ── -->
    <Teleport to="body">
      <div v-if="showBenefitModal" class="modal-overlay" @click.self="showBenefitModal = false">
        <div class="modal-card card">
          <div class="modal-card__header">
            <h3>{{ editingBenefit?.id ? 'Modifier l\'avantage' : 'Nouvel avantage' }}</h3>
            <button @click="showBenefitModal = false" class="modal-card__close">✕</button>
          </div>
          <form @submit.prevent="saveBenefit" class="benefit-form">
            <FormField :def="BENEFIT_FIELDS.icon" :error="benefitErrors.icon" optional>
              <div class="emoji-input-wrap">
                <input v-model="benefitForm.icon" type="text" class="input" placeholder="✨" maxlength="4" />
                <span class="emoji-preview">{{ benefitForm.icon || '✨' }}</span>
              </div>
            </FormField>

            <FormField :def="BENEFIT_FIELDS.title" :error="benefitErrors.title">
              <input v-model="benefitForm.title" type="text" class="input" placeholder="Ex : 10% de réduction" required />
            </FormField>

            <FormField :def="BENEFIT_FIELDS.description" :error="benefitErrors.description" optional>
              <textarea v-model="benefitForm.description" rows="2" class="input resize-none"
                placeholder="Description optionnelle…"></textarea>
            </FormField>

            <FormField :def="BENEFIT_FIELDS.tier" :error="benefitErrors.tier">
              <AppSelect v-model="benefitForm.tier" :options="TIER_OPTIONS" />
            </FormField>

            <label class="toggle-row">
              <span class="label">{{ BENEFIT_FIELDS.is_active.label }}</span>
              <button
                type="button"
                @click="benefitForm.is_active = !benefitForm.is_active"
                class="toggle-btn"
                :class="{ 'toggle-btn--on': benefitForm.is_active }"
              >
                <span class="toggle-btn__thumb"></span>
              </button>
            </label>

            <p v-if="benefitSaveError" class="text-red-500 text-sm">{{ benefitSaveError }}</p>

            <div class="modal-actions">
              <button type="button" @click="showBenefitModal = false" class="btn btn-ghost">Annuler</button>
              <button type="submit" :disabled="benefitSaving" class="btn btn-primary">
                {{ benefitSaving ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>

    <!-- ── Modal : modifier points membre ── -->
    <Teleport to="body">
      <div v-if="showMemberModal" class="modal-overlay" @click.self="showMemberModal = false">
        <div class="modal-card card">
          <div class="modal-card__header">
            <h3>Modifier les points de {{ editingMember?.user?.name }}</h3>
            <button @click="showMemberModal = false" class="modal-card__close">✕</button>
          </div>
          <div class="benefit-form">
            <div>
              <label class="label">Points actuels</label>
              <p class="font-mono font-bold text-2xl text-rose-600">{{ editingMember?.points ?? 0 }}</p>
            </div>
            <div>
              <label class="label">Nouveau solde de points</label>
              <input v-model.number="newPoints" type="number" min="0" class="input" />
            </div>
            <div>
              <label class="label">Niveau manuel</label>
              <AppSelect v-model="newTier" :options="TIER_OPTIONS" />
            </div>
            <div class="modal-actions">
              <button @click="showMemberModal = false" class="btn btn-ghost">Annuler</button>
              <button @click="saveMember" :disabled="memberSaving" class="btn btn-primary">
                {{ memberSaving ? 'Enregistrement…' : 'Enregistrer' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import FormField    from '@/shared/components/ui/FormField.vue'
import AppSelect    from '@/components/ui/AppSelect.vue'
import { adminProgramApi } from './program.api'
import {
  BENEFIT_FIELDS, TIER_FIELDS,
  CONSULTATION_STATUS_OPTIONS, TIER_OPTIONS,
  makeBenefitForm, mapErrors,
} from './program.fields'

// ── Onglets ──────────────────────────────────────────────────────────────────
const activeTab = ref('members')
const tabs = computed(() => [
  { key: 'members',       icon: '👥', label: 'Membres' },
  { key: 'consultations', icon: '💬', label: 'Consultations', badge: stats.value?.consultations_pending || null },
  { key: 'benefits',      icon: '🎁', label: 'Avantages' },
  { key: 'tiers',         icon: '🏆', label: 'Niveaux' },
])

// ── Stats ────────────────────────────────────────────────────────────────────
const stats = ref(null)

// ── Membres ───────────────────────────────────────────────────────────────
const members       = ref([])
const membersLoading= ref(false)
// Conservés d'une visite à l'autre, comme sur les autres listes.
const memberSearch     = usePersistedRef('program.members.search', '')
const memberTierFilter = usePersistedRef('program.members.tier', '')

const filteredMembers = computed(() => {
  let list = members.value
  if (memberTierFilter.value) list = list.filter(m => m.tier === memberTierFilter.value)
  if (memberSearch.value.trim()) {
    const q = memberSearch.value.toLowerCase()
    list = list.filter(m =>
      m.user?.name?.toLowerCase().includes(q) ||
      m.user?.email?.toLowerCase().includes(q)
    )
  }
  return list
})

// ── Member edit modal ────────────────────────────────────────────────────────
const showMemberModal = ref(false)
const editingMember   = ref(null)
const newPoints       = ref(0)
const newTier         = ref('bronze')
const memberSaving    = ref(false)

function openMemberEdit(m) {
  editingMember.value = m
  newPoints.value = m.points
  newTier.value   = m.tier
  showMemberModal.value = true
}
async function saveMember() {
  memberSaving.value = true
  try {
    const { data } = await adminProgramApi.updateMember(editingMember.value.id, {
      points: newPoints.value,
      tier:   newTier.value,
    })
    const idx = members.value.findIndex(m => m.id === editingMember.value.id)
    if (idx !== -1) members.value[idx] = { ...members.value[idx], ...data.membership ?? data }
    showMemberModal.value = false
  } catch (e) {
    console.error(e)
  } finally {
    memberSaving.value = false
  }
}

// ── Consultations ─────────────────────────────────────────────────────────
const consultations       = ref([])
const consultationsLoading= ref(false)
const consultStatusFilter = ref('')

const filteredConsultations = computed(() => {
  if (!consultStatusFilter.value) return consultations.value
  return consultations.value.filter(c => c.status === consultStatusFilter.value)
})

async function updateConsultStatus(consultation, newStatus) {
  try {
    await adminProgramApi.updateConsultation(consultation.id, { status: newStatus })
    consultation.status = newStatus
    if (stats.value && newStatus !== 'pending') {
      stats.value.consultations_pending = Math.max(0, (stats.value.consultations_pending ?? 0) - 1)
    }
  } catch (e) {
    console.error(e)
  }
}

// ── Avantages ─────────────────────────────────────────────────────────────
const benefits       = ref([])
const benefitsLoading= ref(false)
const benefitTierFilter = ref('')

const filteredBenefits = computed(() => {
  if (!benefitTierFilter.value) return benefits.value
  return benefits.value.filter(b => b.tier === benefitTierFilter.value)
})

const showBenefitModal = ref(false)
const editingBenefit   = ref(null)
const benefitForm      = ref(makeBenefitForm())
const benefitErrors    = ref({})
const benefitSaving    = ref(false)
const benefitSaveError = ref('')

function openBenefitForm(benefit) {
  editingBenefit.value = benefit
  benefitForm.value    = benefit
    ? { title: benefit.title, description: benefit.description ?? '', tier: benefit.tier, icon: benefit.icon ?? '', is_active: benefit.is_active }
    : makeBenefitForm()
  benefitErrors.value  = {}
  benefitSaveError.value = ''
  showBenefitModal.value = true
}

async function saveBenefit() {
  benefitSaving.value = true
  benefitSaveError.value = ''
  benefitErrors.value = {}
  try {
    if (editingBenefit.value?.id) {
      const { data } = await adminProgramApi.updateBenefit(editingBenefit.value.id, benefitForm.value)
      const idx = benefits.value.findIndex(b => b.id === editingBenefit.value.id)
      if (idx !== -1) benefits.value[idx] = data.benefit ?? data
    } else {
      const { data } = await adminProgramApi.createBenefit(benefitForm.value)
      benefits.value.unshift(data.benefit ?? data)
    }
    showBenefitModal.value = false
  } catch (e) {
    if (e.response?.status === 422) {
      benefitErrors.value = mapErrors(BENEFIT_FIELDS, e.response.data?.errors ?? {})
    }
    benefitSaveError.value = e.response?.data?.message ?? 'Erreur lors de la sauvegarde.'
  } finally {
    benefitSaving.value = false
  }
}

async function deleteBenefit(id) {
  if (!confirm('Supprimer cet avantage ?')) return
  try {
    await adminProgramApi.deleteBenefit(id)
    benefits.value = benefits.value.filter(b => b.id !== id)
  } catch (e) { console.error(e) }
}

async function toggleBenefit(benefit) {
  try {
    const { data } = await adminProgramApi.updateBenefit(benefit.id, { is_active: !benefit.is_active })
    benefit.is_active = data.benefit?.is_active ?? !benefit.is_active
  } catch (e) { console.error(e) }
}

// ── Niveaux ────────────────────────────────────────────────────────────────
const tiers = ref([
  { slug: 'bronze', name: 'Club Bronze', icon: '🥉', points_min: 0,    points_max: 499,  discount_pct: 5  },
  { slug: 'silver', name: 'Club Argent', icon: '🥈', points_min: 500,  points_max: 1999, discount_pct: 10 },
  { slug: 'gold',   name: 'Club Or',     icon: '🥇', points_min: 2000, points_max: null, discount_pct: 15 },
])

async function saveTier(tier) {
  try {
    await adminProgramApi.updateTier(tier.id ?? tier.slug, {
      points_min:   tier.points_min,
      points_max:   tier.points_max,
      discount_pct: tier.discount_pct,
    })
    tier._saved = true
    setTimeout(() => { tier._saved = false }, 2000)
  } catch (e) { console.error(e) }
}

// ── Helpers ────────────────────────────────────────────────────────────────
const tierLabel = (t) => ({ bronze: '🥉 Bronze', silver: '🥈 Argent', gold: '🥇 Or' }[t] ?? t)

const consultTypeLabel    = (v) => ({ skincare: '🌿 Soin de peau', product: '💄 Conseil produit', routine: '✨ Routine', gift: '🎁 Cadeau', other: '💬 Autre' }[v] ?? v)
const consultChannelLabel = (v) => ({ whatsapp: '💬 WhatsApp', video: '📹 Vidéo', email: '📧 Email', store: '🏪 Boutique' }[v] ?? v)
const statusLabel = (s) => CONSULTATION_STATUS_OPTIONS.find(o => o.value === s)?.label ?? s

function initials(name) {
  if (!name) return '?'
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase()
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(dt))
}

// ── Init ────────────────────────────────────────────────────────────────────
onMounted(async () => {
  const [statsRes, membersRes, consultRes, benefitsRes, tiersRes] = await Promise.allSettled([
    adminProgramApi.getStats(),
    adminProgramApi.getMembers(),
    adminProgramApi.getConsultations(),
    adminProgramApi.getBenefits(),
    adminProgramApi.getTiers(),
  ])

  if (statsRes.status === 'fulfilled')    stats.value        = statsRes.value.data
  if (membersRes.status === 'fulfilled')  members.value      = membersRes.value.data.data ?? membersRes.value.data
  if (consultRes.status === 'fulfilled')  consultations.value= consultRes.value.data.data ?? consultRes.value.data
  if (benefitsRes.status === 'fulfilled') benefits.value     = benefitsRes.value.data.data ?? benefitsRes.value.data
  if (tiersRes.status === 'fulfilled' && tiersRes.value.data.length) tiers.value = tiersRes.value.data
})
</script>

<style scoped>
.program-admin { display: flex; flex-direction: column; gap: var(--space-6); }

.program-admin__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.page-title    { font-family: var(--font-display); font-size: 1.75rem; font-weight: 600; color: var(--gray-800); }
.page-subtitle { color: var(--gray-500); font-size: 0.9375rem; margin-top: 2px; }

/* ── Stats ── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  gap: var(--space-4);
}
.stat-card { padding: var(--space-4) var(--space-5); }
.stat-card__value {
  font-family: var(--font-display);
  font-size: 2rem;
  font-weight: 700;
  color: var(--gray-800);
  line-height: 1;
}
.stat-card__label { font-size: 0.8125rem; color: var(--gray-500); margin-top: 4px; }

/* ── Tabs ── */
.tabs {
  display: flex;
  gap: 4px;
  border-bottom: 2px solid var(--cream-200);
  overflow-x: auto;
}
.tab {
  padding: var(--space-3) var(--space-4);
  font-size: 0.9375rem;
  color: var(--gray-500);
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  white-space: nowrap;
  transition: all var(--transition-fast);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}
.tab:hover { color: var(--gray-800); }
.tab--active { color: var(--rose-600); border-bottom-color: var(--rose-500); font-weight: 500; }
.tab-badge {
  background: var(--rose-500);
  color: #fff;
  border-radius: 99px;
  font-size: 0.6875rem;
  font-weight: 700;
  padding: 1px 7px;
}

/* ── Tab content ── */
.tab-content { display: flex; flex-direction: column; gap: var(--space-5); }
.tab-toolbar {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.search-input { min-width: 240px; }
.input--sm  { width: auto; min-width: 160px; }
.input--xs  { width: auto; min-width: 140px; font-size: 0.8125rem; padding: 4px 8px; }

/* ── Table ── */
.table-wrap { overflow-x: auto; }
.data-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.875rem;
}
.data-table th {
  text-align: left;
  padding: var(--space-3) var(--space-4);
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--gray-500);
  background: var(--cream-50);
  border-bottom: 1px solid var(--cream-200);
  white-space: nowrap;
}
.data-table td {
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--cream-100);
  vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--cream-50); }
.table-empty { text-align: center; color: var(--gray-400); padding: var(--space-10) !important; }

/* ── Member cell ── */
.member-cell { display: flex; align-items: center; gap: var(--space-3); }
.member-cell__avatar {
  width: 36px; height: 36px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--rose-100), var(--rose-200));
  color: var(--rose-700);
  font-size: 0.75rem;
  font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.member-cell__name  { font-weight: 500; color: var(--gray-800); font-size: 0.875rem; }
.member-cell__email { font-size: 0.75rem; color: var(--gray-400); }

/* ── Tier badge ── */
.tier-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 99px; font-size: 0.8125rem; font-weight: 600; }
.tier-badge--sm { font-size: 0.6875rem; padding: 2px 8px; }
.tier-badge--bronze { background: #fef3c7; color: #92400e; }
.tier-badge--silver { background: #f3f4f6; color: #374151; }
.tier-badge--gold   { background: #fef9c3; color: #78350f; }

/* ── Consultation status ── */
.consult-status {
  font-size: 0.75rem;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 99px;
  white-space: nowrap;
}
.consult-status--pending   { background: #fef3c7; color: #92400e; }
.consult-status--confirmed { background: #dcfce7; color: #166534; }
.consult-status--done      { background: #f3f4f6; color: #374151; }
.consult-status--cancelled { background: #fee2e2; color: #991b1b; }

/* ── Benefits grid ── */
.benefits-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: var(--space-4);
}
.benefit-card { padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-3); }
.benefit-card--inactive { opacity: 0.5; }
.benefit-card__top { display: flex; align-items: center; justify-content: space-between; }
.benefit-card__icon { font-size: 1.75rem; }
.benefit-card__title { font-weight: 600; color: var(--gray-800); }
.benefit-card__desc { font-size: 0.8125rem; color: var(--gray-500); }
.benefit-card__actions { display: flex; gap: var(--space-2); flex-wrap: wrap; margin-top: auto; }

.benefit-add-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  min-height: 140px;
  border: 2px dashed var(--cream-300);
  background: var(--cream-50);
  cursor: pointer;
  transition: all var(--transition-fast);
  color: var(--gray-400);
}
.benefit-add-card:hover { border-color: var(--rose-300); background: var(--rose-50); color: var(--rose-500); }

/* ── Tiers admin ── */
.tiers-admin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--space-5);
}
.tier-admin-card { overflow: hidden; }
.tier-admin-card__header {
  padding: var(--space-4) var(--space-5);
  display: flex;
  align-items: center;
  gap: var(--space-3);
}
.tier-admin-header--bronze { background: linear-gradient(135deg, #fef3c7, #fde68a); }
.tier-admin-header--silver { background: linear-gradient(135deg, #f3f4f6, #e5e7eb); }
.tier-admin-header--gold   { background: linear-gradient(135deg, #fef9c3, #fde047); }
.tier-admin-card__icon { font-size: 1.5rem; }
.tier-admin-card__name { font-family: var(--font-display); font-weight: 600; color: var(--gray-800); }
.tier-admin-card__body { padding: var(--space-5); display: flex; flex-direction: column; gap: var(--space-4); }

.input-group { display: flex; align-items: center; }
.input-group .input { border-right: none; border-radius: var(--radius-md) 0 0 var(--radius-md); }
.input-group__addon {
  padding: 0 12px;
  height: 42px;
  display: flex; align-items: center;
  background: var(--cream-100);
  border: 1.5px solid var(--cream-300);
  border-left: none;
  border-radius: 0 var(--radius-md) var(--radius-md) 0;
  color: var(--gray-500);
  font-size: 0.875rem;
}

/* ── Modal ── */
.modal-overlay {
  position: fixed; inset: 0; z-index: 500;
  background: rgba(0,0,0,0.45);
  backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  padding: var(--space-4);
}
.modal-card {
  width: 100%;
  max-width: 480px;
  max-height: 90vh;
  overflow-y: auto;
  padding: 0;
}
.modal-card__header {
  display: flex; align-items: center; justify-content: space-between;
  padding: var(--space-5) var(--space-6);
  border-bottom: 1px solid var(--cream-200);
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  position: sticky; top: 0; background: #fff; z-index: 1;
}
.modal-card__close {
  width: 32px; height: 32px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: var(--gray-400); transition: all var(--transition-fast);
}
.modal-card__close:hover { background: var(--cream-100); color: var(--gray-700); }

.benefit-form { padding: var(--space-6); display: flex; flex-direction: column; gap: var(--space-4); }

.emoji-input-wrap { display: flex; align-items: center; gap: var(--space-3); }
.emoji-preview { font-size: 2rem; line-height: 1; }

.toggle-row { display: flex; align-items: center; justify-content: space-between; cursor: pointer; }
.toggle-btn {
  width: 44px; height: 24px;
  border-radius: 12px;
  background: var(--cream-300);
  position: relative;
  transition: background var(--transition-fast);
  flex-shrink: 0;
}
.toggle-btn--on { background: var(--rose-500); }
.toggle-btn__thumb {
  position: absolute;
  width: 18px; height: 18px;
  background: #fff;
  border-radius: 50%;
  top: 3px; left: 3px;
  transition: left var(--transition-fast);
  box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-btn--on .toggle-btn__thumb { left: calc(100% - 21px); }

.modal-actions { display: flex; gap: var(--space-3); justify-content: flex-end; padding-top: var(--space-2); }

.max-w-xs { max-width: 180px; }
.truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.font-mono { font-family: monospace; }
.font-semibold { font-weight: 600; }
.text-sm { font-size: 0.875rem; }
.text-xs { font-size: 0.75rem; }
.text-gray-400 { color: var(--gray-400); }
.text-gray-500 { color: var(--gray-500); }
.text-gray-600 { color: var(--gray-600); }
.text-green-600 { color: #16a34a; }
.text-red-500 { color: #ef4444; }
.text-2xl { font-size: 1.5rem; }
.mt-2 { margin-top: 8px; }
.w-full { width: 100%; }
</style>
