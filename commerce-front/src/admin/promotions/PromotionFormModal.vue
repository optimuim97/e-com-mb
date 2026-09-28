<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="modal modal--lg">
      <header class="modal__header">
        <h3>{{ promotion ? "Modifier la promotion" : "Nouvelle promotion" }}</h3>
        <button class="modal__close" @click="$emit('close')" aria-label="Fermer">
          ✕
        </button>
      </header>

      <div class="modal__body">
        <!-- Identité -->
        <div class="field">
          <label>Nom de la promotion *</label>
          <input
            v-model="form.name"
            class="input"
            placeholder="Ex. Soldes de fin d'année"
          />
        </div>

        <div class="field">
          <label>Description</label>
          <textarea
            v-model="form.description"
            class="input"
            rows="2"
            placeholder="Note interne : à quoi sert cette opération"
          ></textarea>
        </div>

        <!-- Type d'opération -->
        <div class="field">
          <label>Type d'opération</label>
          <div class="choice-grid">
            <label
              v-for="t in TYPES"
              :key="t.value"
              class="choice"
              :class="{ 'choice--active': form.type === t.value }"
            >
              <input type="radio" :value="t.value" v-model="form.type" />
              <strong>{{ t.label }}</strong>
              <span class="mt-2">{{ t.hint }}</span>
            </label>
          </div>
        </div>

        <!-- Portée -->
        <div class="field">
          <label>Sur quoi s'applique-t-elle ?</label>
          <div class="choice-grid choice-grid--2">
            <label
              v-for="s in SCOPES"
              :key="s.value"
              class="choice"
              :class="{ 'choice--active': form.scope === s.value }"
            >
              <input type="radio" :value="s.value" v-model="form.scope" />
              <strong>{{ s.label }}</strong>
              <span>{{ s.hint }}</span>
            </label>
          </div>
        </div>

        <!-- Sélection d'articles -->
        <div v-if="form.scope === 'product'" class="field">
          <label>Articles concernés *</label>
          <input
            v-model="productSearch"
            class="input"
            placeholder="Rechercher un article…"
            @input="debouncedSearch"
          />
          <div v-if="searchResults.length" class="product-results">
            <button
              v-for="p in searchResults"
              :key="p.id"
              class="product-row"
              type="button"
              @click="addProduct(p)"
            >
              {{ p.name }} <span>{{ fmt(p.price) }}</span>
            </button>
          </div>
          <!-- Aperçu : chaque article, sa remise et le prix qui en résulte -->
          <table v-if="form.products.length" class="preview">
            <thead>
              <tr>
                <th>Article</th>
                <th>Prix</th>
                <th>Remise</th>
                <th>Prix remisé</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in form.products" :key="p.id">
                <td class="preview__name">{{ p.name }}</td>
                <td class="preview__price">{{ fmt(p.price) }}</td>
                <td class="preview__discount">
                  <select v-model="p.discount_type" class="input input--xs">
                    <option :value="null">Celle de l'opération</option>
                    <option value="percent">%</option>
                    <option value="fixed">F CFA</option>
                  </select>
                  <input
                    v-if="p.discount_type"
                    v-model.number="p.value"
                    type="number"
                    min="0"
                    class="input input--xs"
                    placeholder="0"
                  />
                </td>
                <td class="preview__result">
                  <strong>{{ fmt(previewPrice(p)) }}</strong>
                  <small>−{{ fmt(p.price - previewPrice(p)) }}</small>
                </td>
                <td>
                  <button
                    type="button"
                    class="btn btn-xs btn-outline"
                    @click="removeProduct(p.id)"
                  >
                    Retirer
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
          <p v-else class="hint">Aucun article sélectionné pour l'instant.</p>
        </div>

        <!-- Remise -->
        <div class="field-row">
          <div class="field">
            <label>Type de remise *</label>
            <select v-model="form.discount_type" class="input">
              <option v-for="d in DISCOUNT_TYPES" :key="d.value" :value="d.value">
                {{ d.label }}
              </option>
            </select>
          </div>
          <div class="field">
            <label>Valeur *</label>
            <input
              v-model.number="form.value"
              type="number"
              min="0"
              :max="form.discount_type === 'percent' ? 100 : null"
              class="input"
            />
          </div>
          <div v-if="form.discount_type === 'percent'" class="field">
            <label>Plafond de remise</label>
            <input
              v-model.number="form.max_discount_amount"
              type="number"
              min="0"
              class="input"
              placeholder="Aucun"
            />
          </div>
        </div>

        <p v-if="form.discount_type === 'percent'" class="hint">
          Le plafond limite le montant retiré. Sans lui, −{{ form.value || 0 }} % sur un
          coffret à 80 000 F retire {{ fmt((form.value || 0) * 800) }}.
        </p>

        <!-- Période -->
        <div class="field-row">
          <div class="field">
            <label>Date de début</label>
            <input v-model="form.starts_at" type="datetime-local" class="input" />
          </div>
          <div class="field">
            <label>Date de fin</label>
            <input v-model="form.ends_at" type="datetime-local" class="input" />
          </div>
        </div>
        <p class="hint">Laissez vide pour une promotion sans date de début ou de fin.</p>

        <!-- Réglages -->
        <div class="field-row">
          <div class="field">
            <label>Libellé du badge</label>
            <input
              v-model="form.banner_label"
              class="input"
              maxlength="40"
              :placeholder="defaultBadge"
            />
          </div>
          <div class="field">
            <label>Priorité</label>
            <input v-model.number="form.priority" type="number" min="0" class="input" />
          </div>
        </div>
        <p class="hint">
          Les promotions ne se cumulent pas : si plusieurs s'appliquent au même article,
          celle de plus haute priorité l'emporte.
        </p>

        <label class="switch">
          <input type="checkbox" v-model="form.is_active" />
          Promotion active
        </label>

        <p v-if="error" class="form-error">{{ error }}</p>
      </div>

      <footer class="modal__footer">
        <button class="btn btn-outline" @click="$emit('close')">Annuler</button>
        <button class="btn btn-primary" :disabled="saving" @click="save">
          {{ saving ? "Enregistrement…" : "Enregistrer" }}
        </button>
      </footer>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import api from "@/api";
