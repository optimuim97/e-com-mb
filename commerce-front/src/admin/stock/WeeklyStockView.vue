<template>
  <div class="semaine">

    <div class="page-header">
      <div>
        <h1 class="page-header__title">Stock de la semaine</h1>
        <p class="page-header__sub">
          Ce que la boutique accepte de vendre en ligne cette semaine. Le reste
          du stock demeure disponible au comptoir. Un article sans quantité
          saisie n'est pas plafonné — il se vend dans la limite du stock.
        </p>
      </div>
    </div>

    <!-- Navigation de semaine -->
    <div class="filters-bar">
      <button class="btn btn-outline btn-sm" :disabled="chargement" @click="decaler(-1)">← Semaine précédente</button>
      <span class="semaine__libelle">{{ libelleSemaine }}</span>
      <button class="btn btn-outline btn-sm" :disabled="chargement || estSemaineCourante" @click="decaler(1)">
        Semaine suivante →
      </button>
      <button v-if="!estSemaineCourante" class="btn btn-ghost btn-sm" @click="revenirCourante">
        Revenir à cette semaine
      </button>

      <span class="semaine__espace"></span>

      <button
        class="btn btn-primary btn-sm"
        :disabled="!modifie || enregistrement"
        @click="enregistrer"
      >
        {{ enregistrement ? 'Enregistrement…' : (modifie ? `Enregistrer (${nbModifiees})` : 'Enregistré') }}
      </button>
    </div>

    <div class="card table-scroll">
      <div v-if="chargement" class="empty-state">Chargement…</div>
      <div v-else-if="erreur" class="empty-state">{{ erreur }}</div>
      <div v-else-if="!lignes.length" class="empty-state">Aucun article actif.</div>

      <table v-else class="admin-table semaine__table">
        <thead>
          <tr>
            <th>Article</th>
            <th class="text-right" title="Stock physique en boutique">En stock</th>
            <th class="text-right" title="Entré en stock cette semaine">Entré</th>
            <th class="text-right" title="Vendu cette semaine, annulations déduites">Sorti</th>
            <th class="text-right semaine__col-saisie">À vendre en ligne</th>
            <th class="text-right" title="Ce qu'il reste à vendre sur l'allocation">Reste</th>
            <th class="semaine__col-note">Anomalie constatée</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="l in lignes" :key="l.product_id" :class="{ 'semaine__ligne--modifiee': estModifiee(l) }">
            <td>
              <div class="font-medium">{{ l.name }}</div>
              <div v-if="l.sku" class="text-muted text-sm">{{ l.sku }}</div>
            </td>

            <td class="text-right">
              <span :class="{ 'semaine__bas': l.low_stock }">{{ l.stock }}</span>
            </td>
            <td class="text-right text-muted">{{ l.received || '—' }}</td>
            <td class="text-right">{{ l.sold || '—' }}</td>

            <!-- La seule colonne saisie. -->
            <td class="text-right">
              <input
                v-model="saisie[l.product_id].quantity"
                type="number"
                min="0"
                class="input input--mini"
                placeholder="—"
                :disabled="!modifiable"
                @keydown.enter.prevent="enregistrer"
              />
            </td>

            <td class="text-right">
              <template v-if="reste(l) === null"><span class="text-muted">sans plafond</span></template>
              <strong v-else :class="{ 'semaine__epuise': reste(l) === 0 }">{{ reste(l) }}</strong>
            </td>

            <td>
              <input
                v-model="saisie[l.product_id].note"
                type="text"
                class="input input--sm"
                maxlength="255"
                placeholder="Casse, écart, retour douteux…"
                :disabled="!modifiable"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="!modifiable" class="semaine__avis">
      Semaine passée : les quantités ne sont plus modifiables, seul le constat
      reste consultable.
    </p>

    <p v-if="messageErreur" class="semaine__erreur">{{ messageErreur }}</p>

  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import api from '@/api'

/*
 * Une seule colonne se saisit : l'allocation. Les autres sont calculées côté
 * serveur — vendu depuis les lignes de commande, entré depuis le registre des
 * mouvements. Garder un compteur de « vendu » ici le ferait diverger du réel
 * dès la première annulation.
 */
const lignes        = ref([])
const saisie        = reactive({})
const semaine       = ref(null)      // lundi, au format ISO
const finSemaine    = ref(null)
const chargement    = ref(false)
const enregistrement = ref(false)
const erreur        = ref('')
const messageErreur = ref('')

/** Instantané de l'état serveur, pour repérer ce qui a bougé. */
const original = reactive({})

const lundiCourant = () => {
  const d = new Date()
  const jour = (d.getDay() + 6) % 7          // lundi = 0
  d.setDate(d.getDate() - jour)
  return d.toISOString().slice(0, 10)
}

