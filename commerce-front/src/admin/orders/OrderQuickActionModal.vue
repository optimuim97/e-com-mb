<template>
  <component
    :is="inline ? 'div' : 'div'"
    :class="inline ? 'qa-inline' : 'modal-overlay'"
    @click="inline ? null : ($event.target === $event.currentTarget && $emit('close'))"
  >
    <div :class="inline ? 'qa-panel' : 'modal'">
      <header :class="inline ? 'qa-header' : 'modal__header'">
        <div>
          <span class="eyebrow">Traitement rapide</span>
          <h3>Commande {{ order.number }}</h3>
        </div>
        <button :class="inline ? 'qa-close' : 'modal__close'" @click="$emit('close')" aria-label="Fermer">✕</button>
      </header>

      <!-- Récap commande -->
      <div class="modal__section">
        <div class="recap-grid">
          <div>
            <span class="recap-label">Client</span>
            <strong>{{ clientName }}</strong>
            <span class="recap-sub">{{ clientPhone || '—' }}</span>
          </div>
          <div>
            <span class="recap-label">Total</span>
            <strong>{{ fmt(order.total) }}</strong>
            <span class="recap-sub">{{ order.items?.length || 0 }} article(s)</span>
          </div>
          <div>
            <span class="recap-label">Moyen de paiement</span>
            <strong>{{ paymentLabel(order.payment_method) }}</strong>
            <span class="recap-sub" :class="['pmt-state', order.paid_at ? 'pmt-state--ok' : 'pmt-state--pending']">
              {{ order.paid_at ? 'Payée' : 'Non payée' }}
            </span>
          </div>
          <div>
            <span class="recap-label">Statut</span>
            <strong>{{ order.status_label ?? statusLabel(order.status) }}</strong>
            <span v-if="order.tracking_number" class="recap-sub">Suivi : {{ order.tracking_number }}</span>
          </div>
        </div>
      </div>

      <!--
        Frais de livraison — hors {{ settings.homeCity }} et international uniquement.

        Placé avant tout le reste, et bloquant tant qu'il est vide : chacune
        des actions qui suivent annonce un montant à la cliente (lien de
        paiement, notification, expédition). Un total annoncé puis corrigé à la
        hausse, une cliente le refuse — et le serveur refuse d'ailleurs ces
        actions de son côté, ceci n'est que le raccourci de saisie.

        Le champ reste modifiable même une fois rempli : le transporteur peut
        revoir son prix avant l'envoi.
      -->
      <div
        v-if="feeEditable"
        class="modal__section fee-section"
        :class="{ 'fee-section--blocking': feePending }"
      >
        <h4>
          Frais de livraison
          <span v-if="feePending" class="fee-flag">à renseigner</span>
        </h4>

        <p v-if="feePending" class="fee-warn">
          {{ feeMessage }}
        </p>

        <div class="action-row">
          <input
            v-model.number="fee"
            type="number"
            min="0"
            step="100"
            class="input fee-input"
            placeholder="Ex. 3000"
            :disabled="busy === 'fee'"
            @keyup.enter="saveFee"
          />
          <button
            class="btn btn-primary"
            type="button"
            :disabled="busy === 'fee' || fee === '' || fee === null || Number(fee) < 0"
            @click="saveFee"
          >
            {{ busy === 'fee' ? 'Enregistrement…' : 'Enregistrer les frais' }}
          </button>
        </div>

        <p class="modal__hint">
          Sous-total {{ fmt(order.subtotal) }} · Nouveau total :
          <strong>{{ fmt(totalWithFee) }}</strong>
        </p>
      </div>

      <!--
        Changement de statut.

        Le serveur garde la main sur ce qui compte : il refuse d'expédier une
        commande impayée ou aux frais non fixés, et remet le stock en rayon
        sur une annulation. Ici on ne fait que demander confirmation avant les
        gestes qui ne se défont pas simplement.
      -->
      <div class="modal__section">
        <h4>Statut de la commande</h4>
        <div class="action-row">
          <select v-model="nextStatus" class="input status-select" :disabled="busy === 'status'">
            <option v-for="s in STATUS_OPTIONS" :key="s.value" :value="s.value">
              {{ s.label }}{{ s.value === order.status ? ' (actuel)' : '' }}
            </option>
          </select>
          <button
            class="btn btn-primary"
            type="button"
            :disabled="nextStatus === order.status || busy === 'status'"
            @click="changeStatus()"
          >
            {{ busy === 'status' ? 'Enregistrement…' : 'Changer le statut' }}
          </button>
        </div>
        <p v-if="statusHint" class="modal__hint">{{ statusHint }}</p>
      </div>

      <!-- Action paiement -->
      <div class="modal__section" v-if="!order.paid_at">
        <h4>1. Paiement</h4>
        <div class="action-row">
          <button
            class="btn btn-success"
            :disabled="busy === 'mark-paid' || feePending"
            @click="markPaid"
          >
            {{ busy === 'mark-paid' ? 'Validation…' : 'Marquer comme payée (cash / COD)' }}
          </button>
          <!-- <button
            class="btn btn-outline"
            :disabled="busy === 'pay-link'"
            @click="generatePaymentLink"
          >
            {{ busy === 'pay-link' ? 'Génération…' : 'Générer un lien de paiement' }}
          </button> -->
         
          <button
            class="btn btn-outline"
            :disabled="feePending"
            @click="generateMerchantLink"
          >
            Générer lien marchand Wave
          </button>
        </div>

        <!--
          Tous les moyens actifs, d'un coup.

          La cliente n'a pas forcément le compte qu'on lui propose : lui
          envoyer le seul Wave, c'est l'obliger à répondre « je n'ai que
          Orange » et refaire un aller-retour. On lui donne le choix.
          Les moyens désactivés dans les Paramètres n'apparaissent pas.
        -->
        <div v-if="payOptions.length" class="pay-options">
          <div class="pay-options__head">
            <span>Moyens de paiement à proposer</span>
            <a
              v-if="waAllOptionsLink && !feePending"
              :href="waAllOptionsLink"
              target="_blank"
              rel="noopener"
              class="btn btn-xs btn-primary"
            >
              Tout envoyer via WhatsApp
            </a>
          </div>

          <ul class="pay-options__list">
            <li v-for="opt in payOptions" :key="opt.key" class="pay-option">
              <span class="pay-option__badge" :class="`pay-option__badge--${opt.key}`">{{ opt.sigle }}</span>
              <span class="pay-option__info">
                <strong>{{ opt.label }}</strong>
                <span class="pay-option__value">{{ opt.value }}</span>
              </span>
              <span class="pay-option__actions">
                <button class="btn btn-xs btn-outline" @click="copy(opt.value)">Copier</button>
                <a
                  v-if="opt.waLink && !feePending"
                  :href="opt.waLink"
                  target="_blank"
                  rel="noopener"
                  class="btn btn-xs btn-outline"
                >Envoyer</a>
              </span>
            </li>
          </ul>

          <p v-if="feePending" class="modal__hint">
            Renseignez d'abord les frais de livraison : le montant à régler
            n'est pas encore arrêté.
          </p>
        </div>
        <div v-if="paymentLink" class="payment-link-box">
          <p>Lien généré (CinetPay) :</p>
          <div class="payment-link-row">
            <input :value="paymentLink" readonly class="input" />
            <button class="btn btn-xs btn-outline" @click="copy(paymentLink)">Copier</button>
            <a :href="waPayLink" target="_blank" rel="noopener" class="btn btn-xs btn-primary">
              Envoyer via WhatsApp
            </a>
          </div>
        </div>
        <div v-if="merchantLink" class="payment-link-box">
          <p>Lien marchand Wave ({{ fmt(order.total) }}) :</p>
          <div class="payment-link-row">
            <input :value="merchantLink" readonly class="input" />
            <button class="btn btn-xs btn-outline" @click="copy(merchantLink)">Copier</button>
            <a :href="merchantLink" target="_blank" rel="noopener" class="btn btn-xs btn-outline">
              Ouvrir
            </a>
            <a :href="waMerchantLink" target="_blank" rel="noopener" class="btn btn-xs btn-primary">
              Envoyer via WhatsApp
            </a>
          </div>
        </div>
      </div>
      <div class="modal__section" v-else>
        <p class="modal__info">Paiement déjà enregistré.</p>
      </div>

      <!-- Action tracking -->
      <div class="modal__section">
        <h4>2. Numéro de suivi & envoi client</h4>
        <div class="action-row">
          <input
            v-model="tracking"
            type="text"
            class="input"
            placeholder="Ex. RB-2026-00123"
            :disabled="busy === 'tracking'"
          />
          <button
            class="btn btn-outline"
            type="button"
            :disabled="busy === 'gen-tracking' || busy === 'tracking'"
            @click="generateTracking"
            title="Générer un numéro de suivi"
          >
            {{ busy === 'gen-tracking' ? '…' : 'Générer' }}
          </button>
          <button
            class="btn btn-primary"
            :disabled="!tracking.trim() || busy === 'tracking' || feePending"
            @click="saveTracking()"
          >
            {{ busy === 'tracking' ? 'Enregistrement…' : 'Confirmer + marquer expédiée' }}
          </button>
        </div>
        <p v-if="tracking.trim() && clientPhone" class="modal__hint">
          Après confirmation, un lien WhatsApp pré-rempli s'ouvrira pour notifier le client.
        </p>
      </div>

      <!-- Notification de la cliente -->
      <div class="modal__section">
        <h4>3. Notifier la cliente</h4>
        <div class="action-row">
          <!--
            Envoi automatique par l'API WhatsApp, mis en commentaire.

            L'API Cloud n'autorise l'envoi libre que dans les 24 h suivant un
            message de la cliente : hors de cette fenêtre l'envoi échoue, et
            l'agent retombait sur le repli manuel. On ouvre donc directement
            WhatsApp avec le message rédigé — l'agent l'envoie depuis le
            téléphone de la boutique, qui ne connaît pas cette limite.
            Pour réactiver : décommenter ce bouton et le bloc de repli plus bas.

          <button
            class="btn btn-primary"
            type="button"
            :disabled="!clientPhone || busy === 'notify' || feePending"
            @click="notifyNow"
            title="Envoie le récapitulatif et la facture par WhatsApp"
          >
            {{ busy === 'notify' ? 'Envoi…' : 'Notifier directement' }}
          </button>
          -->
          <a
            v-if="clientPhone && !feePending"
            :href="waRecapLink"
            target="_blank"
            rel="noopener"
            class="btn btn-primary"
            :class="{ 'btn--loading': !waRecapReady }"
            title="Ouvre WhatsApp avec le récapitulatif de la commande, prêt à envoyer"
          >
            {{ waRecapReady ? 'Notifier la cliente sur WhatsApp' : 'Préparation du message…' }}
          </a>
          <button
            v-else
            class="btn btn-primary"
            type="button"
            disabled
            :title="feePending ? 'Renseignez d\'abord les frais de livraison' : 'Aucun numéro sur cette commande'"
          >
            Notifier la cliente sur WhatsApp
          </button>
          <button
            class="btn btn-outline"
            type="button"
            :disabled="busy === 'invoice'"
            @click="downloadInvoice"
          >
            {{ busy === 'invoice' ? '…' : 'Facture PDF' }}
          </button>
        </div>

        <p v-if="!clientPhone" class="modal__hint">
          Aucun numéro sur cette commande — seule la facture PDF est disponible.
        </p>
        <p v-else-if="feePending" class="modal__hint">
          Renseignez d'abord les frais de livraison : le récapitulatif annoncerait un total faux.
        </p>
        <p v-else class="modal__hint">
          WhatsApp s'ouvre avec le récapitulatif : articles, total et consignes de paiement.
          Joignez la facture PDF si la cliente la demande.
        </p>

        <!--
          Repli manuel de l'envoi automatique, mis en commentaire avec lui : il
          n'apparaissait qu'après un refus de l'API.

        <div v-if="notifyFallback" class="notify-fallback">
          <p>
            L'envoi automatique n'a pas abouti. Vous pouvez envoyer le message
            vous-même — il est déjà rédigé.
          </p>
          <div class="action-row">
            <a :href="waNotifyLink" target="_blank" rel="noopener" class="btn btn-xs btn-primary">
              Ouvrir WhatsApp
            </a>
            <button class="btn btn-xs btn-outline" @click="copy(notifyFallback)">
              Copier le message
            </button>
            <button class="btn btn-xs btn-outline" @click="downloadInvoice">
              Facture PDF
            </button>
          </div>
        </div>
        -->
      </div>

      <!-- Lien détails -->
      <footer class="modal__footer">
        <RouterLink
          :to="{ name: 'admin.order', params: { id: order.id } }"
          class="btn btn-outline"
          @click="$emit('close')"
        >
          Voir détails complets →
        </RouterLink>
        <p v-if="error" class="form-error">{{ error }}</p>
        <p v-if="success" class="form-success">{{ success }}</p>
      </footer>
    </div>
  </component>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import api from '@/api';
