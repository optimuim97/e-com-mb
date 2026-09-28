<template>
  <div class="suppliers">

    <div class="page-header">
      <div>
        <h1 class="page-header__title">Fournisseurs</h1>
        <p class="page-header__sub">
          Chez qui la boutique s'approvisionne. Un fournisseur rattaché à des
          bons d'achat est désactivé plutôt que supprimé — ses pièces justifient
          les coûts déjà entrés.
        </p>
      </div>
      <button class="btn btn-primary" @click="ouvrirFormulaire()">Nouveau fournisseur</button>
    </div>

    <div class="filters-bar">
      <input
        v-model="recherche"
        type="search"
        class="input input--sm"
        placeholder="Nom, contact, téléphone…"
        @keyup.enter="charger(1)"
      />
      <label class="filtre-actifs">
        <input v-model="actifsSeuls" type="checkbox" @change="charger(1)" />
        Actifs seulement
      </label>
    </div>

    <div class="card table-scroll">
      <div v-if="loading" class="empty-state">Chargement…</div>
      <!--
        Un échec de chargement ne doit pas se lire comme une liste vide : la
        cause est ailleurs, et l'écran affirmait le contraire de la vérité.
      -->
      <div v-else-if="chargementErreur" class="empty-state">
        <p>{{ chargementErreur }}</p>
        <RouterLink v-if="moduleEteint" class="btn btn-outline btn-sm" :to="{ name: 'admin.settings' }">
          Ouvrir les paramètres
        </RouterLink>
      </div>
      <div v-else-if="fournisseurs.length === 0" class="empty-state">
        Aucun fournisseur enregistré.
      </div>
      <table v-else class="admin-table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Contact</th>
            <th>Téléphone</th>
            <th class="text-right">Bons d'achat</th>
            <th>État</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in fournisseurs" :key="f.id">
            <td class="font-medium">{{ f.name }}</td>
            <td class="text-muted">{{ f.contact_name || '—' }}</td>
            <td class="text-muted">{{ f.phone || '—' }}</td>
            <td class="text-right">{{ f.orders_count ?? 0 }}</td>
            <td>
              <span class="badge" :class="f.is_active ? 'badge-success' : 'badge-gray'">
                {{ f.is_active ? 'Actif' : 'Archivé' }}
              </span>
            </td>
            <td class="text-right">
              <button class="btn btn-ghost btn-sm" @click="ouvrirFormulaire(f)">Modifier</button>
              <button class="btn btn-ghost btn-sm" @click="supprimer(f)">Supprimer</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <AdminPagination
      :current-page="page"
      :last-page="meta?.last_page"
      :total="meta?.total"
      :show-per-page="false"
      item-singular="fournisseur"
      item-plural="fournisseurs"
      @update:page="charger"
    />

    <!--
      Formulaire. Téléporté vers `body` et bâti sur les classes globales
      d'app.css : `.modal-overlay` pour le fond, `.modal` pour le panneau.
      L'inverse était écrit ici, et le fond héritait donc du `max-width`
      du panneau — il ne couvrait plus l'écran.
    -->
    <Teleport to="body">
      <div v-if="formulaireOuvert" class="modal-overlay" @click.self="fermerFormulaire">
        <div class="modal">
          <header class="modal__header">
            <h2>{{ form.id ? 'Modifier le fournisseur' : 'Nouveau fournisseur' }}</h2>
            <button class="modal__close" type="button" aria-label="Fermer" @click="fermerFormulaire">✕</button>
          </header>

          <div class="modal__body">
            <div class="field">
              <label class="label">Nom *</label>
              <input v-model="form.name" type="text" class="input" maxlength="160" />
            </div>
            <div class="field">
              <label class="label">Personne de contact</label>
              <input v-model="form.contact_name" type="text" class="input" maxlength="160" />
            </div>
            <div class="modal__grid">
              <div class="field">
                <label class="label">Téléphone</label>
                <input v-model="form.phone" type="text" class="input" maxlength="40" />
              </div>
              <div class="field">
                <label class="label">E-mail</label>
                <input v-model="form.email" type="email" class="input" maxlength="160" />
              </div>
            </div>
            <div class="field">
              <label class="label">Adresse</label>
              <input v-model="form.address" type="text" class="input" maxlength="255" />
            </div>
            <div class="field">
              <label class="label">Notes</label>
              <textarea v-model="form.notes" class="input" rows="3" maxlength="2000"></textarea>
            </div>
            <label class="case">
              <input v-model="form.is_active" type="checkbox" />
              Fournisseur actif
            </label>

            <p v-if="erreur" class="erreur">{{ erreur }}</p>
          </div>

          <footer class="modal__footer">
            <button class="btn btn-ghost" @click="fermerFormulaire">Annuler</button>
            <button class="btn btn-primary" :disabled="!form.name.trim() || envoi" @click="enregistrer">
              {{ envoi ? 'Enregistrement…' : 'Enregistrer' }}
            </button>
          </footer>
        </div>
      </div>
    </Teleport>

  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { usePersistedRef } from '@/admin/utils/persistedFilters'