import { TYPES, SCOPES, DISCOUNT_TYPES } from "./promotion.constants";
import { useHomeStore } from "@/features/home/home.store";

const props = defineProps({ promotion: { type: Object, default: null } });
const emit = defineEmits(["close", "saved"]);

const saving = ref(false);
const error = ref("");

const form = reactive({
  name: "",
  description: "",
  type: "standard",
  scope: "global",
  discount_type: "percent",
  value: 10,
  max_discount_amount: null,
  banner_label: "",
  starts_at: "",
  ends_at: "",
  priority: 0,
  is_active: true,
  products: [],
});

const defaultBadge = computed(() =>
  form.discount_type === "percent" ? `−${form.value || 0} %` : "Promotion"
);

function fmt(value) {
  return new Intl.NumberFormat("fr-FR").format(Math.round(Number(value) || 0)) + " F";
}

// Les champs datetime-local n'acceptent pas le format ISO complet renvoyé
// par l'API : on tronque aux minutes.
function toLocalInput(iso) {
  return iso ? String(iso).slice(0, 16) : "";
}

onMounted(() => {
  if (!props.promotion) return;
  Object.assign(form, {
    ...props.promotion,
    starts_at: toLocalInput(props.promotion.starts_at),
    ends_at: toLocalInput(props.promotion.ends_at),
    // Les remises dérogatoires vivent dans le pivot renvoyé par l'API.
    products: (props.promotion.products ?? []).map((p) => ({
      id: p.id,
      name: p.name,
      price: Number(p.price),
      discount_type: p.pivot?.discount_type ?? null,
      value: p.pivot?.value != null ? Number(p.pivot.value) : null,
    })),
  });
});

// ── Sélection d'articles ────────────────────────────────────────────────
const productSearch = ref("");
const searchResults = ref([]);
let searchTimer;

function debouncedSearch() {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(async () => {
    if (productSearch.value.trim().length < 2) {
      searchResults.value = [];
      return;
    }
    const { data } = await api.get("/products", {
      params: { search: productSearch.value, per_page: 8 },
    });
    const items = data.data ?? data;
    // On masque ceux déjà retenus : les reproposer prête à confusion.
    const chosen = new Set(form.products.map((p) => p.id));
    searchResults.value = items.filter((p) => !chosen.has(p.id));
  }, 300);
}

function addProduct(product) {
  form.products.push({
    id: product.id,
    name: product.name,
    price: Number(product.price),
    // Null = l'article suit la remise de l'opération.
    discount_type: null,
    value: null,
  });
  searchResults.value = searchResults.value.filter((p) => p.id !== product.id);
  productSearch.value = "";
}

function removeProduct(id) {
  form.products = form.products.filter((p) => p.id !== id);
}

/**
 * Prix remisé tel que le calculera le serveur.
 *
 * Reproduit volontairement la même règle qu'en base — remise propre à
 * l'article si elle est renseignée, sinon celle de l'opération, le tout borné
 * par le plafond — pour que l'aperçu ne mente pas.
 */
