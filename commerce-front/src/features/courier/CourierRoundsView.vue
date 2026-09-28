<template>
  <div class="liv">
    <header class="liv__head">
      <div>
        <span class="liv__eyebrow">Livraisons</span>
        <h1 class="liv__title">
          <template v-if="tournee">{{ tournee.code }}</template>
          <template v-else>Mes tournées</template>
        </h1>
        <p v-if="!tournee" class="liv__who">{{ auth.user?.name }}</p>
        <p v-else-if="tournee.label || tournee.zone_name" class="liv__who">
          {{ tournee.label || tournee.zone_name }}
        </p>
      </div>

      <button v-if="tournee" type="button" class="liv__back" @click="retour">← Mes tournées</button>
      <button v-else type="button" class="liv__back" @click="deconnexion">Déconnexion</button>
    </header>

    <p v-if="erreur" class="liv__error">{{ erreur }}</p>

    <!--
      Invite d'installation, formulée pour le terrain et non pour la boutique.
      Placée sur la liste seulement : au milieu d'une tournée, elle passerait
      devant les arrêts à faire.
    -->
    <div v-if="proposerInstallation" class="liv__install">
      <p class="liv__install-txt">
        Installez l'application sur votre téléphone : elle s'ouvre directement
        sur vos tournées, sans passer par le navigateur.
      </p>
      <div class="liv__install-actions">
        <button type="button" class="liv__btn liv__btn--ok" @click="installer">Installer</button>
        <button type="button" class="liv__btn" @click="refuserInstallation">Plus tard</button>
      </div>
    </div>

    <!-- ── Liste des tournées ──────────────────────────────────────────── -->
    <template v-if="!tournee">
      <p v-if="chargement" class="liv__vide">Chargement…</p>

      <p v-else-if="!tournees.length" class="liv__vide">
        Aucune tournée à votre nom pour le moment.
      </p>

      <ul v-else class="liv__list">
        <li v-for="t in tournees" :key="t.id">
          <button type="button" class="liv__round" @click="ouvrir(t.code)">
            <div class="liv__round-head">
              <span class="liv__code">{{ t.code }}</span>
              <span v-if="t.status === 'closed'" class="liv__tag liv__tag--closed">clôturée</span>
              <span v-else class="liv__tag liv__tag--open">en cours</span>
            </div>
            <p class="liv__round-zone">{{ t.label || t.zone_name || 'Sans zone' }}</p>
            <p class="liv__round-meta">
              {{ t.orders_count }} livraison(s) · {{ t.items_count }} article(s)
              <span v-if="t.expected_total > 0">
                · <strong>{{ prix(t.expected_total) }}</strong> à encaisser
              </span>
            </p>
          </button>
        </li>
      </ul>
    </template>

    <!-- ── Une tournée : la liste des arrêts ───────────────────────────── -->
    <template v-else>
      <div class="liv__bar">
        <span><strong>{{ restantes }}</strong> à faire sur {{ tournee.lines.length }}</span>
        <span v-if="tournee.summary.collected > 0" class="liv__bar-cash">
          {{ prix(tournee.summary.collected) }} encaissés
        </span>
      </div>

      <p v-if="tournee.status === 'closed'" class="liv__closed">
        Tournée clôturée par la boutique. Le pointage n'est plus modifiable.
      </p>

      <ul class="liv__stops">
        <li
          v-for="l in tournee.lines"
          :key="l.id"
          class="liv__stop"
          :class="`liv__stop--${l.status}`"
        >
          <div class="liv__stop-head">
            <span class="liv__pos">{{ l.position }}</span>
            <div class="liv__who-block">
              <p class="liv__name">{{ l.order?.name || '—' }}</p>
              <p class="liv__place">{{ l.order?.locality || '—' }}</p>
            </div>
            <span v-if="l.status !== 'pending'" class="liv__tag" :class="tagPointage(l.status)">
              {{ LIBELLES[l.status] }}
            </span>
          </div>

          <p v-if="l.order?.address" class="liv__addr">{{ l.order.address }}</p>
          <p v-if="l.order?.landmark" class="liv__addr">Repère : {{ l.order.landmark }}</p>
          <p v-if="l.order?.note" class="liv__note">« {{ l.order.note }} »</p>

          <!-- Appeler avant de se présenter : c'est le geste le plus fréquent. -->
          <a v-if="l.order?.phone" :href="`tel:${l.order.phone}`" class="liv__call">
            Appeler {{ l.order.phone }}
          </a>

          <p class="liv__amount">
            <template v-if="l.amount_expected > 0">
              À encaisser <strong>{{ prix(l.amount_expected) }}</strong>
            </template>
            <template v-else>Déjà réglée — ne rien encaisser</template>
          </p>

          <!-- Pointage -->
          <div v-if="l.status === 'pending' && tournee.status !== 'closed'" class="liv__do">
            <!--
              Montant pré-rempli au montant attendu : le livreur ne saisit que
              s'il a encaissé autre chose. Debout devant une porte, une saisie
              par arrêt ne se fait pas.
            -->
            <input
              v-if="l.amount_expected > 0"
              v-model.number="montants[l.id]"
              type="number"
              inputmode="numeric"
              min="0"
              class="liv__input"
              :placeholder="`${l.amount_expected}`"
            />
            <button
              type="button"
              class="liv__btn liv__btn--ok"
              :disabled="enCours === l.id"
              @click="pointer(l, 'delivered')"
            >
              {{ enCours === l.id ? '…' : 'Livré' }}
            </button>
            <button type="button" class="liv__btn liv__btn--ko" @click="demanderMotif(l, 'failed')">
              Échec
            </button>
            <button type="button" class="liv__btn liv__btn--wait" @click="demanderMotif(l, 'postponed')">
              Reporté
            </button>
          </div>

          <div v-else-if="l.status !== 'pending'" class="liv__done">
            <span v-if="l.amount_collected > 0">{{ prix(l.amount_collected) }} encaissés</span>
            <span v-if="l.failure_reason" class="liv__reason">{{ l.failure_reason }}</span>
            <button
              v-if="tournee.status !== 'closed'"
              type="button"
              class="liv__link"
              @click="rouvrir(l)"
            >
              Corriger
            </button>
          </div>

          <!-- Motif, obligatoire sur un échec -->
          <div v-if="motif.lineId === l.id" class="liv__motif">
            <label class="liv__motif-label">
              {{ motif.outcome === 'failed' ? "Pourquoi la livraison n'a pas pu se faire ?" : 'Pourquoi est-elle reportée ?' }}
            </label>
            <input
              v-model="motif.text"
              type="text"
              class="liv__input liv__input--wide"
              maxlength="500"
              placeholder="Cliente absente, adresse introuvable, colis refusé…"
            />
            <div class="liv__motif-actions">
              <button
                type="button"
                class="liv__btn liv__btn--ok"
                :disabled="!motif.text.trim() || enCours === l.id"
                @click="confirmerMotif"
              >
                Enregistrer
              </button>
              <button type="button" class="liv__btn" @click="annulerMotif">Annuler</button>
            </div>
          </div>
        </li>
      </ul>

      <p v-if="restantes === 0 && tournee.status !== 'closed'" class="liv__fini">
        Tournée terminée. Rapportez la caisse à la boutique : c'est elle qui clôture.
      </p>
    </template>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api'