import { useSettingsStore } from '@/stores/settings';

const props = defineProps({
  order:  { type: Object,  required: true },
  inline: { type: Boolean, default: false },
});
const emit = defineEmits(['close', 'updated']);

const settings = useSettingsStore();

// Lien marchand Wave par défaut si non configuré dans Paramètres (payment_wave_link)
const WAVE_MERCHANT_DEFAULT = 'https://pay.wave.com/m/M_sn_-VVZrJ_wEEIP/c/sn/';

const tracking      = ref(props.order.tracking_number ?? '');
const busy          = ref(null); // 'mark-paid' | 'pay-link' | 'tracking'
const error         = ref('');
const success       = ref('');
const paymentLink   = ref(null);
const merchantLink  = ref(null);

// ── Frais de livraison ───────────────────────────────────────────────────────
//
// L'état vient du serveur (`shipping_fee_pending` / `shipping_fee_editable`),
// qui applique la même règle que ses garde-fous. On en garde une copie locale
// pour que le panneau se débloque dès l'enregistrement, sans attendre que le
// parent recharge sa liste.
const feeEditable = computed(() => !!props.order.shipping_fee_editable);
const feePending  = ref(!!props.order.shipping_fee_pending);
const fee         = ref(Number(props.order.shipping_cost) || '');

