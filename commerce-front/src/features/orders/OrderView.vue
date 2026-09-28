<template>
  <main class="order-page">
    <div class="container order-content">
      <!-- Back -->
      <RouterLink to="/orders" class="order-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M19 12H5M12 19l-7-7 7-7"/>
        </svg>
        {{ $t('common.myOrders') }}
      </RouterLink>

      <!-- Loading -->
      <div v-if="loading" class="order-loader">
        <div class="order-loader__spinner"></div>
      </div>

      <!-- Error -->
      <div v-else-if="!order" class="order-empty card">
        <div class="order-empty__icon"><FlowerMark /></div>
        <h2 class="display-md">{{ $t('orders.notFound') }}</h2>
        <RouterLink to="/orders" class="btn btn-primary">{{ $t('common.back') }}</RouterLink>
      </div>

      <!-- Detail -->
      <div v-else class="order-blocks">
        <!-- Header -->
        <header class="order-header card">
          <div class="order-header__inner">
            <div>
              <span class="eyebrow">{{ $t('orders.orderLabel') }}</span>
              <h1 class="display-md order-header__title">{{ order.number }}</h1>
              <p class="order-header__date">{{ $t('orders.orderedOn', { date: formatDate(order.created_at) }) }}</p>
            </div>
            <div class="order-header__actions">
              <span :class="statusBadge(order.status)">{{ statusLabel(order.status) }}</span>
              <div class="order-header__btns">
                <button @click="downloadInvoice" :disabled="downloadingPdf" class="order-pdf-btn" :title="$t('orders.invoice')">
                  <svg v-if="!downloadingPdf" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17v3a1 1 0 001 1h16a1 1 0 001-1v-3M3 7V4a1 1 0 011-1h4l2 3h8a1 1 0 011 1v3" />
                  </svg>
                  <svg v-else width="13" height="13" class="order-pdf-btn__spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke-linecap="round"/>
                  </svg>
                  {{ $t('orders.invoice') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Bannière "Payer maintenant" : commande en ligne non réglée -->
          <div v-if="showPayNow" class="order-pay-banner">
            <div class="order-pay-banner__body">
              <strong>{{ $t('orders.payPendingTitle') }}</strong>
              <p>{{ $t('orders.payPendingDescBefore') }} <strong>{{ formatPrice(order.total) }}</strong> {{ $t('orders.payPendingDescAfter') }}</p>
            </div>
            <button class="order-pay-banner__btn" :disabled="paying" @click="payNow">
              <span v-if="paying" class="order-pay-banner__spin"></span>
              <span v-else>{{ $t('orders.payNow') }}</span>
            </button>
          </div>

          <!--
            Promised delivery date, computed server-side on the extraction
            calendar. Rendered here from the structured value so it follows the
            shop language; conditional while payment (or the fee) is missing.
          -->
          <div v-if="deliveryText" class="order-eta">
            <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h11v9H3zM14 10h4l3 3v3h-7zM7 19a2 2 0 100-4 2 2 0 000 4zM17 19a2 2 0 100-4 2 2 0 000 4z" />
            </svg>
            <div>
              <strong>{{ deliveryText }}</strong>
              <p v-if="order.delivery_estimate.conditional" class="order-eta__note">
                {{ order.delivery_estimate.zone === 'sous_region' || order.delivery_estimate.zone === 'international'
                  ? $t('orders.etaConditionalAbroad')
                  : $t('orders.etaConditional') }}
              </p>
            </div>
          </div>

          <!-- Tracking -->
          <div v-if="order.tracking_number" class="order-tracking">
            <strong>{{ $t('orders.trackingNumber') }} :</strong> {{ order.tracking_number }}
          </div>

          <!-- Bannière Wave : payer + envoyer la capture -->
          <div v-if="isWavePayment && (wavePayUrl || settings.payment_wave_number || settings.payment_mobile_number)" class="order-wave-banner">
            <div class="order-wave-banner__icon">
              <svg width="28" height="28" viewBox="0 0 36 36" fill="none"><circle cx="18" cy="18" r="18" fill="#0E74F5"/><text x="18" y="24" text-anchor="middle" font-size="16" font-weight="bold" fill="white">W</text></svg>
            </div>
            <div class="order-wave-banner__body">
              <strong>{{ $t('orders.wavePayTitle') }}</strong>

              <!-- Montant + bouton inline (hors Abidjan : prépaiement mis en avant) -->
              <div class="order-wave-amount-row">
                <p>{{ $t('orders.amountToPay') }} <strong>{{ formatPrice(order.total) }}</strong></p>
                <a
                  v-if="wavePayUrl && isOutsideAbidjan"
                  :href="wavePayUrl"
                  target="_blank"
                  rel="noopener"
                  class="order-wave-pay order-wave-pay--inline"
                >
                  {{ $t('orders.payWithWave') }}
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                </a>
              </div>

              <!-- 1) Bouton payer avec Wave pleine largeur (cas Abidjan) -->
              <a v-if="wavePayUrl && !isOutsideAbidjan" :href="wavePayUrl" target="_blank" rel="noopener" class="order-wave-pay">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                {{ $t('orders.payAmountWithWave', { amount: formatPrice(order.total) }) }}
              </a>

              <!-- Fallback : numéro à défaut de lien -->
              <div v-else-if="settings.payment_wave_number || settings.payment_mobile_number" class="order-wave-banner__number-wrapper">
                <p class="order-wave-banner__number">{{ settings.payment_wave_number || settings.payment_mobile_number }}</p>
                <CopyButton
                  :text="settings.payment_wave_number || settings.payment_mobile_number"
                  :title="$t('orders.copyNumber')"
                  :copied-text="$t('orders.copied')"
                />
              </div>

              <!-- 2) Envoyer la capture via WhatsApp -->
              <div class="order-wave-steps">
                <p class="order-wave-steps__label">{{ $t('orders.afterPayment') }}</p>
                <a v-if="paymentProofLink" :href="paymentProofLink" target="_blank" rel="noopener" class="order-wave-proof">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413A11.815 11.815 0 0012.05 0"/></svg>
                  {{ $t('orders.sendProofWhatsapp') }}
                </a>
              </div>

              <p class="order-wave-banner__hint">{{ $t('orders.referenceHintBefore') }}
                <strong>{{ order.number }}</strong> {{ $t('orders.referenceHintAfter') }}</p>
            </div>
          </div>

          <!-- WhatsApp -->
          <div v-if="waLink" class="order-whatsapp">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="order-whatsapp__icon">
              <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            <div class="order-whatsapp__text">
              <strong>{{ $t('orders.sendToSeller') }}</strong>
              <p>{{ $t('orders.sendToSellerDesc') }}</p>
            </div>
            <a :href="waLink" target="_blank" rel="noopener" class="order-whatsapp__btn">
              {{ $t('common.send') }}
            </a>
          </div>
        </header>

        <!-- Items -->
        <section class="card order-block">
          <h2 class="order-block__title">{{ $t('orders.orderedItems') }}</h2>
          <ul class="order-items">
            <li v-for="item in order.items" :key="item.id" class="order-item">
              <img
                :src="item.product_image || '/placeholder.png'"
                :alt="item.product_name"
                class="order-item__img"
                v-img-fallback
              />
              <div class="order-item__info">
                <p class="order-item__name">{{ item.product_name }}</p>
                <p v-if="item.variant_label" class="order-item__variant">{{ item.variant_label }}</p>
                <p class="order-item__qty">{{ $t('common.quantity') }} : {{ item.quantity }}</p>
              </div>
              <p class="order-item__price">
                {{ formatPrice(item.unit_price * item.quantity) }}
              </p>
            </li>
          </ul>
        </section>

        <!-- Totals -->
        <section class="card order-block">
          <h2 class="order-block__title">{{ $t('orders.summary') }}</h2>
          <ul class="order-totals">
            <li>
              <span>{{ $t('common.subtotal') }}</span>
              <span>{{ formatPrice(order.subtotal) }}</span>
            </li>
            <li v-if="Number(order.discount_amount) > 0" class="order-totals__discount">
              <span>{{ $t('common.discount') }}</span>
              <span>-{{ formatPrice(order.discount_amount) }}</span>
            </li>
            <li>
              <span>{{ $t('common.shipping') }}</span>
              <!-- Pas de livraison gratuite : 0 = frais pas encore renseignés par nos agents -->
              <span :class="{ 'order-shipping--pending': !(Number(order.shipping_cost) > 0) }">
                {{ Number(order.shipping_cost) > 0 ? formatPrice(order.shipping_cost) : $t('orders.shippingToBeSet') }}
              </span>
            </li>
            <li class="order-totals__final">
              <span>{{ $t('common.total') }}</span>
              <span>{{ formatPrice(order.total) }}</span>
            </li>
          </ul>
        </section>

        <!-- Shipping address -->
        <section class="card order-block">
          <h2 class="order-block__title">{{ $t('orders.shippingAddress') }}</h2>
          <address class="order-address">
            <p class="order-address__name">
              {{ order.shipping_address?.first_name }} {{ order.shipping_address?.last_name }}
            </p>
            <p>{{ order.shipping_address?.address_line1 }}</p>
            <p>{{ order.shipping_address?.city }}<span v-if="order.shipping_address?.country"> — {{ countryName(order.shipping_address.country) }}</span></p>
            <p class="order-address__phone">
              <span>{{ $t('orders.phone') }} :</span> {{ order.shipping_address?.phone }}
            </p>
          </address>
        </section>

        <!-- Payment -->
        <section class="card order-block">
          <h2 class="order-block__title">{{ $t('orders.paymentMethod') }}</h2>
          <div class="order-pay-detail">
            <div class="order-pay-detail__row">
              <span class="order-pay-detail__label">{{ $t('orders.payMethodLabel') }}</span>
              <span class="order-pay-detail__value">{{ paymentLabel(order.payment_method ?? order.payments?.[0]?.provider) }}</span>
            </div>
            <div class="order-pay-detail__row">
              <span class="order-pay-detail__label">{{ $t('orders.payStatusLabel') }}</span>
              <span :class="['order-pay-status', order.is_paid ? 'order-pay-status--ok' : 'order-pay-status--pending']">
                {{ order.is_paid ? $t('orders.paid') : $t('orders.awaitingPayment') }}
              </span>
            </div>
            <div v-if="order.payment_reference" class="order-pay-detail__row">
              <span class="order-pay-detail__label">{{ $t('orders.reference') }}</span>
              <span class="order-pay-detail__ref">
                {{ order.payment_reference }}
                <CopyButton :text="order.payment_reference" :title="$t('orders.copyReference')" :copied-text="$t('orders.copied')" />
              </span>
            </div>
            <div v-if="paidPayment" class="order-pay-detail__row">
              <span class="order-pay-detail__label">{{ $t('orders.paidOn') }}</span>
              <span class="order-pay-detail__value">{{ formatDate(paidPayment.paid_at) }}</span>
            </div>
          </div>
        </section>

        <!--
          Frais de livraison à convenir — hors {{ settings.homeCity }} et international.

          Le transport n'est pas tarifé d'avance sur ces destinations : ni le
          montant final ni le moyen de règlement ne sont arrêtés. On met donc
          l'agent en avant plutôt qu'un total provisoire, et le message part
          déjà rédigé pour que la cliente n'ait rien à ressaisir.
        -->
        <section v-if="order.shipping_fee_pending" class="card order-block order-agent">
          <h2 class="order-block__title">{{ $t('orders.agentTitle') }}</h2>
          <p class="order-agent__desc">{{ $t('orders.agentDesc') }}</p>

          <ul class="order-agent__points">
            <li>{{ $t('orders.agentPointFees') }}</li>
            <li>{{ $t('orders.agentPointPayment') }}</li>
          </ul>

          <p class="order-agent__subtotal">
            {{ $t('common.subtotal') }} : <strong>{{ formatPrice(order.subtotal) }}</strong>
            <span class="order-agent__hint">{{ $t('orders.agentSubtotalHint') }}</span>
          </p>

          <!--
            Bascule automatique au retour du tunnel (?agent=1) — pas lors d'une
            simple consultation : une cliente qui revient voir sa commande ne
            doit pas être expédiée sur WhatsApp à chaque visite.
          -->
          <p v-if="agentRedirect.bloque.value" class="order-agent__redirect">
            {{ $t('orders.agentBlocked') }}
          </p>

          <p v-else-if="agentRedirect.active.value" class="order-agent__redirect">
            {{ $t('orders.agentRedirect', { count: agentRedirect.secondes.value }) }}
            <button type="button" class="order-agent__stay" @click="agentRedirect.cancel()">
              {{ $t('orders.agentStay') }}
            </button>
          </p>

          <a
            v-if="waLink"
            :href="waLink"
            target="_blank"
            rel="noopener"
            class="order-agent__btn"
            @click="agentRedirect.cancel()"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            {{ $t('orders.agentCta') }}
          </a>
        </section>

        <!--
          Comment payer. La cliente qui revient sur sa commande quelques heures
          plus tard n'a plus l'écran de confirmation sous les yeux : sans ces
          consignes, elle ne sait ni quel montant envoyer, ni surtout quelle
          référence indiquer — et un virement sans référence ne se rattache à
          aucune commande.
          Mêmes consignes qu'à la confirmation, construites par le même
          composable.
        -->
        <section v-if="payInstructions" class="card order-block">
          <div class="order-payhelp__head">
            <span
              class="order-payhelp__badge"
              :class="`order-payhelp__badge--${payInstructions.method}`"
            >{{ payBadgeLabel }}</span>
            <h2 class="order-block__title">{{ payInstructions.title }}</h2>
          </div>

          <div class="order-payhelp__amount">
            <span>{{ $t('checkout.exactAmount') }}</span>
            <strong>{{ formatPrice(order.total) }}</strong>
          </div>

          <!--
            Le numéro n'est repris ici que pour Orange Money et MTN : la
            bannière Wave plus haut porte déjà soit son lien de paiement, soit
            son numéro. L'afficher deux fois inviterait à se demander lequel
            est le bon.
          -->
          <button
            v-if="!isWavePayment && payInstructions.number"
            type="button"
            class="order-payhelp__copy"
            @click="copyPayValue(payInstructions.number, 'number')"
          >
            <span class="order-payhelp__copy-info">
              <span class="order-payhelp__copy-label">{{ $t('checkout.payNumberLabel') }}</span>
              <span class="order-payhelp__copy-value">{{ payInstructions.number }}</span>
            </span>
            <span class="order-payhelp__copy-action">
              {{ copiedPay === 'number' ? $t('common.copied') : $t('common.copy') }}
            </span>
          </button>

          <button
            type="button"
            class="order-payhelp__copy order-payhelp__copy--sm"
            @click="copyPayValue(order.number, 'ref')"
          >
            <span class="order-payhelp__copy-info">
              <span class="order-payhelp__copy-label">{{ $t('checkout.orderReference') }}</span>
              <span class="order-payhelp__copy-value order-payhelp__copy-value--sm">{{ order.number }}</span>
            </span>
            <span class="order-payhelp__copy-action">
              {{ copiedPay === 'ref' ? $t('common.copied') : $t('common.copy') }}
            </span>
          </button>

          <div class="order-payhelp__steps">
            <p class="order-payhelp__steps-title">{{ $t('checkout.howToProceed') }}</p>
            <p class="order-payhelp__steps-text">{{ payInstructions.instructions }}</p>
          </div>
        </section>

        <!--
          Annulation. Le serveur décide seul si elle est ouverte (délai réglable
          en admin, statut, paiement) : on n'affiche ici que ce qu'il annonce.
          Le bloc reste visible quand elle est fermée, avec le motif — sans lui,
          la cliente chercherait un bouton qui n'existe pas.
        -->
        <section v-if="showCancelBlock" class="card order-block">
          <h2 class="order-block__title">{{ $t('orders.cancelTitle') }}</h2>

          <template v-if="order.can_cancel">
            <p class="order-cancel__window">
              <span v-if="cancelCountdown">{{ $t('orders.cancelRemaining', { time: cancelCountdown }) }}</span>
              <span v-else-if="order.cancellable_until">
                {{ $t('orders.cancelDeadline', { date: formatDate(order.cancellable_until) }) }}
              </span>
            </p>

            <label class="order-cancel__reason">
              <span class="order-cancel__reason-label">{{ $t('orders.cancelReasonLabel') }}</span>
              <input
                v-model="cancelReason"
                type="text"
                maxlength="255"
                class="input"
                :placeholder="$t('orders.cancelReasonPlaceholder')"
              />
            </label>

            <p v-if="cancelError" class="order-cancel__error">{{ cancelError }}</p>

            <button class="order-cancel__btn" :disabled="cancelling" @click="cancelDialog = true">
              {{ cancelling ? $t('orders.cancelling') : $t('orders.cancelButton') }}
            </button>
          </template>

          <p v-else class="order-cancel__blocked">{{ cancelBlockedMessage }}</p>
        </section>
      </div>
    </div>

    <!--
      Confirmation d'annulation.

      `window.confirm` était bloquant, non traduit et non stylé — et sur
      certains navigateurs mobiles il n'apparaît tout simplement pas, ce qui
      rendait le bouton muet.
    -->
    <div v-if="cancelDialog" class="order-dialog" @click.self="cancelDialog = false">
      <div class="order-dialog__panel" role="dialog" aria-modal="true">
        <h3 class="order-dialog__title">{{ $t('orders.cancelDialogTitle') }}</h3>
        <p class="order-dialog__body">{{ $t('orders.cancelDialogBody') }}</p>

        <p v-if="cancelError" class="order-dialog__error">{{ cancelError }}</p>

        <div class="order-dialog__actions">
          <button class="order-dialog__keep" :disabled="cancelling" @click="cancelDialog = false">
            {{ $t('orders.cancelDialogKeep') }}
          </button>
          <button class="order-dialog__confirm" :disabled="cancelling" @click="cancelOrder">
            {{ cancelling ? $t('orders.cancelling') : $t('orders.cancelButton') }}
          </button>
        </div>
      </div>
    </div>

  </main>