import { installPrompt, promptInstall, isStandalone } from '@/pwa'
import { useAuthStore } from '@/features/auth/auth.store'

const LIBELLES = {
  delivered: 'Livrée',
  failed:    'Échec',
  postponed: 'Reportée',
}

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const tournees   = ref([])
const tournee    = ref(null)
const chargement = ref(false)
const enCours    = ref(null)
const erreur     = ref('')

/** Montants corrigés, par id de ligne. Vide = le montant attendu s'applique. */
const montants = reactive({})

const motif = reactive({ lineId: null, outcome: null, text: '' })

// ── Installation sur le téléphone ─────────────────────────────────────────
// Un refus se respecte : sans mémoire, la bandeau reviendrait à chaque
// ouverture et deviendrait un irritant plutôt qu'une proposition.
const REFUS_CLE   = 'shop_livreur_pwa_refuse_le'
const REFUS_JOURS = 14
const refusInstall = ref(true)

const proposerInstallation = computed(() =>
  !tournee.value && !!installPrompt.value && !refusInstall.value && !isStandalone()
)

async function installer() {
  const choix = await promptInstall()
  if (choix === 'dismissed') refuserInstallation()
}

function refuserInstallation() {
  refusInstall.value = true
  try { localStorage.setItem(REFUS_CLE, String(Date.now())) } catch { /* mode privé */ }
}