function previewPrice(product) {
  const price = Number(product.price) || 0;
  const type = product.discount_type || form.discount_type;
  const value = product.discount_type
    ? Number(product.value) || 0
    : Number(form.value) || 0;

  let discount = type === "percent" ? (price * value) / 100 : value;

  if (form.discount_type === "percent" && form.max_discount_amount) {
    discount = Math.min(discount, Number(form.max_discount_amount));
  }

  return Math.max(0, Math.round((price - discount) * 100) / 100);
}

// ── Enregistrement ──────────────────────────────────────────────────────
async function save() {
  error.value = "";
  saving.value = true;
  try {
    const payload = {
      name: form.name,
      description: form.description || null,
      type: form.type,
      scope: form.scope,
      discount_type: form.discount_type,
      value: form.value,
      // Un plafond n'a de sens que sur un pourcentage — le serveur le refuse
      // sur un montant fixe.
      max_discount_amount:
        form.discount_type === "percent" ? form.max_discount_amount || null : null,
      banner_label: form.banner_label || null,
      starts_at: form.starts_at || null,
      ends_at: form.ends_at || null,
      priority: form.priority || 0,
      is_active: form.is_active,
      // Chaque article part avec sa remise dérogatoire éventuelle.
      products:
        form.scope === "product"
          ? form.products.map((p) => ({
              id: p.id,
              discount_type: p.discount_type || null,
              value: p.discount_type ? p.value ?? null : null,
            }))
          : [],
    };

    if (props.promotion) {
      await api.put(`/admin/promotions/${props.promotion.id}`, payload);
    } else {
      await api.post("/admin/promotions", payload);
    }

    // L'accueil est mis en cache cinq minutes côté navigateur aussi : sans
    // cela, la gérante qui enregistre une opération puis va voir la boutique
    // dans le même onglet la croirait sans effet.
    useHomeStore().fetchStats(true);

    emit("saved");
  } catch (e) {
    const data = e.response?.data ?? {};
    error.value =
      data.message ||
      Object.values(data.errors ?? {}).flat()[0] ||
      "L'enregistrement a échoué.";
  } finally {
    saving.value = false;
  }
}
</script>

<style scoped>
.modal--lg {
  max-width: 720px;
}
.field {
  margin-bottom: var(--space-4);
}
.field label {
  display: block;
  font-size: 0.8125rem;
  font-weight: 500;
  margin-bottom: 6px;
}
.field-row {
  display: flex;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.field-row .field {
  flex: 1;
  min-width: 150px;
}

.choice-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: var(--space-2);
}
.choice-grid--2 {
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}
.choice {
  display: block;
  padding: var(--space-3);
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-sm, 6px);
  cursor: pointer;
}
.choice--active {
  border-color: var(--rose-500);
  background: var(--rose-50);
}
.choice input {
  display: none;
}
.choice strong {
  display: block;
  font-size: 0.875rem;
}
.choice span {
  display: block;
  font-size: 0.75rem;
  color: var(--gray-500);
  margin-top: 2px;
}

.product-results {
  border: 1px solid var(--gray-200);
  border-radius: var(--radius-sm, 6px);
  margin-top: 6px;
  max-height: 180px;
  overflow-y: auto;
}
.product-row {
  display: flex;
  justify-content: space-between;
  width: 100%;
  padding: 8px 12px;
  background: none;
  border: 0;
  cursor: pointer;
  text-align: left;
  font-size: 0.8125rem;
}
.product-row:hover {
  background: var(--rose-50);
}
.preview {
  width: 100%;
  margin-top: 10px;
  border-collapse: collapse;
  font-size: 0.8125rem;
}
.preview th {
  text-align: left;
  font-weight: 500;
  font-size: 0.6875rem;
  color: var(--gray-500);
  padding: 4px 8px;
  border-bottom: 1px solid var(--gray-200);
}
.preview td {
  padding: 8px;
  border-bottom: 1px solid var(--gray-100);
  vertical-align: middle;
}
.preview__name {
  max-width: 200px;
}
.preview__price {
  color: var(--gray-500);
  white-space: nowrap;
}
.preview__discount {
  display: flex;
  gap: 4px;
  align-items: center;
}
.preview__result {
  white-space: nowrap;
}
.preview__result strong {
  color: var(--rose-600);
  display: block;
}
.preview__result small {
  color: var(--gray-400);
  font-size: 0.6875rem;
}
.input--xs {
  padding: 4px 6px !important;
  font-size: 0.75rem !important;
  max-width: 110px;
}

.hint {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin: -8px 0 var(--space-4);
}
.switch {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.875rem;
}
</style>