const feeMessage = computed(() =>
  props.order.destination === 'international'
    ? "Commande internationale : renseignez les frais avant d'annoncer un montant à la cliente."
    : `Livraison hors ${settings.homeCity} : renseignez les frais avant d'annoncer un montant au client.`
);

const totalWithFee = computed(() => {
  const sousTotal = Number(props.order.subtotal) || 0;
  const remise    = Number(props.order.discount_amount) || 0;
  return Math.max(0, sousTotal - remise + (Number(fee.value) || 0));
});

async function saveFee() {
  if (busy.value) return;
  busy.value = 'fee';
  error.value = '';
  success.value = '';
  try {
    const { data } = await api.put(`/admin/orders/${props.order.id}`, {
      shipping_cost: Number(fee.value) || 0,
    });
    const majOrder = data.data ?? data;

    // Zéro n'est pas un tarif : le serveur continue de considérer les frais
    // comme non fixés, le panneau doit le refléter.
    feePending.value = !!majOrder.shipping_fee_pending;
    success.value = feePending.value
      ? 'Frais enregistrés à 0 — le montant reste considéré comme non fixé.'
      : `Frais enregistrés. Nouveau total : ${fmt(majOrder.total)}`;

    // `partial` : la ligne doit refléter le nouveau total, mais la commande
    // n'est pas traitée pour autant — il reste à notifier ou à expédier.
    emit('updated', majOrder, { partial: true });
  } catch (e) {
    error.value = e.response?.data?.message ?? "Les frais n'ont pas pu être enregistrés.";
  } finally {
    busy.value = null;
  }
}