const restantes = computed(() =>
  tournee.value ? tournee.value.lines.filter(l => l.status === 'pending').length : 0
)

async function chargerListe() {
  chargement.value = true
  erreur.value     = ''
  try {
    const { data } = await api.get('/livreur/rounds')
    tournees.value = data.data ?? []
  } catch (e) {
    erreur.value = messageDe(e, 'Impossible de charger vos tournées.')
  } finally {
    chargement.value = false
  }
}

async function chargerTournee(code) {
  chargement.value = true
  erreur.value     = ''
  try {
    const { data } = await api.get(`/livreur/rounds/${encodeURIComponent(code)}`)
    tournee.value = data.data
  } catch (e) {
    tournee.value = null
    erreur.value  = messageDe(e, "Cette tournée n'est pas accessible.")
    router.replace({ name: 'courier.rounds' })
  } finally {
    chargement.value = false
  }
}

function ouvrir(code) {
  router.push({ name: 'courier.round', params: { code } })
}

function retour() {
  router.push({ name: 'courier.rounds' })
}

async function deconnexion() {
  await auth.logout()
  router.push({ name: 'login' })
}

// ── Pointage ────────────────────────────────────────────────────────────────

async function pointer(line, outcome, reason = null) {
  if (enCours.value) return
  enCours.value = line.id
  erreur.value  = ''

  try {
    const charge = { status: outcome }

    if (outcome === 'delivered') {
      const saisi = montants[line.id]
      if (saisi !== undefined && saisi !== null && saisi !== '') {
        charge.amount_collected = Number(saisi)
      }
    } else {
      charge.reason = reason
    }

    const { data } = await api.patch(
      `/livreur/rounds/${encodeURIComponent(tournee.value.code)}/lines/${line.id}`,
      charge,
    )
    tournee.value = data.data
    delete montants[line.id]
    annulerMotif()
  } catch (e) {
    erreur.value = messageDe(e, "Le pointage n'a pas pu être enregistré. Réessayez.")
  } finally {
    enCours.value = null
  }
}

function demanderMotif(line, outcome) {
  motif.lineId  = line.id
  motif.outcome = outcome
  motif.text    = ''
}

function annulerMotif() {
  motif.lineId  = null
  motif.outcome = null
  motif.text    = ''
}

function confirmerMotif() {
  if (!motif.text.trim()) return
  const ligne = tournee.value.lines.find(l => l.id === motif.lineId)
  if (ligne) pointer(ligne, motif.outcome, motif.text.trim())
}

/** Repasse une ligne à pointer, pour corriger une saisie faite trop vite. */
function rouvrir(line) {
  tournee.value = {
    ...tournee.value,
    lines: tournee.value.lines.map(l => (l.id === line.id ? { ...l, status: 'pending' } : l)),
  }
}

// ── Divers ──────────────────────────────────────────────────────────────────

function tagPointage(status) {
  return {
    delivered: 'liv__tag--ok',
    failed:    'liv__tag--ko',
    postponed: 'liv__tag--wait',
  }[status] ?? ''
}

function prix(v) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency', currency: 'XOF', maximumFractionDigits: 0,
  }).format(Number(v) || 0)
}

/**
 * Le livreur est dans la rue, souvent en réseau incertain. Un message qui dit
 * ce qui s'est passé vaut mieux qu'un écran figé : il saura s'il doit
 * réessayer ou appeler la boutique.
 */
function messageDe(e, defaut) {
  if (!e.response) return 'Pas de réseau. Réessayez dans un instant.'
  return e.response?.data?.message ?? defaut
}