import { RouterLink } from 'vue-router'
import api from '@/api'
import AdminPagination from '@/admin/components/AdminPagination.vue'

const fournisseurs = ref([])
const meta         = ref(null)
const loading      = ref(false)

/** Pourquoi la liste est vide, quand elle l'est pour une mauvaise raison. */
const chargementErreur = ref('')
const moduleEteint     = ref(false)
const page         = ref(1)
// Conservés d'une visite à l'autre, comme sur les autres listes.
const recherche    = usePersistedRef('suppliers.search', '')
const actifsSeuls  = usePersistedRef('suppliers.active-only', false)

async function charger(p = 1) {
  page.value    = p
  loading.value = true
  chargementErreur.value = ''
  moduleEteint.value     = false
  try {
    const params = { page: p }
    if (recherche.value)   params.search      = recherche.value
    if (actifsSeuls.value) params.active_only = 1
    const { data } = await api.get('/admin/suppliers', { params })
    fournisseurs.value = data.data
    meta.value         = data.meta
  } catch (e) {
    fournisseurs.value = []
    meta.value = null
    moduleEteint.value     = e.response?.status === 403
    chargementErreur.value = e.response?.data?.message ?? "Les fournisseurs n'ont pas pu être chargés."
  } finally {
    loading.value = false
  }
}

// ── Formulaire ───────────────────────────────────────────────────────────────

const formulaireOuvert = ref(false)
const envoi            = ref(false)
const erreur           = ref('')

const VIDE = { id: null, name: '', contact_name: '', phone: '', email: '', address: '', notes: '', is_active: true }
const form = ref({ ...VIDE })

function ouvrirFormulaire(fournisseur = null) {
  form.value = fournisseur ? { ...fournisseur } : { ...VIDE }
  erreur.value = ''
  formulaireOuvert.value = true
}

function fermerFormulaire() {
  formulaireOuvert.value = false
}

async function enregistrer() {
  envoi.value  = true
  erreur.value = ''
  try {
    const charge = {
      name: form.value.name,
      contact_name: form.value.contact_name || null,
      phone: form.value.phone || null,
      email: form.value.email || null,
      address: form.value.address || null,
      notes: form.value.notes || null,
      is_active: form.value.is_active,
    }
    if (form.value.id) await api.put(`/admin/suppliers/${form.value.id}`, charge)
    else               await api.post('/admin/suppliers', charge)

    fermerFormulaire()
    charger(page.value)
  } catch (e) {
    erreur.value = e.response?.data?.message ?? "Le fournisseur n'a pas pu être enregistré."
  } finally {
    envoi.value = false
  }
}

async function supprimer(fournisseur) {
  if (!window.confirm(`Supprimer « ${fournisseur.name} » ?`)) return
  try {
    // Le serveur archive au lieu de supprimer si des bons y sont rattachés,
    // et le dit dans sa réponse.
    const { data } = await api.delete(`/admin/suppliers/${fournisseur.id}`)
    if (data.archived) window.alert(data.message)
    charger(page.value)
  } catch (e) {
    window.alert(e.response?.data?.message ?? 'Suppression impossible.')
  }
}

onMounted(() => charger(1))
</script>

<style scoped>
/* Espacement vertical de l'écran, comme les autres pages de l'admin. */
.suppliers { display: flex; flex-direction: column; gap: var(--space-4); }
.suppliers > .page-header { margin-bottom: 0; }
.suppliers > .filters-bar { margin-bottom: 0; }
.suppliers > .stats-grid  { margin-bottom: 0; }
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  flex-wrap: wrap;
}

.filters-bar {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
  margin-bottom: 16px;
}

.filtre-actifs {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: #6b5f58;
}

.text-right { text-align: right; }

.case {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  margin-top: 4px;
}

/* Les styles de modale viennent d'app.css — voir le commentaire du gabarit. */

.erreur {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 13px;
}
</style>