const clientName = computed(() => {
  const addr = props.order.shipping_address || {};
  return props.order.user?.name
    ?? [addr.first_name, addr.last_name].filter(Boolean).join(' ')
    ?? '—';
});
const clientPhone = computed(() => {
  return props.order.shipping_address?.phone ?? props.order.user?.phone ?? '';
});

const waPayLink = computed(() => buildWaLink(
  clientPhone.value,
  `Bonjour ${clientName.value.split(' ')[0]}\n\nVoici le lien de paiement pour votre commande ${props.order.number} (${fmt(props.order.total)}) :\n${paymentLink.value}\n\nMerci de procéder au paiement pour finaliser votre commande.\n\n${settings.shopName}`,
));

function buildWaLink(phoneRaw, message) {
  if (!phoneRaw) return '#';
  const phone = String(phoneRaw).replace(/[^0-9]/g, '');
  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}

// ── Changement de statut ─────────────────────────────────────────────────────

const STATUS_OPTIONS = [
  { value: 'pending',    label: 'En attente' },
  { value: 'confirmed',  label: 'Confirmée' },
  { value: 'processing', label: 'En préparation' },
  { value: 'shipped',    label: 'Expédiée' },
  { value: 'delivered',  label: 'Livrée' },
  { value: 'cancelled',  label: 'Annulée' },
  { value: 'refunded',   label: 'Remboursée' },
];

const nextStatus = ref(props.order.status);

// La commande a changé ailleurs (suivi, paiement) : le choix repart du statut réel.
watch(() => props.order.status, (s) => { nextStatus.value = s; });

/** Ce que le changement choisi va entraîner, dit avant de cliquer. */
const statusHint = computed(() => {
  if (nextStatus.value === props.order.status) return '';
  return {
    shipped:   "La date d'expédition est enregistrée. Une commande impayée ou aux frais non fixés demandera confirmation.",
    delivered: 'La date de livraison est enregistrée.',
    cancelled: 'Les articles retournent en stock.',
    refunded:  "Le remboursement n'est pas effectué ici : seul le statut change.",
  }[nextStatus.value] ?? '';
});

async function changeStatus(force = false) {
  const cible = nextStatus.value;
  if (cible === props.order.status || busy.value) return;

  // Gestes difficiles à défaire : une annulation remet le stock en rayon.
  if (!force && ['cancelled', 'refunded'].includes(cible)) {
    const libelle = statusLabel(cible).toLowerCase();
    if (!confirm(`Passer la commande ${props.order.number} en « ${libelle} » ?`)) return;
  }

  busy.value = 'status';
  error.value = ''; success.value = '';
  try {
    const { data } = await api.patch(`/admin/orders/${props.order.id}`, {
      status: cible,
      ...(force ? { force: true } : {}),
    });
    emit('updated', data.data ?? data);
    success.value = `Statut changé : ${statusLabel(cible)}.`;
  } catch (e) {
    // Même garde-fou que l'expédition avec numéro de suivi : l'agent voit
    // pourquoi et peut passer outre en connaissance de cause.
    if (e.response?.status === 422 && e.response?.data?.code === 'shipping_blocked') {
      const raisons = (e.response.data.blockers ?? []).map(b => `• ${b}`).join('\n');
      if (confirm(`Expédition risquée :\n\n${raisons}\n\nExpédier quand même ?`)) {
        busy.value = null;
        return changeStatus(true);
      }
      error.value = e.response.data.message;
    } else {
      error.value = e.response?.data?.message ?? "Le statut n'a pas pu être changé.";
    }
  } finally {
    busy.value = null;
  }
}