// La route porte le code : le livreur peut garder l'onglet ouvert sur sa
// tournée et le rouvrir sans repasser par la liste.
watch(() => route.params.code, (code) => {
  if (code) chargerTournee(code)
  else { tournee.value = null; chargerListe() }
})

onMounted(() => {
  try {
    const depuis = Number(localStorage.getItem(REFUS_CLE) || 0)
    refusInstall.value = depuis > 0 && Date.now() < depuis + REFUS_JOURS * 86400000
  } catch {
    refusInstall.value = false
  }

  if (route.params.code) chargerTournee(route.params.code)
  else chargerListe()
})
</script>

<style scoped>
/*
 * Écran de terrain : lu debout, sur un téléphone, souvent au soleil. D'où les
 * grandes cibles tactiles, le contraste appuyé, et une seule colonne — le
 * livreur descend la liste dans l'ordre des arrêts.
 */
.liv {
  max-width: 640px;
  margin: 0 auto;
  padding: var(--space-4) var(--space-3) 64px;
  min-height: 100vh;
  background: var(--cream-50);
}

.liv__head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: var(--space-3); margin-bottom: var(--space-4);
}
.liv__eyebrow {
  font-size: 0.6875rem; font-weight: 700; letter-spacing: 0.08em;
  text-transform: uppercase; color: var(--gray-400);
}
.liv__title {
  font-family: var(--font-display);
  font-size: 1.5rem; margin: 2px 0 0; color: var(--gray-800);
  letter-spacing: 0.03em;
}
.liv__who { font-size: 0.875rem; color: var(--gray-500); margin: 2px 0 0; }
.liv__back {
  background: none; border: none; cursor: pointer; padding: 6px 0;
  font-size: 0.8125rem; color: var(--rose-600); white-space: nowrap;
}

.liv__error {
  padding: var(--space-3); border-radius: var(--radius-md);
  background: #fee2e2; color: #991b1b; font-size: 0.875rem;
}
.liv__vide { color: var(--gray-500); text-align: center; padding: var(--space-8) 0; }

.liv__install {
  padding: var(--space-4);
  margin-bottom: var(--space-3);
  border-radius: var(--radius-lg);
  background: #fff;
  border: 1.5px dashed var(--rose-200);
}
.liv__install-txt {
  margin: 0 0 var(--space-3);
  font-size: 0.875rem; color: var(--gray-700); line-height: 1.5;
}
.liv__install-actions { display: flex; gap: 8px; }

/* ── Liste des tournées ── */
.liv__list { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); }
.liv__round {
  display: block; width: 100%; text-align: left; cursor: pointer;
  padding: var(--space-4); border-radius: var(--radius-lg);
  border: 1px solid var(--cream-300); background: #fff;
}
.liv__round-head { display: flex; align-items: center; gap: var(--space-2); }
.liv__code {
  font-family: ui-monospace, monospace; font-weight: 700;
  font-size: 1.125rem; letter-spacing: 0.06em; color: var(--rose-600);
}
.liv__round-zone { margin: 6px 0 2px; font-weight: 500; color: var(--gray-800); }
.liv__round-meta { margin: 0; font-size: 0.8125rem; color: var(--gray-500); }