const estSemaineCourante = computed(() => semaine.value === lundiCourant())

/* Une semaine révolue ne se corrige pas : ce serait réécrire une décision déjà
   appliquée aux ventes. On la laisse en lecture. */
const modifiable = computed(() => semaine.value >= lundiCourant())

const libelleSemaine = computed(() => {
  if (!semaine.value) return ''
  const fmt = (iso) => new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'long' })
  return `Du ${fmt(semaine.value)} au ${fmt(finSemaine.value)}`
})

function estModifiee(l) {
  const a = saisie[l.product_id]
  const b = original[l.product_id]
  if (!a || !b) return false
  return String(a.quantity ?? '') !== String(b.quantity ?? '')
      || String(a.note ?? '')     !== String(b.note ?? '')
}

const modifiees   = computed(() => lignes.value.filter(estModifiee))
const nbModifiees = computed(() => modifiees.value.length)
const modifie     = computed(() => nbModifiees.value > 0)

/** Reste calculé à la volée : la saisie doit répondre sans aller-retour. */
function reste(l) {
  const brut = saisie[l.product_id]?.quantity
  if (brut === '' || brut === null || brut === undefined) return null
  return Math.max(0, Number(brut) - l.sold)
}

async function charger(semaineCible = null) {
  chargement.value = true
  erreur.value = ''
  try {
    const { data } = await api.get('/admin/weekly-stock', {
      params: semaineCible ? { week: semaineCible } : {},
    })
    lignes.value     = data.rows
    semaine.value    = data.week_start
    finSemaine.value = data.week_end

    for (const cle of Object.keys(saisie)) delete saisie[cle]
    for (const cle of Object.keys(original)) delete original[cle]

    for (const l of data.rows) {
      const valeur = { quantity: l.allocated ?? '', note: l.note ?? '' }
      saisie[l.product_id]   = { ...valeur }
      original[l.product_id] = { ...valeur }
    }
  } catch (e) {
    erreur.value = e.response?.data?.message ?? "Le tableau n'a pas pu être chargé."
  } finally {
    chargement.value = false
  }
}

function decaler(semaines) {
  const d = new Date(semaine.value)
  d.setDate(d.getDate() + semaines * 7)
  charger(d.toISOString().slice(0, 10))
}

const revenirCourante = () => charger(lundiCourant())

async function enregistrer() {
  if (!modifie.value || enregistrement.value) return
  enregistrement.value = true
  messageErreur.value  = ''

  try {
    // Seules les lignes touchées partent : envoyer tout le catalogue écraserait
    // des décisions prises entre-temps depuis un autre poste.
    const { data } = await api.post('/admin/weekly-stock', {
      week: semaine.value,
      rows: modifiees.value.map(l => ({
        product_id: l.product_id,
        quantity:   saisie[l.product_id].quantity === '' ? null : Number(saisie[l.product_id].quantity),
        note:       saisie[l.product_id].note || null,
      })),
    })

    lignes.value = data.rows
    for (const l of data.rows) {
      const valeur = { quantity: l.allocated ?? '', note: l.note ?? '' }
      saisie[l.product_id]   = { ...valeur }
      original[l.product_id] = { ...valeur }
    }
  } catch (e) {
    messageErreur.value = e.response?.data?.message ?? "L'enregistrement a échoué."
  } finally {
    enregistrement.value = false
  }
}

onMounted(() => charger())
</script>

<style scoped>
.semaine { display: flex; flex-direction: column; gap: var(--space-4); }

.filters-bar {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  flex-wrap: wrap;
}
.semaine__espace { flex: 1; min-width: var(--space-2); }

.semaine__libelle {
  font-weight: 600;
  font-size: 0.9375rem;
  color: var(--gray-800);
  white-space: nowrap;
}

.text-right { text-align: right; }

/* Les deux colonnes saisies se distinguent du constat. */
.semaine__col-saisie { min-width: 120px; }
.semaine__col-note   { min-width: 220px; }

.semaine__table td { vertical-align: middle; }

.semaine__ligne--modifiee > td {
  background: var(--rose-50);
  box-shadow: inset 3px 0 0 var(--rose-500);
}

.semaine__bas    { color: #a3221b; font-weight: 600; }
.semaine__epuise { color: #a3221b; }

.semaine__avis {
  margin: 0;
  font-size: 0.8125rem;
  color: var(--gray-500);
}

.semaine__erreur {
  margin: 0;
  padding: 10px 12px;
  border-radius: var(--radius-md);
  background: #fee2e2;
  color: #b91c1c;
  font-size: 0.8125rem;
}
</style>