// ── Notification WhatsApp ────────────────────────────────────────────────────
//
// Le récapitulatif est rédigé par le serveur — le même texte que l'envoi
// automatique : articles, total, statut et consignes de paiement. Il est
// chargé à l'ouverture pour que le bouton soit un vrai lien : ouvrir WhatsApp
// après une requête ferait bloquer la fenêtre par le navigateur.
const recapMessage = ref('');
const waRecapReady = ref(false);

async function loadRecap() {
  waRecapReady.value = false;
  try {
    const { data } = await api.get(`/admin/orders/${props.order.id}/whatsapp-message`);
    recapMessage.value = data.message ?? '';
  } catch {
    recapMessage.value = '';
  } finally {
    waRecapReady.value = true;
  }
}

/** Message de secours, si le serveur n'a pas pu rédiger le récapitulatif. */
const recapDeSecours = computed(() => [
  `Bonjour ${clientName.value.split(' ')[0]}`,
  '',
  `Votre commande ${props.order.number} (${fmt(props.order.total)}) : ${(props.order.status_label ?? statusLabel(props.order.status)).toLowerCase()}.`,
  '',
  'Merci de votre confiance.',
  settings.shopName,
].join('\n'));

const waRecapLink = computed(() =>
  buildWaLink(clientPhone.value, recapMessage.value || recapDeSecours.value));

onMounted(loadRecap);

// Statut, total, paiement ou suivi modifiés depuis la fenêtre : le message
// doit annoncer l'état à jour, pas celui de l'ouverture.
watch(
  () => [props.order.status, props.order.total, props.order.paid_at, props.order.tracking_number, props.order.payment_method],
  loadRecap,
);

// Message rédigé par le serveur, conservé quand l'envoi automatique échoue :
// l'agent peut alors l'envoyer lui-même plutôt que de le retaper.
// (Envoi automatique mis en commentaire dans le gabarit ; conservé pour le réactiver.)
const notifyFallback = ref('');
const waNotifyLink = computed(() => buildWaLink(clientPhone.value, notifyFallback.value));

/**
 * Envoie le récapitulatif + la facture par WhatsApp, à la demande.
 *
 * L'envoi automatique à la commande peut échouer sans que personne le
 * remarque. En cas de refus de l'API, on n'affiche pas qu'une erreur : on
 * bascule sur le repli manuel, sinon la cliente reste sans nouvelle.
 */
async function notifyNow() {
  busy.value = 'notify';
  error.value = ''; success.value = ''; notifyFallback.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${props.order.id}/notify`);
    success.value = data.document
      ? 'Récapitulatif et facture envoyés par WhatsApp.'
      : 'Récapitulatif envoyé — la facture PDF n’a pas pu être jointe.';
  } catch (e) {
    const data = e.response?.data ?? {};
    error.value = data.reason ?? "L'envoi WhatsApp a échoué.";
    // Le serveur renvoie le message même en échec : on le propose à l'agent.
    if (data.message) notifyFallback.value = data.message;
  } finally {
    busy.value = '';
  }
}

/** Récupère la facture PDF — recours quand WhatsApp ne passe pas. */
async function downloadInvoice() {
  busy.value = 'invoice';
  error.value = '';
  try {
    const res = await api.get(`/admin/orders/${props.order.id}/invoice`, { responseType: 'blob' });
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = `facture-${props.order.number}.pdf`;
    a.click();
    // Libère le blob : sans ça chaque téléchargement fuit en mémoire.
    URL.revokeObjectURL(url);
  } catch {
    error.value = 'Impossible de générer la facture.';
  } finally {
    busy.value = '';
  }
}

async function markPaid() {
  busy.value = 'mark-paid';
  error.value = ''; success.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${props.order.id}/mark-paid`);
    emit('updated', data.data ?? data);
    success.value = 'Commande marquée comme payée.';
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Erreur.';
  } finally {
    busy.value = null;
  }
}