</template>

<script setup>
import { useSettingsStore } from '@/stores/settings'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useCurrencyStore } from '@/stores/currency'
import { useRoute, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import api from '@/api'
import { buildAdminMessage, buildWaLink } from '@/utils/whatsapp'
import CopyButton from '@/shared/components/CopyButton.vue'
import { usePaymentInstructions } from '@/composables/usePaymentInstructions'
import { useAgentRedirect } from '@/composables/useAgentRedirect'
import { useToast } from 'vue-toastification'

const { t, locale } = useI18n()
const toast = useToast()
const route = useRoute()

const order    = ref(null)
const loading  = ref(true)
const settings = ref({})
const downloadingPdf     = ref(false)
const copied   = ref(false)
const paying   = ref(false)

// Moyen de paiement de la commande
const orderProvider = computed(() =>
  order.value?.payment_method ?? order.value?.payments?.[0]?.provider ?? ''
)
// GeniusPay (Wave/OM en ligne) activé dans l'admin ?
const geniuspayEnabled = computed(() => settings.value.payment_geniuspay_enabled === 'true')

// Bouton "Payer maintenant" (paiement en ligne) : seulement si une passerelle
// en ligne est réellement disponible — carte bancaire (Stripe) toujours,
// Wave/OM uniquement si GeniusPay est activé. Sinon → instructions manuelles.
// 'cinetpay' reste accepté pour les anciennes commandes créées avant Stripe.
const CARD_PROVIDERS = ['card', 'stripe', 'cinetpay']

const showPayNow = computed(() => {
  if (!order.value || order.value.is_paid) return false
  if (['cancelled', 'refunded'].includes(order.value.status)) return false
  const p = orderProvider.value
  if (CARD_PROVIDERS.includes(p)) return true
  if (['wave', 'orange_money'].includes(p)) return geniuspayEnabled.value
  return false
})

const paidPayment = computed(() =>
  order.value?.payments?.find(p => p.status === 'completed') ?? null
)

// ── Consignes de règlement ───────────────────────────────────────────────────
//
// Uniquement pour les paiements mobiles saisis à la main et pas encore reçus.
// Une commande déjà réglée, annulée ou remboursée n'a plus rien à payer, et un
// règlement par passerelle se fait sur le site de la passerelle : dans les deux
// cas, afficher un mode d'emploi serait au mieux inutile, au pire trompeur.
const { build: buildPayInstructions, isManual: isManualPayment } = usePaymentInstructions()

const payInstructions = computed(() => {
  const commande = order.value
  if (!commande || commande.is_paid) return null
  if (['cancelled', 'refunded'].includes(commande.status)) return null
  // Frais de livraison pas encore arrêtés : le total affiché n'est pas ferme.
  // Donner un « montant exact à envoyer » ici ferait payer la mauvaise somme,
  // et un virement au mauvais montant est pénible à rattraper des deux côtés.
  if (commande.shipping_fee_pending) return null
  if (!isManualPayment(orderProvider.value)) return null

  return buildPayInstructions(settings.value, orderProvider.value, commande.number, commande.total)
})

// Sigle de l'opérateur affiché dans la pastille.
const payBadgeLabel = computed(() => ({
  wave:         'W',
  orange_money: 'OM',
  mtn:          'M',
}[payInstructions.value?.method] ?? ''))

// Bascule vers un agent, uniquement au retour du tunnel de commande.
const agentRedirect = useAgentRedirect(5)

const copiedPay = ref(null)   // 'number' | 'ref' | null

async function copyPayValue(texte, cle) {
  if (!texte) return
  try {
    await navigator.clipboard.writeText(String(texte))
  } catch {
    // `clipboard` exige un contexte sécurisé : sur une page servie en HTTP,
    // ou sur d'anciens navigateurs mobiles, il faut ce repli.
    const zone = document.createElement('textarea')
    zone.value = String(texte)
    document.body.appendChild(zone)
    zone.select()
    try { document.execCommand('copy') } catch { /* rien de plus à tenter */ }
    document.body.removeChild(zone)
  }
  copiedPay.value = cle
  setTimeout(() => { if (copiedPay.value === cle) copiedPay.value = null }, 1800)
}

async function payNow() {
  if (!order.value) return
  paying.value = true
  try {
    const { data } = await api.post(`/orders/${order.value.number}/pay`)
    if (data.payment_url) {
      window.location.href = data.payment_url
    }
  } catch (e) {
    alert(e.response?.data?.message ?? t('orders.payInitError'))
  } finally {
    paying.value = false
  }
}

async function copyPhoneNumber() {
  if (!settings.value.payment_mobile_number) return
  try {
    await navigator.clipboard.writeText(settings.value.payment_mobile_number)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {
    // silent — fallback behavior handled by browser
  }
}

async function downloadInvoice() {
  if (!order.value) return
  downloadingPdf.value = true
  try {
    const response = await api.get(`/orders/${order.value.number}/invoice`, {
      responseType: 'blob',
    })
    const url  = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.download = `facture-${order.value.number}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch {
    // silent — user can retry
  } finally {
    downloadingPdf.value = false
  }
}

async function fetchOrder() {
  loading.value = true
  try {
    const [orderRes, settingsRes] = await Promise.all([
      api.get(`/orders/${route.params.number}`),
      api.get('/settings'),
    ])
    order.value    = orderRes.data.data ?? orderRes.data
    settings.value = settingsRes.data
  } catch {
    order.value = null
  } finally {
    loading.value = false
  }
}

// Instructions de paiement mobile manuel : Wave/OM quand le paiement en ligne
// (GeniusPay) est désactivé et que la commande n'est pas encore réglée.
const isWavePayment = computed(() => {
  if (!order.value || order.value.is_paid) return false
  return ['wave', 'orange_money'].includes(orderProvider.value) && !geniuspayEnabled.value
})

const waLink = computed(() => {
  if (!order.value || !settings.value.whatsapp_admin_number) return null
  if (settings.value.whatsapp_notify_customer !== 'true') return null
  const message = buildAdminMessage(order.value, settings.value)
  return buildWaLink(settings.value.whatsapp_admin_number, message)
})

// Lien « Payer avec Wave » renvoyé par l'API (lien marchand + montant)
const wavePayUrl = computed(() => order.value?.wave_pay_url || null)

// Hors Abidjan (renvoyé par l'API) : prépaiement obligatoire → bouton à côté du montant
const isOutsideAbidjan = computed(() => order.value?.is_abidjan === false)

// Lien WhatsApp pour envoyer la capture du paiement (numéro admin, message pré-rempli)
const paymentProofLink = computed(() => {
  const num = settings.value.whatsapp_admin_number
  if (!num || !order.value) return null
  const msg = t('orders.waveProofMessage', {
    number: order.value.number,
    amount: formatPrice(order.value.total),
  })
  return buildWaLink(num, msg)
})

function formatDate(val) {
  if (!val) return '—'
  return new Date(val).toLocaleDateString(locale.value, { day: '2-digit', month: 'long', year: 'numeric' })
}

/**
 * A calendar day ("2026-09-23") as a weekday phrase. Built from its parts, not
 * parsed as an ISO string: `new Date('2026-09-23')` is UTC midnight, which a
 * customer in the Americas would see as the day before.
 */
function formatDay(ymd) {
  const [y, m, d] = ymd.split('-').map(Number)
  return new Date(y, m - 1, d).toLocaleDateString(locale.value, {
    weekday: 'long', day: 'numeric', month: 'long',
  })
}

const deliveryText = computed(() => {
  const eta = order.value?.delivery_estimate
  if (!eta) return ''

  if (eta.firm) return t('orders.etaFirm', { date: formatDay(eta.from) })
  if (eta.from === eta.to) return t('orders.etaOn', { date: formatDay(eta.from) })
  return t('orders.etaRange', { from: formatDay(eta.from), to: formatDay(eta.to) })
})

function formatPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}

function statusLabel(status) {
  // Le serveur distingue l'attente de paiement de l'attente de traitement ;
  // ici on traduit, la boutique étant bilingue.
  if (order.value?.awaiting_payment && status === 'pending') {
    return t('orders.status.awaiting_payment')
  }
  return t(`orders.status.${status}`) || status
}

function statusBadge(status) {
  const map = {
    pending: 'badge badge-warning',
    processing: 'badge badge-primary',
    shipped: 'badge badge-primary',
    delivered: 'badge badge-success',
    cancelled: 'badge badge-danger',
    refunded: 'badge badge-gray',
  }
  return map[status] ?? 'badge badge-gray'
}

function countryName(code) {
  if (!code) return '—'
  try {
    return new Intl.DisplayNames([locale.value], { type: 'region' }).of(code) ?? code
  } catch {
    return code
  }
}

function paymentLabel(method) {
  const map = {
    wave: 'Wave',
    orange_money: 'Orange Money',
    stripe: t('checkout.stripeCard'),
    virement: t('checkout.bankTransfer'),
    bank_transfer: t('checkout.bankTransfer'),
    delivery: t('checkout.cashOnDelivery'),
    cod: t('checkout.cashOnDelivery'),
  }
  return map[method] ?? method ?? '—'
}

// ── Annulation par la cliente ────────────────────────────────────────────────
//
// Aucune règle n'est rejouée ici : le serveur renvoie `can_cancel`,
// `cancellable_until` et un code de refus. Recalculer le délai côté front
// donnerait deux vérités pour une même commande, et c'est toujours la mauvaise
// qui s'affiche.

const cancelReason = ref('')
const cancelling   = ref(false)
const cancelError  = ref('')
const cancelDialog = ref(false)

// Le bloc reste affiché tant que l'annulation a un sens à évoquer. Sur une
// commande déjà annulée ou livrée, il n'aurait rien à dire.
const showCancelBlock = computed(() => {
  if (!order.value) return false
  if (order.value.can_cancel) return true
  return ['pending', 'confirmed'].includes(order.value.status)
})

// Refus traduit dans la langue de l'interface ; le texte du serveur sert de
// filet si un nouveau code apparaissait sans sa traduction.
const cancelBlockedMessage = computed(() => {
  const code = order.value?.cancel_blocked_code
  if (!code) return ''
  const cle = `orders.cancelBlocked_${code}`
  const traduit = t(cle)
  return traduit === cle ? (order.value.cancel_blocked_reason ?? '') : traduit
})

// Compte à rebours : « 3 h 20 » se lit et se décide mieux qu'une date.
const maintenant = ref(Date.now())
let minuteur = null

const cancelCountdown = computed(() => {
  const limite = order.value?.cancellable_until
  if (!limite) return ''

  const restant = new Date(limite).getTime() - maintenant.value
  if (restant <= 0) return ''

  const heures  = Math.floor(restant / 3600000)
  const minutes = Math.floor((restant % 3600000) / 60000)

  if (heures <= 0) {
    return minutes > 0 ? `${minutes} min` : t('orders.cancelExpiresSoon')
  }
  return minutes > 0 ? `${heures} h ${minutes} min` : `${heures} h`
})

async function cancelOrder() {
  if (!order.value || cancelling.value) return

  cancelling.value  = true
  cancelError.value = ''
  try {
    const { data } = await api.post(`/orders/${order.value.number}/cancel`, {
      reason: cancelReason.value || undefined,
    })
    // La réponse imbrique la ressource sans enveloppe « data ».
    order.value = data.order ?? order.value
    cancelReason.value = ''
    cancelDialog.value = false

    // La page se met à jour en silence : sans notification, la cliente peut
    // douter que le clic ait été pris en compte et recommencer.
    toast.success(t('orders.cancelSuccess'))
  } catch (e) {
    cancelError.value = e.response?.data?.message ?? t('orders.cancelError')
  } finally {
    cancelling.value = false
  }
}

onMounted(async () => {
  await fetchOrder()

  // Retour du tunnel avec des frais encore à fixer : on enchaîne sur l'agent.
  // Le lien dépend des réglages boutique, chargés en parallèle — d'où la
  // fonction, évaluée au moment de basculer et non maintenant.
  if (route.query.agent && order.value?.shipping_fee_pending) {
    agentRedirect.start(() => waLink.value)
  }

  // Une minute suffit : le compte à rebours s'affiche en heures et minutes.
  minuteur = setInterval(() => { maintenant.value = Date.now() }, 60000)
})

onBeforeUnmount(() => clearInterval(minuteur))
</script>

<style scoped>
/* ── Boîte de dialogue d'annulation ── */
.order-dialog {
  position: fixed;
  inset: 0;
  z-index: 80;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background: rgba(30, 20, 16, .5);
}

.order-dialog__panel {
  width: 100%;
  max-width: 400px;
  padding: 26px;
  border-radius: 18px;
  background: #fff;
  box-shadow: 0 24px 60px rgba(0, 0, 0, .25);
}

.order-dialog__title {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 8px;
}

.order-dialog__body {
  font-size: 14px;
  line-height: 1.6;
  color: #6b5f58;
}

.order-dialog__error {
  margin-top: 14px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 13px;
}

.order-dialog__actions {
  display: flex;
  gap: 10px;
  margin-top: 22px;
}

.order-dialog__keep,
.order-dialog__confirm {
  flex: 1;
  padding: 11px 16px;
  border-radius: 999px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s ease;
}

/* Garder sa commande est l'issue la plus probable : c'est elle qu'on met en
   avant, l'annulation reste possible mais ne s'attrape pas par mégarde. */
.order-dialog__keep {
  border: none;
  background: #2d2d2d;
  color: #fff;
}
.order-dialog__keep:hover:not(:disabled) { background: #000; }

.order-dialog__confirm {
  border: 1px solid #e5b4b4;
  background: #fff;
  color: #b91c1c;
}
.order-dialog__confirm:hover:not(:disabled) { background: #fee2e2; }

.order-dialog__keep:disabled,
.order-dialog__confirm:disabled { opacity: .6; cursor: default; }

/* ── Frais à convenir avec un agent ── */
.order-agent__redirect {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f0fdf4;
  font-size: 13px;
  color: #15803d;
  line-height: 1.5;
}
.order-agent__stay {
  display: block;
  margin-top: 4px;
  border: none;
  background: none;
  padding: 0;
  font-size: 12px;
  color: #6b7280;
  text-decoration: underline;
  cursor: pointer;
}

.order-agent { border-left: 3px solid #25d366; }

.order-agent__desc {
  font-size: 14px;
  line-height: 1.6;
  color: #6b5f58;
  margin-bottom: 12px;
}

.order-agent__points {
  list-style: none;
  margin: 0 0 16px;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.order-agent__points li {
  position: relative;
  padding-left: 18px;
  font-size: 13.5px;
  color: #6b5f58;
}
.order-agent__points li::before {
  content: '';
  position: absolute;
  left: 4px;
  top: 8px;
  width: 5px;
  height: 5px;
  border-radius: 50%;
  background: #25d366;
}

.order-agent__subtotal {
  padding: 12px 14px;
  border-radius: 12px;
  background: #f9f4f1;
  font-size: 14px;
  margin-bottom: 16px;
}
.order-agent__hint {
  display: block;
  margin-top: 3px;
  font-size: 12px;
  color: #a89a92;
}

.order-agent__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 13px 20px;
  border-radius: 999px;
  background: #25d366;
  color: #fff;
  font-size: 15px;
  font-weight: 700;
  text-decoration: none;
  transition: background .15s ease;
}
.order-agent__btn:hover { background: #1eb85a; }

/* ── Comment payer ── */
.order-payhelp__head {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
}

.order-payhelp__head .order-block__title { margin-bottom: 0; }

.order-payhelp__badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  font-weight: 800;
  font-size: 0.8125rem;
  letter-spacing: -0.01em;
  color: #fff;
  flex-shrink: 0;
  line-height: 1;
  background: #9ca3af;
}
.order-payhelp__badge--wave         { background: #1dc3f0; }
.order-payhelp__badge--orange_money { background: #ff6600; }
.order-payhelp__badge--mtn          { background: #ffcb00; color: #333; }

.order-payhelp__amount {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
  background: #f9f4f1;
  margin-bottom: 12px;
}
.order-payhelp__amount span { font-size: 13px; color: #8a7f78; }
.order-payhelp__amount strong { font-size: 20px; font-weight: 700; }

.order-payhelp__copy {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  width: 100%;
  padding: 12px 14px;
  margin-bottom: 10px;
  border: 1px solid #f0e8e3;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  text-align: left;
  transition: border-color .15s ease, background .15s ease;
}
.order-payhelp__copy:hover { border-color: #d9c4b8; background: #fffaf8; }

.order-payhelp__copy-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.order-payhelp__copy-label { font-size: 11px; color: #a89a92; }
.order-payhelp__copy-value {
  font-size: 17px;
  font-weight: 700;
  letter-spacing: .02em;
  overflow: hidden;
  text-overflow: ellipsis;
}
.order-payhelp__copy-value--sm { font-size: 14px; letter-spacing: .04em; }
.order-payhelp__copy--sm { padding: 10px 14px; }

.order-payhelp__copy-action {
  flex-shrink: 0;
  font-size: 12px;
  font-weight: 600;
  color: #b5876a;
  white-space: nowrap;
}

.order-payhelp__steps {
  margin-top: 16px;
  padding: 14px 16px;
  border-radius: 12px;
  background: #f9f4f1;
}
.order-payhelp__steps-title {
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 6px;
}
/* Les consignes de la boutique sont saisies avec des retours à la ligne :
   sans `pre-line`, tout s'afficherait en un seul paragraphe. */
.order-payhelp__steps-text {
  font-size: 13.5px;
  line-height: 1.65;
  color: #6b5f58;
  white-space: pre-line;
}

/* ── Annulation ── */
.order-cancel__window {
  font-size: 14px;
  color: #6b5f58;
  margin-bottom: 14px;
}

.order-cancel__reason {
  display: block;
  margin-bottom: 14px;
}

.order-cancel__reason-label {
  display: block;
  font-size: 13px;
  color: #8a7f78;
  margin-bottom: 6px;
}

.order-cancel__btn {
  border: 1px solid #e5b4b4;
  background: #fff;
  color: #b91c1c;
  border-radius: 999px;
  padding: 10px 22px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background .15s ease, border-color .15s ease;
}

.order-cancel__btn:hover:not(:disabled) {
  background: #fee2e2;
  border-color: #b91c1c;
}

.order-cancel__btn:disabled {
  opacity: .6;
  cursor: default;
}

.order-cancel__error {
  margin-bottom: 12px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #fee2e2;
  color: #b91c1c;
  font-size: 13px;
}

.order-cancel__blocked {
  font-size: 14px;
  color: #6b5f58;
  line-height: 1.6;
}

.order-page {
  min-height: 100vh;
  background: var(--color-bg);
  padding: var(--space-8) 0 var(--space-16);
}

.order-content { max-width: 760px; }

.order-back {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.8125rem;
  color: var(--gray-500);
  margin-bottom: var(--space-6);
  transition: color var(--transition-fast);
}
.order-back:hover { color: var(--rose-500); }

.order-loader { display: flex; justify-content: center; padding: var(--space-16) 0; }
.order-loader__spinner {
  width: 32px; height: 32px;
  border: 2px solid var(--rose-100);
  border-top-color: var(--rose-500);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.order-empty {
  text-align: center;
  padding: var(--space-16);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
}
.order-empty__icon { font-size: 4rem; opacity: 0.6; }

.order-blocks {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

/* ── Header ── */
.order-header {
  padding: var(--space-6);
}
.order-header__inner {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
  flex-wrap: wrap;
}
.order-header__title {
  color: var(--gray-800);
  font-family: var(--font-display);
  margin-top: var(--space-1);
}
.order-header__date {
  font-size: 0.8125rem;
  color: var(--gray-400);
  margin-top: var(--space-1);
}
.order-header__actions {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: var(--space-2);
}
.order-header__btns {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.order-pdf-btn {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.75rem;
  color: #fff;
  padding: 4px 12px;
  border-radius: var(--radius-full);
  background: var(--rose-500);
  border: 1.5px solid var(--rose-500);
  transition: all var(--transition-fast);
  font-weight: 500;
}
.order-pdf-btn:hover:not(:disabled) {
  background: var(--rose-600);
  border-color: var(--rose-600);
}
.order-pdf-btn:disabled { opacity: 0.65; cursor: default; }
.order-pdf-btn__spin {
  animation: spin 0.7s linear infinite;
}

.order-eta {
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  margin-top: var(--space-4);
  padding: var(--space-3) var(--space-4);
  background: #f0fdf4;
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  color: #166534;
}
.order-eta svg { flex: 0 0 auto; margin-top: 1px; }
.order-eta strong { font-weight: 600; }
.order-eta__note { margin: 2px 0 0; font-size: 0.75rem; color: #3f6212; }

.order-tracking {
  margin-top: var(--space-4);
  padding: var(--space-3) var(--space-4);
  background: var(--rose-50);
  border-radius: var(--radius-md);
  font-size: 0.875rem;
  color: var(--rose-700);
}

/* ── Bannière Wave ── */
.order-wave-banner {
  margin-top: var(--space-4);
  padding: var(--space-4);
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  border-radius: var(--radius-md);
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
}
.order-wave-banner__icon { flex-shrink: 0; margin-top: 2px; }
.order-wave-banner__body { flex: 1; font-size: 0.875rem; color: #1d4ed8; }
.order-wave-banner__body strong { color: #1e40af; display: block; margin-bottom: 4px; font-size: 0.9375rem; }
.order-wave-banner__body p { margin: 2px 0; }
.order-wave-banner__number-wrapper {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  margin: var(--space-2) 0;
}
.order-wave-banner__number {
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: 0.05em;
  color: #1e40af;
  margin: 0 !important;
}
.order-wave-banner__copy-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 6px;
  background: #fff;
  color: #1e40af;
  border: 1.5px solid #bfdbfe;
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}
.order-wave-banner__copy-btn:hover {
  background: #eff6ff;
  border-color: #93c5fd;
}
.order-wave-banner__hint { font-size: 0.8125rem; color: #3b82f6; margin-top: var(--space-2); }

/* Bouton « Payer avec Wave » */
.order-wave-pay {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  margin: var(--space-3) 0;
  padding: 12px 18px;
  border-radius: var(--radius-full, 999px);
  background: #1dc3f0;
  color: #fff;
  font-size: 0.9375rem;
  font-weight: 700;
  transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
  box-shadow: 0 4px 14px rgba(29, 195, 240, .35);
}
.order-wave-pay:hover { background: #06b6e0; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(29, 195, 240, .45); }

/* Rangée montant + bouton inline (hors Abidjan) */
.order-wave-amount-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.order-wave-pay--inline {
  width: auto;
  margin: 0;
  padding: 8px 16px;
  font-size: 0.8125rem;
  flex-shrink: 0;
}

/* Étape 2 : envoyer la capture */
.order-wave-steps { margin-top: var(--space-3); }
.order-wave-steps__label { font-size: 0.8125rem; font-weight: 600; color: #1e40af; margin-bottom: 6px; }
.order-wave-proof {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 10px 16px;
  border-radius: var(--radius-full, 999px);
  background: #25d366;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  transition: background 0.15s;
}
.order-wave-proof:hover { background: #1ebe5d; }

.order-whatsapp {
  margin-top: var(--space-4);
  padding: var(--space-4);
  background: #dcfce7;
  border-radius: var(--radius-md);
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
}
.order-whatsapp__icon { color: #16a34a; flex-shrink: 0; }
.order-whatsapp__text { flex: 1; font-size: 0.8125rem; }
.order-whatsapp__text strong { color: #15803d; display: block; }
.order-whatsapp__text p { color: #16a34a; margin-top: 2px; }
.order-whatsapp__btn {
  background: #16a34a;
  color: #fff;
  font-size: 0.8125rem;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: var(--radius-full);
  transition: background var(--transition-fast);
  flex-shrink: 0;
}
.order-whatsapp__btn:hover { background: #15803d; }

/* ── Blocks ── */
.order-block { padding: var(--space-6); }
.order-block__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
  margin-bottom: var(--space-4);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--cream-200);
}

/* ── Items ── */
.order-items {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.order-item {
  display: grid;
  grid-template-columns: 64px 1fr auto;
  gap: var(--space-4);
  align-items: center;
}
.order-item__img {
  width: 64px; height: 64px;
  border-radius: var(--radius-md);
  object-fit: cover;
  background: var(--cream-100);
}
.order-item__name {
  font-weight: 500;
  color: var(--gray-800);
  font-size: 0.9375rem;
}
.order-item__variant {
  font-size: 0.75rem;
  color: var(--gray-400);
  margin-top: 2px;
}
.order-item__qty {
  font-size: 0.8125rem;
  color: var(--gray-500);
  margin-top: 4px;
}
.order-item__price {
  font-family: var(--font-sans);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--gray-800);
  white-space: nowrap;
}

/* ── Totals ── */
.order-totals {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.order-totals li {
  display: flex;
  justify-content: space-between;
  font-size: 0.9375rem;
  color: var(--gray-600);
}
.order-totals__discount { color: #15803d; }
.order-shipping--pending { color: #b45309; font-weight: 500; font-size: 0.8125rem; }
.order-totals__final {
  font-family: var(--font-sans);
  font-size: 1.25rem !important;
  font-weight: 600;
  color: var(--gray-800) !important;
  padding-top: var(--space-3);
  border-top: 1px solid var(--cream-200);
}
.order-totals__final span:last-child { color: var(--rose-600); }

/* ── Address ── */
.order-address {
  font-style: normal;
  font-size: 0.9375rem;
  color: var(--gray-600);
  line-height: 1.7;
}
.order-address__name {
  font-weight: 600;
  color: var(--gray-800);
}
.order-address__phone {
  margin-top: var(--space-2);
  font-size: 0.875rem;
}
.order-address__phone span { color: var(--gray-400); }

.order-payment {
  font-size: 0.9375rem;
  color: var(--gray-600);
}

/* ── Bannière Payer maintenant ── */
.order-pay-banner {
  margin-top: var(--space-4);
  padding: var(--space-4);
  background: linear-gradient(135deg, var(--rose-50), #fff7f9);
  border: 1.5px solid var(--rose-200);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  flex-wrap: wrap;
}
.order-pay-banner__body { flex: 1; min-width: 200px; font-size: 0.875rem; }
.order-pay-banner__body strong { color: var(--rose-700); display: block; font-size: 0.9375rem; margin-bottom: 2px; }
.order-pay-banner__body p { color: var(--rose-600); margin: 0; }
.order-pay-banner__btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 28px;
  background: linear-gradient(135deg, var(--rose-500), var(--rose-600));
  color: #fff;
  font-weight: 700;
  font-size: 0.875rem;
  letter-spacing: 0.04em;
  border-radius: 999px;
  border: none;
  cursor: pointer;
  box-shadow: 0 8px 20px -6px rgba(var(--rose-500-rgb), 0.5);
  transition: transform 0.18s ease, box-shadow 0.18s ease;
  white-space: nowrap;
}
.order-pay-banner__btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 12px 26px -8px rgba(var(--rose-500-rgb), 0.6); }
.order-pay-banner__btn:disabled { opacity: 0.7; cursor: default; }
.order-pay-banner__spin {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}

/* ── Bloc traçabilité paiement ── */
.order-pay-detail { display: flex; flex-direction: column; gap: var(--space-3); }
.order-pay-detail__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-4);
  font-size: 0.875rem;
}
.order-pay-detail__label { color: var(--gray-400); }
.order-pay-detail__value { color: var(--gray-700); font-weight: 500; }
.order-pay-detail__ref {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-family: ui-monospace, monospace;
  font-size: 0.8125rem;
  color: var(--gray-700);
}
.order-pay-status {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 999px;
}
.order-pay-status--ok { background: #dcfce7; color: #15803d; }
.order-pay-status--pending { background: var(--gold-100, #fef9c3); color: var(--gold-600, #b45309); }
</style>