/* ── Barre de progression ── */
.liv__bar {
  display: flex; justify-content: space-between; gap: var(--space-2);
  padding: var(--space-3) var(--space-4); margin-bottom: var(--space-3);
  border-radius: var(--radius-md); background: #fff;
  border: 1px solid var(--cream-300);
  font-size: 0.875rem; color: var(--gray-700);
}
.liv__bar-cash { font-weight: 600; color: #15803d; }
.liv__closed {
  padding: var(--space-3); margin-bottom: var(--space-3);
  border-radius: var(--radius-md); background: var(--cream-200);
  font-size: 0.8125rem; color: var(--gray-600);
}

/* ── Arrêts ── */
.liv__stops { list-style: none; margin: 0; padding: 0; display: grid; gap: var(--space-3); }
.liv__stop {
  padding: var(--space-4); border-radius: var(--radius-lg);
  background: #fff; border: 1px solid var(--cream-300);
}
.liv__stop--delivered { background: #f6fdf8; border-color: #bbf7d0; }
.liv__stop--failed    { background: #fef6f6; border-color: #fecaca; }
.liv__stop--postponed { background: #fffbf0; border-color: #fde68a; }

.liv__stop-head { display: flex; align-items: center; gap: var(--space-3); }
.liv__pos {
  flex: none; width: 30px; height: 30px; border-radius: 50%;
  display: grid; place-items: center;
  background: var(--rose-500); color: #fff;
  font-size: 0.8125rem; font-weight: 700;
}
.liv__who-block { flex: 1; min-width: 0; }
.liv__name  { margin: 0; font-weight: 600; color: var(--gray-800); }
.liv__place { margin: 0; font-size: 0.8125rem; color: var(--gray-500); }

.liv__addr { margin: 8px 0 0; font-size: 0.875rem; color: var(--gray-600); }
.liv__note { margin: 6px 0 0; font-size: 0.8125rem; color: var(--gray-500); font-style: italic; }

.liv__call {
  display: block; margin-top: var(--space-3);
  padding: 12px; border-radius: var(--radius-md);
  background: var(--cream-100); color: var(--rose-600);
  font-weight: 600; text-align: center; text-decoration: none;
}

.liv__amount {
  margin: var(--space-3) 0 0; font-size: 0.9375rem; color: var(--gray-700);
}
.liv__amount strong { font-size: 1.125rem; color: var(--gray-900); }

/* Boutons de pointage : cibles larges, atteignables au pouce. */
.liv__do {
  display: flex; gap: 8px; flex-wrap: wrap; margin-top: var(--space-3);
}
.liv__btn {
  flex: 1 1 90px; min-height: 46px; padding: 10px 12px;
  border-radius: var(--radius-md); border: 1.5px solid var(--cream-300);
  background: #fff; color: var(--gray-700);
  font-size: 0.9375rem; font-weight: 600; cursor: pointer;
}
.liv__btn:disabled { opacity: 0.5; cursor: default; }
.liv__btn--ok   { background: var(--rose-500); border-color: var(--rose-500); color: #fff; }
.liv__btn--ko   { color: #b91c1c; border-color: #fecaca; }
.liv__btn--wait { color: #92400e; border-color: #fde68a; }

.liv__input {
  flex: 1 1 100px; min-height: 46px; padding: 10px 12px;
  border-radius: var(--radius-md); border: 1.5px solid var(--cream-300);
  font-size: 1rem; background: #fff;
}
.liv__input--wide { width: 100%; flex: none; }

.liv__done {
  display: flex; align-items: center; gap: var(--space-3); flex-wrap: wrap;
  margin-top: var(--space-3); font-size: 0.8125rem; color: var(--gray-600);
}
.liv__reason { font-style: italic; }
.liv__link {
  background: none; border: none; cursor: pointer;
  font-size: 0.8125rem; color: var(--rose-600); padding: 4px 0;
}

.liv__motif { margin-top: var(--space-3); display: grid; gap: 8px; }
.liv__motif-label { font-size: 0.8125rem; font-weight: 600; color: var(--gray-700); }
.liv__motif-actions { display: flex; gap: 8px; }

.liv__tag {
  font-size: 0.6875rem; font-weight: 700; padding: 3px 10px;
  border-radius: 999px; white-space: nowrap;
}
.liv__tag--open   { background: #dbeafe; color: #1d4ed8; }
.liv__tag--closed { background: var(--cream-200); color: var(--gray-600); }
.liv__tag--ok     { background: #dcfce7; color: #15803d; }
.liv__tag--ko     { background: #fee2e2; color: #b91c1c; }
.liv__tag--wait   { background: #fef3c7; color: #92400e; }

.liv__fini {
  margin-top: var(--space-4); padding: var(--space-4);
  border-radius: var(--radius-md); background: #dcfce7; color: #15803d;
  font-size: 0.875rem; text-align: center;
}
</style>