async function generatePaymentLink() {
  busy.value = 'pay-link';
  error.value = ''; success.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${props.order.id}/payment-link`);
    paymentLink.value = data.url;
    success.value = 'Lien généré. Tu peux l\'envoyer au client.';
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Impossible de générer le lien.';
  } finally {
    busy.value = null;
  }
}

/**
 * Génère le lien marchand Wave (pay.wave.com) avec le montant de la commande.
 * Base : setting `payment_wave_link` (admin → Paramètres), sinon lien par défaut.
 * Aucune requête API : le lien est construit localement, prêt à envoyer au client.
 */
function generateMerchantLink() {
  error.value = ''; success.value = '';

  const base = (settings.paymentWaveLink || '').trim() || WAVE_MERCHANT_DEFAULT;
  const amount = Math.round(Number(props.order.total) || 0);
  // Ajoute ?amount= (ou &amount= si la base contient déjà des paramètres)
  const sep = base.includes('?') ? (base.endsWith('?') || base.endsWith('&') ? '' : '&') : '?';

  merchantLink.value = amount ? `${base}${sep}amount=${amount}` : base;
  success.value = 'Lien marchand Wave généré. Tu peux l\'envoyer au client.';
}

/**
 * Moyens de paiement à proposer à la cliente.
 *
 * Seuls ceux activés dans les Paramètres, avec leur numéro (ou le lien
 * marchand pour Wave). Un moyen sans numéro renseigné n'est pas proposé :
 * envoyer « Orange Money : » suivi de rien ne rend service à personne.
 */
const payOptions = computed(() => {
  const s = (settings.data?.value ?? settings.data ?? {});
  const lire = (k) => (s[k] ?? '').toString().trim();
  const actif = (k) => ['1', 'true', 'on'].includes(lire(k).toLowerCase());
  const numeroParDefaut = lire('payment_mobile_number');

  const lienWave = (lire('payment_wave_link') || WAVE_MERCHANT_DEFAULT).trim();
  const montant  = Math.round(Number(props.order.total) || 0);
  const sep      = lienWave.includes('?')
    ? (lienWave.endsWith('?') || lienWave.endsWith('&') ? '' : '&')
    : '?';

  const candidats = [
    {
      key: 'wave', sigle: 'W', label: 'Wave',
      actif: actif('payment_wave_enabled'),
      value: montant ? `${lienWave}${sep}amount=${montant}` : lienWave,
    },
    {
      key: 'orange', sigle: 'OM', label: 'Orange Money',
      actif: actif('payment_orange_money_enabled'),
      value: lire('payment_orange_money_number') || numeroParDefaut,
    },
    {
      key: 'mtn', sigle: 'M', label: 'MTN MoMo',
      actif: actif('payment_mtn_enabled'),
      value: lire('payment_mtn_number') || numeroParDefaut,
    },
  ];

  return candidats
    .filter((o) => o.actif && o.value)
    .map((o) => ({
      ...o,
      waLink: buildWaLink(clientPhone.value, messagePourUnMoyen(o)),
    }));
});

/** Message d'un seul moyen — la cliente en a demandé un en particulier. */
function messagePourUnMoyen(opt) {
  const prenom = clientName.value.split(' ')[0];
  const consigne = opt.key === 'wave'
    ? `Cliquez sur ce lien pour payer directement :\n${opt.value}`
    : `Envoyez ${fmt(props.order.total)} au ${opt.value}.\nIndiquez la référence ${props.order.number} dans le motif.`;

  return [
    `Bonjour ${prenom}`,
    '',
    `Paiement de votre commande ${props.order.number} — ${fmt(props.order.total)}`,
    '',
    `${opt.label} :`,
    consigne,
    '',
    'Envoyez-nous la capture une fois le paiement fait.',
    '',
    'Merci de votre confiance.',
    settings.shopName,
  ].join('\n');
}

/**
 * Message reprenant tous les moyens actifs : la cliente choisit celui qu'elle
 * possède, sans avoir à nous le demander.
 */
const waAllOptionsLink = computed(() => {
  const options = payOptions.value;
  if (!options.length) return null;

  const prenom = clientName.value.split(' ')[0];
  const lignes = options.map((o) => `• ${o.label} : ${o.value}`);

  return buildWaLink(clientPhone.value, [
    `Bonjour ${prenom}`,
    '',
    `Paiement de votre commande ${props.order.number} — ${fmt(props.order.total)}`,
    '',
    'Choisissez le moyen qui vous arrange :',
    ...lignes,
    '',
    `Indiquez la référence ${props.order.number} dans le motif, puis envoyez-nous la capture.`,
    '',
    'Merci de votre confiance.',
    settings.shopName,
  ].join('\n'));
});

const waMerchantLink = computed(() => buildWaLink(
  clientPhone.value,
  `Bonjour ${clientName.value.split(' ')[0]}\n\nVoici votre lien de paiement Wave pour la commande ${props.order.number} (${fmt(props.order.total)}) :\n${merchantLink.value}\n\nCliquez sur le lien pour payer directement avec Wave.\n\nMerci de votre confiance.\n${settings.shopName}`,
));

async function generateTracking() {
  busy.value = 'gen-tracking';
  error.value = '';
  try {
    const { data } = await api.post(`/admin/orders/${props.order.id}/generate-tracking`);
    tracking.value = data.tracking_number;
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Impossible de générer un numéro.';
  } finally {
    busy.value = null;
  }
}

async function saveTracking(force = false) {
  busy.value = 'tracking';
  error.value = ''; success.value = '';
  try {
    const { data } = await api.patch(`/admin/orders/${props.order.id}`, {
      tracking_number: tracking.value.trim(),
      status: 'shipped',
      ...(force ? { force: true } : {}),
    });
    emit('updated', data.data ?? data);
    success.value = 'Numéro enregistré, commande marquée expédiée.';

    // Pré-remplir WhatsApp pour notifier le client
    if (clientPhone.value) {
      const msg = `Bonjour ${clientName.value.split(' ')[0]}\n\nVotre commande ${props.order.number} a été expédiée !\n\n📦 Numéro de suivi : ${tracking.value.trim()}\n\nVous serez livré(e) très bientôt.\n\nMerci de votre confiance.\n${settings.shopName}`;
      window.open(buildWaLink(clientPhone.value, msg), '_blank', 'noopener');
    }
  } catch (e) {
    // Expédition bloquée (hors zone sans frais / impayée) → confirmation explicite
    if (e.response?.status === 422 && e.response?.data?.code === 'shipping_blocked') {
      const raisons = (e.response.data.blockers ?? []).map(b => `• ${b}`).join('\n');
      if (confirm(`Expédition risquée :\n\n${raisons}\n\nExpédier quand même ?`)) {
        busy.value = null;
        return saveTracking(true);
      }
      error.value = e.response.data.message;
    } else {
      error.value = e.response?.data?.message ?? 'Erreur.';
    }
  } finally {
    busy.value = null;
  }
}

function copy(text) {
  navigator.clipboard?.writeText(text);
  success.value = 'Copié dans le presse-papier.';
}

function fmt(v) {
  // Montant masqué (finance privée) → tiret
  if (v === null || v === undefined) return '—';
  return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'XOF', minimumFractionDigits: 0 }).format(Number(v));
}

const STATUS_LABELS = {
  pending: 'En attente', confirmed: 'Confirmée', processing: 'En préparation',
  shipped: 'Expédiée', delivered: 'Livrée', cancelled: 'Annulée', refunded: 'Remboursée',
};
function statusLabel(s) { return STATUS_LABELS[s] ?? s; }

const PAYMENT_LABELS = {
  wave: 'Wave', orange_money: 'Orange Money', card: 'Carte bancaire', stripe: 'Carte bancaire', cinetpay: 'Carte bancaire',
  cod: 'Paiement à la livraison', delivery: 'Paiement à la livraison', cash: 'Espèces',
};
function paymentLabel(p) { return PAYMENT_LABELS[p] ?? p ?? '—'; }
</script>

<style scoped>
/* ── Moyens de paiement proposés ── */
.pay-options {
  margin-top: 14px;
  padding: 12px 14px;
  border: 1px solid #f0e8e3;
  border-radius: 12px;
  background: #fffdfc;
}

.pay-options__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 10px;
  font-size: 12px;
  font-weight: 700;
  color: #8a7f78;
  text-transform: uppercase;
  letter-spacing: .03em;
}

.pay-options__list { list-style: none; margin: 0; padding: 0; }

.pay-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  border-bottom: 1px solid #faf6f4;
}
.pay-option:last-child { border-bottom: none; }

.pay-option__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 9px;
  font-size: 11px;
  font-weight: 800;
  color: #fff;
  flex-shrink: 0;
}
.pay-option__badge--wave   { background: #1dc3f0; }
.pay-option__badge--orange { background: #ff6600; }
.pay-option__badge--mtn    { background: #ffcb00; color: #333; }

.pay-option__info { display: flex; flex-direction: column; gap: 1px; min-width: 0; flex: 1; }
.pay-option__value {
  font-size: 12px;
  color: #8a7f78;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.pay-option__actions { display: flex; gap: 6px; flex-shrink: 0; }

/* ── Frais de livraison ── */
.fee-section--blocking {
  border-left: 3px solid #f59e0b;
  background: #fffbeb;
}

.fee-flag {
  margin-left: 8px;
  padding: 2px 8px;
  border-radius: 999px;
  background: #fde68a;
  color: #92400e;
  font-size: 11px;
  font-weight: 700;
  vertical-align: middle;
}

.fee-warn {
  margin: 0 0 10px;
  font-size: 13px;
  line-height: 1.5;
  color: #92400e;
}

.fee-input { max-width: 180px; }

.status-select { max-width: 240px; }

/* Lien affiché avant que le message du serveur soit prêt : cliquable (le
   message de secours s'applique), mais visiblement en cours. */
.btn--loading { opacity: 0.75; }

.modal-overlay {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(0,0,0,0.45);
  display: flex; align-items: center; justify-content: center;
  padding: var(--space-4);
}

/* ── Mode inline (dans la ligne du tableau) ── */
.qa-inline {
  background: var(--cream-50);
  padding: 0;
}
.qa-panel {
  background: #fff;
  border-left: 3px solid var(--rose-500);
  margin: 0;
  padding: 0;
}
.qa-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: var(--space-3) var(--space-4);
  border-bottom: 1px solid var(--cream-200);
  background: var(--cream-50);
}
.qa-header h3 { font-family: var(--font-display); font-size: 1rem; margin: 2px 0 0; }
.qa-close {
  width: 26px; height: 26px; border-radius: 50%;
  background: transparent; color: var(--gray-500);
  display: flex; align-items: center; justify-content: center;
  font-size: 0.875rem; cursor: pointer; border: 1px solid var(--cream-300);
}
.qa-close:hover { background: var(--rose-500); color: #fff; border-color: var(--rose-500); }
.qa-inline .modal__section { padding: var(--space-3) var(--space-4); }
.qa-inline .recap-grid { grid-template-columns: repeat(4, 1fr); }
.qa-inline .modal__footer { padding: var(--space-3) var(--space-4); }
@media (max-width: 900px) {
  .qa-inline .recap-grid { grid-template-columns: repeat(2, 1fr); }
}
.modal {
  background: #fff;
  border-radius: var(--radius-lg);
  width: 100%;
  max-width: 600px;
  max-height: 92vh;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}
.modal__header {
  display: flex; justify-content: space-between; align-items: flex-start;
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--cream-200);
}
.modal__header h3 { font-family: var(--font-display); font-size: 1.125rem; margin: 0; }
.modal__close {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--cream-100); color: var(--gray-600);
  display: flex; align-items: center; justify-content: center;
  font-size: 1rem; cursor: pointer; border: none;
}
.modal__close:hover { background: var(--rose-500); color: #fff; }

.modal__section {
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--cream-100);
}
.modal__section h4 {
  font-family: var(--font-display);
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-700);
  margin: 0 0 var(--space-3);
}
.modal__info { color: var(--gray-500); font-size: 0.875rem; }
.modal__hint { font-size: 0.75rem; color: var(--gray-400); margin-top: var(--space-2); }

/* Repli manuel après échec d'envoi : teinté d'ambre, pour signaler qu'une
   action reste à faire sans dramatiser comme le ferait du rouge. */
.notify-fallback {
  margin-top: var(--space-3);
  padding: var(--space-3);
  background: #fffbeb;
  border: 1px solid #fcd34d;
  border-radius: var(--radius-sm, 6px);
}
.notify-fallback p {
  font-size: 0.8125rem;
  color: #92400e;
  margin: 0 0 var(--space-2);
}

.recap-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: var(--space-3) var(--space-4);
}
.recap-grid > div { display: flex; flex-direction: column; gap: 2px; }
.recap-label {
  font-size: 0.625rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--gray-400);
  font-weight: 600;
}
.recap-grid strong { font-size: 0.9375rem; color: var(--gray-800); }
.recap-sub { font-size: 0.75rem; color: var(--gray-500); }

.pmt-state--ok      { color: #15803d; font-weight: 600; }
.pmt-state--pending { color: var(--gold-600, #b45309); font-weight: 600; }

.action-row {
  display: flex; gap: var(--space-2); flex-wrap: wrap;
}
.action-row .input { flex: 1; min-width: 200px; }
.action-row .btn { white-space: nowrap; }

.btn-success {
  background: #15803d; color: #fff; border: none;
}
.btn-success:hover:not(:disabled) { background: #166534; }

.payment-link-box {
  margin-top: var(--space-3);
  padding: var(--space-3);
  background: var(--rose-50);
  border-radius: var(--radius-md);
}
.payment-link-box p { font-size: 0.75rem; color: var(--rose-700); margin: 0 0 var(--space-2); }
.payment-link-row {
  display: flex; gap: var(--space-2); align-items: center; flex-wrap: wrap;
}
.payment-link-row .input { flex: 1; min-width: 200px; font-size: 0.75rem; }

.modal__footer {
  padding: var(--space-4) var(--space-5);
  display: flex; flex-direction: column; gap: var(--space-2);
  align-items: flex-start;
}
.form-error   { color: #ef4444; font-size: 0.8125rem; }
.form-success { color: #15803d; font-size: 0.8125rem; }
.btn-xs { padding: 4px 8px !important; font-size: 0.6875rem !important; }
</style>
