<template>
  <!--
    Convention des modales d'admin : `.modal-overlay` pour le fond,
    `.modal` pour le panneau.
  -->
  <Teleport to="body">
    <div class="modal-overlay" @click.self="$emit('close')">
      <div class="modal modal--sm">
        <header class="modal__header">
          <h2>Confirmer le départ — {{ round.code }}</h2>
          <button class="modal__close" type="button" aria-label="Fermer" @click="$emit('close')">✕</button>
        </header>

        <div class="modal__body">
          <p class="depart__intro">
            {{ round.orders_count }} commande{{ round.orders_count > 1 ? 's' : '' }}
            préparée{{ round.orders_count > 1 ? 's' : '' }},
            <strong>{{ round.items_count }} article{{ round.items_count > 1 ? 's' : '' }} à emporter</strong>,
            {{ formatPrice(round.expected_total) }} à encaisser. Les colis passent en « expédiée »
            au moment où vous confirmez.
          </p>

          <!-- ── Le livreur ── -->
          <div v-if="!creation" class="field">
            <label class="label">Livreur</label>
            <select v-model="courierId" class="input">
              <option :value="null">
                {{ round.courier ? `${round.courier.name} (déjà affecté)` : 'Non affecté' }}
              </option>
              <option v-for="c in couriers" :key="c.id" :value="c.id">
                {{ c.name }}<span v-if="c.phone"> — {{ c.phone }}</span>
              </option>
            </select>
            <button type="button" class="depart__link" @click="ouvrirCreation">
              + Créer un livreur
            </button>
          </div>

          <!--
            Création sur place : un nouveau livreur se présente le matin, la
            tournée est prête, et quitter l'écran pour créer un compte fait
            perdre la sélection.
          -->
          <fieldset v-else class="depart__creation">
            <legend>Nouveau livreur</legend>

            <div class="field">
              <label class="label">Nom *</label>
              <input v-model="nouveau.name" type="text" class="input" maxlength="255" />
            </div>
            <div class="field">
              <label class="label">Téléphone</label>
              <input v-model="nouveau.phone" type="text" class="input" maxlength="30" />
            </div>
            <div class="field">
              <label class="label">Mot de passe *</label>
              <input v-model="nouveau.password" type="text" class="input" minlength="8" />
              <p class="depart__aide">
                Huit caractères au moins. Il servira au livreur pour ouvrir sa
                tournée sur son téléphone — dictez-le-lui.
              </p>
            </div>

            <div class="depart__creation-actions">
              <button
                type="button"
                class="btn btn-sm btn-primary"
                :disabled="!nouveau.name.trim() || nouveau.password.length < 8 || creationEnCours"
                @click="creerLivreur"
              >
                {{ creationEnCours ? '…' : 'Créer' }}
              </button>
              <button type="button" class="btn btn-sm btn-outline" @click="creation = false">
                Annuler
              </button>
            </div>
          </fieldset>

          <p v-if="identifiant" class="depart__ok">
            Livreur créé. Identifiant de connexion : <strong>{{ identifiant }}</strong>
          </p>

          <!--
            Les refus ne s'affichent qu'après une tentative : la liste est
            recalculée au départ, et l'annoncer avant serait deviner.
          -->
          <div v-if="refus.length" class="depart__refus">
            <p class="depart__refus-titre">
              {{ refus.length }} commande{{ refus.length > 1 ? 's' : '' }} écartée{{ refus.length > 1 ? 's' : '' }}
              au moment du départ :
            </p>
            <ul>
              <li v-for="r in refus" :key="r.id">
                <strong>{{ r.number }}</strong> — {{ r.reasons.join(' ') }}
              </li>
            </ul>
            <label v-if="forcable" class="case">
              <input v-model="force" type="checkbox" />
              Faire partir quand même
            </label>
          </div>

          <p v-if="erreur" class="depart__erreur">{{ erreur }}</p>
        </div>

        <div class="modal__footer">
          <button type="button" class="btn btn-outline" @click="$emit('close')">Annuler</button>
          <button type="button" class="btn btn-primary" :disabled="envoi" @click="confirmer">
            {{ envoi ? '…' : 'Faire partir' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import api from '@/api'

const props = defineProps({
  round: { type: Object, required: true },
})
const emit = defineEmits(['close', 'dispatched'])

const couriers  = ref([])
const courierId = ref(props.round.courier?.id ?? null)
const force     = ref(false)
const envoi     = ref(false)
const erreur    = ref('')
const refus     = ref([])

const creation        = ref(false)
const creationEnCours = ref(false)
const identifiant     = ref('')
const nouveau         = reactive({ name: '', phone: '', password: '' })

/** Forcer n'a de sens que sur les refus qui se lèvent. */
const forcable = computed(() => refus.value.some(r => r.forceable))

async function chargerLivreurs() {
  try {
    const { data } = await api.get('/admin/delivery-rounds/couriers')
    couriers.value = data.data ?? []
  } catch (e) {
    console.error('Livreurs indisponibles', e)
  }
}

function ouvrirCreation() {
  creation.value    = true
  identifiant.value = ''
  erreur.value      = ''
  nouveau.name      = ''
  nouveau.phone     = ''
  nouveau.password  = ''
}

async function creerLivreur() {
  if (creationEnCours.value) return
  creationEnCours.value = true
  erreur.value          = ''

  try {
    const { data } = await api.post('/admin/delivery-rounds/couriers', {
      name:     nouveau.name.trim(),
      phone:    nouveau.phone.trim() || null,
      password: nouveau.password,
    })

    await chargerLivreurs()
    // Le nouveau venu est aussitôt sélectionné : c'est pour lui qu'on vient
    // de le créer.
    courierId.value   = data.data.id
    identifiant.value = data.data.username
    creation.value    = false
  } catch (e) {
    erreur.value = premierMessage(e) ?? "Le livreur n'a pas pu être créé."
  } finally {
    creationEnCours.value = false
  }
}

async function confirmer() {
  if (envoi.value) return
  envoi.value  = true
  erreur.value = ''

  try {
    const { data } = await api.post(
      `/admin/delivery-rounds/${encodeURIComponent(props.round.code)}/dispatch`,
      { courier_id: courierId.value, force: force.value },
    )
    emit('dispatched', data)
  } catch (e) {
    const corps = e.response?.data ?? {}
    refus.value  = corps.rejected ?? []
    erreur.value = premierMessage(e) ?? "La tournée n'a pas pu partir."
  } finally {
    envoi.value = false
  }
}

/** Message du serveur, y compris celui d'une erreur de validation. */
function premierMessage(e) {
  const corps = e.response?.data
  if (!corps) return null
  const champs = corps.errors ? Object.values(corps.errors).flat() : []
  return champs[0] ?? corps.message ?? null
}

function formatPrice(v) {
  return new Intl.NumberFormat('fr-FR', {
    style: 'currency', currency: 'XOF', maximumFractionDigits: 0,
  }).format(Number(v) || 0)
}

onMounted(chargerLivreurs)
</script>

<style scoped>
.depart__intro {
  margin: 0; font-size: 0.8125rem; line-height: 1.6; color: var(--gray-600);
}
.depart__link {
  margin-top: 6px; padding: 0; background: none; border: none; cursor: pointer;
  font-size: 0.75rem; color: var(--rose-600);
}
.depart__link:hover { text-decoration: underline; }

.depart__creation {
  display: flex; flex-direction: column; gap: var(--space-3);
  padding: var(--space-4); margin: 0;
  border: 1px solid var(--cream-300); border-radius: var(--radius-md);
  background: var(--cream-50);
}
.depart__creation legend {
  padding: 0 6px; font-size: 0.75rem; font-weight: 600; color: var(--gray-600);
}
.depart__creation-actions { display: flex; gap: var(--space-2); }
.depart__aide {
  margin: 4px 0 0; font-size: 0.6875rem; line-height: 1.5; color: var(--gray-500);
}

.depart__ok {
  margin: 0; padding: var(--space-3);
  border-radius: var(--radius-sm);
  background: #f0fdf4; color: #166534; font-size: 0.8125rem;
}

.depart__refus {
  padding: var(--space-3); border-radius: var(--radius-sm);
  background: #fffbeb; border: 1px solid #fde68a;
}
.depart__refus-titre { margin: 0 0 6px; font-size: 0.8125rem; font-weight: 600; color: #92400e; }
.depart__refus ul { margin: 0; padding-left: 18px; font-size: 0.75rem; color: #78350f; }
.depart__refus li { margin-bottom: 2px; }
.case {
  display: inline-flex; align-items: center; gap: 8px;
  margin-top: 8px; font-size: 0.75rem; color: #78350f; cursor: pointer;
}

.depart__erreur {
  margin: 0; padding: var(--space-3); border-radius: var(--radius-sm);
  background: #fee2e2; color: #991b1b; font-size: 0.8125rem;
}
</style>
