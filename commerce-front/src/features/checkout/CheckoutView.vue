<template>
  <main class="co-page">

    <!-- ── Stepper header ── -->
    <div class="co-header">
      <div class="container co-header__inner">
        <RouterLink to="/" class="co-header__logo">
          <BrandLogo img-class="co-header__logo-img" size="md" />
        </RouterLink>

        <div class="co-steps">
          <template v-for="(s, i) in STEPS" :key="s.key">
            <button
              class="co-step"
              :class="{
                'co-step--active': currentStep === i + 1,
                'co-step--done':   currentStep > i + 1,
              }"
              :disabled="currentStep < i + 1"
              @click="currentStep > i + 1 && (currentStep = i + 1)"
            >
              <span class="co-step__circle">
                <svg v-if="currentStep > i + 1" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                <span v-else>{{ i + 1 }}</span>
              </span>
              <span class="co-step__label">{{ s.label }}</span>
            </button>
            <div v-if="i < STEPS.length - 1" class="co-step__line"
              :class="{ 'co-step__line--done': currentStep > i + 1 }">
            </div>
          </template>
        </div>

        <RouterLink to="/cart" class="co-header__back">
          ← {{ $t('drawer.stepCart') }}
        </RouterLink>
      </div>

      <!-- Barre de progression visuelle (toujours visible, même quand
           les labels sont masqués sur mobile) -->
      <div class="co-progress" aria-hidden="true">
        <div class="co-progress__bar" :style="{ width: `${(currentStep / STEPS.length) * 100}%` }"></div>
      </div>
    </div>

    <!-- ── Mini récap mobile (total + nb articles) ── -->
    <div v-if="cartStore.itemCount && !paymentInstructions" class="co-mini-recap hide-desktop">
      <div class="container co-mini-recap__inner">
        <span class="co-mini-recap__count">{{ cartStore.itemCount }} article{{ cartStore.itemCount > 1 ? 's' : '' }}</span>
        <button class="co-mini-recap__toggle" @click="summaryExpanded = !summaryExpanded">
          {{ formatPrice(orderTotal) }}
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
            :style="{ transform: summaryExpanded ? 'rotate(180deg)' : 'rotate(0)', transition: '0.2s' }">
            <polyline points="6 9 12 15 18 9"/>
          </svg>
        </button>
      </div>
      <!-- Récap détaillé dépliable -->
      <Transition name="expand">
        <div v-if="summaryExpanded" class="co-mini-recap__detail container">
          <ul class="co-mini-items">
            <li v-for="item in cartStore.items" :key="item.id" class="co-mini-item">
              <span class="co-mini-item__name">{{ item.product?.name ?? 'Produit' }}</span>
              <span class="co-mini-item__qty">×{{ item.quantity }}</span>
              <span class="co-mini-item__price">{{ formatPrice(Number(item.unit_price ?? item.price ?? 0) * item.quantity) }}</span>
            </li>
          </ul>
          <div class="co-mini-total">
            <span>{{ $t('common.shipping') }}</span>
            <span :class="shippingPending ? 'co-summary__pending' : ''">{{ shippingLabel }}</span>
          </div>
          <div class="co-mini-total co-mini-total--bold">
            <span>{{ $t('common.total') }}</span>
            <span>{{ formatPrice(orderTotal) }}</span>
          </div>
        </div>
      </Transition>
    </div>

    <!-- ── Contenu ── -->
    <div class="container co-body">
      <div class="co-grid" :class="{ 'co-grid--confirmed': paymentInstructions && !cartStore.itemCount }">

        <!-- ── Colonne formulaire ── -->
        <div class="co-form">

          <!-- ÉTAPE 0 : Gate d'authentification (invités uniquement) -->
          <Transition name="step-slide" mode="out-in">
          <div v-if="!authStore.isLoggedIn" key="gate">
            <CheckoutAuthGate
              @authenticated="onAuthenticated"
              @quick-order="showQuickOrder = true"
              @close="router.push({ name: 'cart' })"
            />
          </div>
          </Transition>

          <!-- CONFIRMATION DE PAIEMENT MANUEL (Wave / Orange Money) -->
          <Transition name="step-slide" mode="out-in">
          <section v-if="authStore.isLoggedIn && paymentInstructions" key="pay-instructions" class="card co-section">
            <header class="co-section__head">
              <span class="co-section__icon" v-html="paymentInstructions.icon"></span>
              <div>
                <h2 class="co-section__title">{{ paymentInstructions.title }}</h2>
                <p class="co-section__hint">{{ $t('checkout.orderNo') }} <strong>{{ confirmedOrderNumber }}</strong></p>
              </div>
            </header>
            <div class="co-section__body">
              <!-- Montant à payer — mis en évidence -->
              <div class="pay-instr__amount-hero">
                <span class="pay-instr__amount-hero-label">{{ $t('checkout.exactAmount') }}</span>
                <strong class="pay-instr__amount-hero-value">{{ formatPrice(confirmedOrderTotal) }}</strong>
              </div>

              <!-- Payer avec Wave (lien marchand + montant) -->
              <a
                v-if="paymentInstructions.payUrl"
                :href="paymentInstructions.payUrl"
                target="_blank"
                rel="noopener"
                class="pay-instr__wave-btn"
              >
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                {{ $t('orders.payAmountWithWave', { amount: formatPrice(confirmedOrderTotal) }) }}
              </a>

              <!-- Numéro de paiement — copiable (masqué si lien Wave dispo) -->
              <button
                v-if="paymentInstructions.number && !paymentInstructions.payUrl"
                type="button"
                class="pay-instr__copy-card"
                @click="copyValue(paymentInstructions.number, 'number')"
              >
                <div class="pay-instr__copy-info">
                  <span class="pay-instr__copy-label">{{ $t('checkout.payNumberLabel') }}</span>
                  <span class="pay-instr__copy-value">{{ paymentInstructions.number }}</span>
                </div>
                <span class="pay-instr__copy-action">
                  <svg v-if="copied === 'number'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  {{ copied === 'number' ? $t('common.copied') : $t('common.copy') }}
                </span>
              </button>

              <!-- Référence commande — copiable -->
              <button
                type="button"
                class="pay-instr__copy-card pay-instr__copy-card--sm"
                @click="copyValue(confirmedOrderNumber, 'ref')"
              >
                <div class="pay-instr__copy-info">
                  <span class="pay-instr__copy-label">{{ $t('checkout.orderReference') }}</span>
                  <span class="pay-instr__copy-value pay-instr__copy-value--sm">{{ confirmedOrderNumber }}</span>
                </div>
                <span class="pay-instr__copy-action">
                  <svg v-if="copied === 'ref'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                  <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                  {{ copied === 'ref' ? $t('common.copied') : $t('common.copy') }}
                </span>
              </button>

              <!-- Instructions étape par étape -->
              <div class="pay-instr__steps">
                <p class="pay-instr__steps-title">{{ $t('checkout.howToProceed') }}</p>
                <p class="pay-instr__steps-text">{{ paymentInstructions.instructions }}</p>
              </div>

              {{ adminWhatsappLink ?? '-------------------' }}
              <!-- Bouton WhatsApp admin -->
              <a v-if="adminWhatsappLink" :href="adminWhatsappLink" target="_blank" rel="noopener"
                class="btn btn-whatsapp pay-instr__wa">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                {{ $t("checkout.confirmWhatsapp") }}
              </a>
            </div>
            <div class="co-section__foot pay-instr__foot">
              <button @click="goToProducts" class="btn btn-outline">
                ← {{ $t('checkout.backToProducts') }}
              </button>
              <button @click="goToOrder" class="btn btn-primary">
                {{ $t("checkout.viewMyOrder") }}
              </button>
            </div>
          </section>
          </Transition>

          <!-- PANIER VIDE (avant commande) -->
          <Transition name="step-slide" mode="out-in">
          <section v-if="authStore.isLoggedIn && !paymentInstructions && !cartStore.itemCount" key="empty-cart" class="card co-section co-empty">
            <div class="co-empty__body">
              <div class="co-empty__icon">
                <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
              </div>
              <h2 class="co-section__title">{{ $t('checkout.emptyCart') }}</h2>
              <p class="co-empty__hint">{{ $t('checkout.emptyCartHint') }}</p>
              <RouterLink :to="{ name: 'products' }" class="btn btn-primary co-empty__cta">
                {{ $t("checkout.discoverProducts") }}
              </RouterLink>
            </div>
          </section>
          </Transition>

          <!-- ÉTAPE 1 : Infos personnelles -->
          <Transition name="step-slide" mode="out-in">
          <section v-if="authStore.isLoggedIn && !paymentInstructions && cartStore.itemCount && currentStep === 1" class="card co-section" key="step1">
            <header class="co-section__head">
              <span class="co-section__icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <div>
                <h2 class="co-section__title">{{ $t('checkout.personalInfo') }}</h2>
                <p class="co-section__hint">{{ $t('checkout.howToCallYou') }}</p>
              </div>
            </header>
            <div class="co-section__body">
              <div class="co-grid-2">
                <CheckoutField :def="FIELDS.first_name" :error="fe('first_name')">
                  <input v-model="form.first_name" type="text" class="input" required :placeholder="$t('checkout.firstNamePlaceholder')" />
                </CheckoutField>
                <CheckoutField :def="FIELDS.last_name" :error="fe('last_name')" optional>
                  <input v-model="form.last_name" type="text" class="input" :placeholder="$t('checkout.lastNamePlaceholder')" />
                </CheckoutField>
              </div>
              <CheckoutField :def="FIELDS.phone" :error="fe('phone')">
                <PhoneInput
                  v-model="form.phone"
                  placeholder="07 00 00 00"
                  :required="true"
                  @validity="phoneValidity = $event"
                />
              </CheckoutField>
              <CheckoutField :def="FIELDS.email" :error="fe('email')">
                <input v-model="form.email" type="email" class="input" :placeholder="$t('checkout.emailPlaceholder')" />
              </CheckoutField>
            </div>
            <div class="co-section__foot">
              <button
                @click="goStep(2)"
                :disabled="!step1Valid"
                class="btn btn-primary co-next-btn"
              >
                {{ $t("checkout.continue") }}
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
            </div>
          </section>
          </Transition>

          <!-- ÉTAPE 2 : Adresse de livraison -->
          <Transition name="step-slide" mode="out-in">
          <section v-if="authStore.isLoggedIn && !paymentInstructions && cartStore.itemCount && currentStep === 2" class="card co-section" key="step2">
            <header class="co-section__head">
              <span class="co-section__icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
              </span>
              <div>
                <h2 class="co-section__title">{{ $t('checkout.shippingAddress') }}</h2>
                <p class="co-section__hint">{{ $t("checkout.whereDeliver") }}</p>
              </div>
            </header>
            <div class="co-section__body">

              <!-- Pays — tabindex=-1 sur le wrapper pour éviter le focus auto -->
              <CheckoutField :def="FIELDS.shipping_country" :error="fe('shipping_country')">
                <div tabindex="-1" @focus.capture.prevent>
                  <AppSelect
                    v-model="form.shipping_country"
                    :options="shippingCountryOptions"
                    :placeholder="$t('checkout.selectCountry')"
                    @update:modelValue="onShippingCountryChange"
                  />
                </div>
              </CheckoutField>

              <!-- Ville selon pays -->
              <template v-if="form.shipping_country === 'CI'">
                <CitySelect
                  v-model:city="form.shipping_city"
                  v-model:commune="form.shipping_commune"
                />
              </template>
              <template v-else-if="form.shipping_country">
                <CityFree
                  v-model:city="form.shipping_city"
                  v-model:region="form.shipping_region"
                  :country="form.shipping_country"
                />
              </template>

              <!--
                Bannière internationale, dès le choix du pays — la commande
                rapide l'annonce au même moment. L'apprendre à l'étape du
                paiement, c'est le découvrir après avoir tout saisi.
              -->
              <Transition name="fade">
                <div v-if="isInternational" class="co-outzone">
                  <span class="co-outzone__icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/></svg>
                  </span>
                  <div class="co-outzone__body">
                    <strong>{{ $t('quickOrder.intlTitle') }}</strong>
                    <p>{{ $t('checkout.intlLead') }}</p>
                  </div>
                </div>
              </Transition>

              <!-- Bannière bien visible dès qu'on est hors Abidjan -->
              <Transition name="fade">
                <div
                  v-if="form.shipping_country === 'CI' && form.shipping_city && !isAbidjanDelivery"
                  class="co-outzone"
                >
                  <span class="co-outzone__icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                  </span>
                  <div class="co-outzone__body">
                    <strong>{{ $t('checkout.outsideAbidjanTitle', { zone: settings.homeCity }) }}</strong>
                    <p>
                      {{ $t('checkout.outsideAbidjanP1') }} <b>{{ $t('checkout.outsideAbidjanB1') }}</b>
                      {{ $t('checkout.outsideAbidjanP2', { zone: settings.homeCity }) }} <b>{{ $t('checkout.outsideAbidjanB2') }}</b>{{ $t('checkout.outsideAbidjanP3', { zone: settings.homeCity }) }}
                    </p>
                  </div>
                </div>
              </Transition>

              <!-- Adresse -->
              <CheckoutField :def="FIELDS.shipping_address_line1" :error="fe('shipping_address_line1')" optional>
                <input v-model="form.shipping_address_line1" type="text" class="input" :placeholder="$t('checkout.addressPlaceholder')" />
              </CheckoutField>

              <!--
                Point de repère — et c'est ici que vit « Ma position ».
                Le bouton était au-dessus du sélecteur de ville, loin du champ
                que la détection remplit vraiment. Contre ce label, on voit
                immédiatement ce qu'il fait.
              -->
              <CheckoutField :def="FIELDS.landmark" optional>
                <textarea
                  v-model="form.landmark"
                  class="input co-landmark"
                  rows="2"
                  :placeholder="$t('checkout.landmarkPlaceholder')"
                />
              </CheckoutField>

              <!-- Numéro du receveur -->
              <div class="co-receiver">
                <label class="co-receiver__toggle">
                  <input type="checkbox" v-model="form.receiver_different" class="co-receiver__cb" />
                  <span>{{ $t('checkout.receiverDifferent') }}</span>
                </label>
                <Transition name="fade">
                  <CheckoutField
                    v-if="form.receiver_different"
                    :def="FIELDS.receiver_phone"
                    :error="fe('receiver_phone')"
                    class="co-receiver__field"
                  >
                    <PhoneInput
                      v-model="form.receiver_phone"
                      placeholder="07 00 00 00"
                    />
                  </CheckoutField>
                </Transition>
              </div>

              <!-- Hors zone : frais communiqués manuellement -->
              <div v-if="shippingManual" class="co-shipping-notice">
                <span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
                <div>
                  <strong>{{ $t("checkout.shippingNoticeTitle") }}</strong>
                  <p>{{ $t("checkout.shippingNoticeText") }}</p>
                </div>
              </div>
            </div>
            <div class="co-section__foot">
              <button @click="currentStep = 1" class="btn btn-outline co-back-btn">
                ← {{ $t("common.back") }}
              </button>
              <button
                @click="goStep(3)"
                :disabled="!step2Valid"
                class="btn btn-primary co-next-btn"
              >
                {{ $t("checkout.continue") }}
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
              </button>
            </div>
          </section>
          </Transition>

          <!-- ÉTAPE 3 : Paiement -->
          <Transition name="step-slide" mode="out-in">
          <section v-if="authStore.isLoggedIn && !paymentInstructions && cartStore.itemCount && currentStep === 3" class="card co-section" key="step3">
            <header class="co-section__head">
              <span class="co-section__icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>
              </span>
              <!--
                Rien à choisir ici quand un agent fixe le règlement : titrer
                « Choisissez votre mode de règlement » au-dessus d'un encart qui
                dit l'inverse ferait chercher un choix qui n'existe pas.
              -->
              <div>
                <h2 class="co-section__title">
                  {{ nothingToPay ? $t('checkout.confirmStepTitle') : $t('checkout.paymentMethod') }}
                </h2>
                <p class="co-section__hint">
                  {{ nothingToPay ? $t('checkout.confirmStepHint') : $t('checkout.chooseMethod') }}
                </p>
              </div>
            </header>
            <div class="co-section__body">
              <!--
                INTERNATIONAL : aucun règlement en ligne. La commande part
                quand même — c'est le déroulé de la commande rapide, et
                l'international n'avait sinon aucun moyen d'aboutir depuis le
                panier. Le passage à WhatsApp se fait après validation, depuis
                la page de suivi, avec le numéro de commande en main.
              -->
              <div v-if="isInternational" class="co-intl">
                <p class="co-intl__lead">
                  {{ $t('checkout.intlLead') }}
                </p>
                <p class="co-intl__note">
                  {{ $t('checkout.intlNote') }}
                </p>
              </div>

              <!-- HORS ZONE : frais inconnus → nos agents fixent aussi le règlement -->
              <div v-else-if="shippingManual" class="co-intl">
                <p class="co-intl__lead">
                  {{ $t('checkout.manualZoneLead') }}
                </p>
                <p class="co-intl__note">
                  {{ $t('checkout.manualZoneNote') }}
                </p>
              </div>

              <template v-else>
                <div class="payment-methods">
                  <label
                    v-for="method in paymentMethods"
                    :key="method.value"
                    class="payment-method"
                    :class="{ 'payment-method--active': form.payment_method === method.value }"
                  >
                    <input type="radio" :value="method.value" v-model="form.payment_method" required />
                    <span class="payment-method__icon" v-html="method.icon"></span>
                    <div class="payment-method__body">
                      <strong>{{ method.label }}</strong>
                      <span v-if="method.desc">{{ method.desc }}</span>
                    </div>
                    <span class="payment-method__dot"></span>
                  </label>
                </div>

                <p v-if="!paymentMethods.length" class="co-msg co-msg--error">
                  {{ $t('checkout.noPaymentMethod') }}
                </p>

                <p v-if="destination === 'abidjan'" class="co-shipping-note">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  {{ $t('checkout.abidjanNote', { zone: settings.homeCity }) }}
                </p>
                <p v-else-if="form.shipping_city" class="co-shipping-note">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                  {{ $t('checkout.insideCountryNote') }}
                </p>
              </template>

              <p v-if="submitError" class="co-msg co-msg--error">{{ submitError }}</p>
            </div>
            <div class="co-section__foot">
              <button @click="currentStep = 2" class="btn btn-outline co-back-btn">
                ← {{ $t("common.back") }}
              </button>
            </div>
          </section>
          </Transition>

        </div>

        <!-- ── Sidebar récapitulatif ── -->
        <aside class="co-summary card" v-if="cartStore.itemCount">
          <h3 class="co-summary__title">{{ $t('checkout.summary') }}</h3>

          <!-- Articles -->
          <ul class="co-summary__items">
            <li v-for="item in cartStore.items" :key="item.id" class="co-summary__item">
              <div class="co-summary__item-img">
                <img v-if="item.product?.images?.[0]?.url" :src="item.product.images[0].url" :alt="item.product?.name" @error="e => e.target.style.display='none'" />
                <span v-else><FlowerMark /></span>
              </div>
              <div class="co-summary__item-info">
                <p>{{ item.product?.name ?? item.name ?? 'Produit' }}</p>
                <span>× {{ item.quantity }}</span>
              </div>
              <p class="co-summary__item-price">{{ formatPrice(Number(item.unit_price ?? item.price ?? 0) * item.quantity) }}</p>
            </li>
          </ul>

          <!-- Totaux -->
          <ul class="co-summary__lines">
            <li>
              <span>{{ $t("common.subtotal") }}</span>
              <span>{{ formatPrice(Number(cartStore.subtotal)) }}</span>
            </li>
            <li v-if="couponApplied" class="co-summary__discount">
              <span>{{ $t('common.discount') }}{{ couponLabelSuffix }}</span>
              <span>-{{ formatPrice(discountAmount) }}</span>
            </li>
            <li>
              <span>{{ $t('common.shipping') }}</span>
              <span :class="shippingPending ? 'co-summary__pending' : ''">
                {{ shippingLabel }}
              </span>
            </li>
            <li class="co-summary__total">
              <span>{{ $t('common.total') }}</span>
              <span>{{ formatPrice(orderTotal) }}</span>
            </li>
          </ul>

          <!-- Code promo — dans la sidebar, même bloc que le récap -->
          <div class="co-promo">
            <div class="co-promo__input-wrap">
              <input
                v-model="couponCode"
                type="text"
                class="input co-promo__input"
                :placeholder="$t('checkout.promoCode')"
                :disabled="couponApplied"
              />
              <button
                type="button"
                @click="applyCoupon"
                :disabled="couponLoading || couponApplied"
                class="btn btn-outline btn-sm"
              >
                <template v-if="couponApplied">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><polyline points="20 6 9 17 4 12"/></svg>
                </template>
                <template v-else>{{ $t('common.apply') }}</template>
              </button>
            </div>
            <p v-if="couponError" class="co-msg co-msg--error">{{ couponError }}</p>
            <p v-if="couponApplied" class="co-msg co-msg--success">{{ $t('drawer.discountApplied') }}</p>
            <!--
              Code ciblé : la cliente doit savoir pourquoi la remise ne porte
              pas sur tout son panier, sinon l'écart avec le taux annoncé passe
              pour une erreur.
            -->
            <p v-if="couponTargets.length" class="co-msg co-coupon-scope">
              {{ $t('checkout.couponOnlyOn', { products: couponTargets.join(', ') }) }}
            </p>
          </div>

          <!-- Bouton confirmer — visible uniquement à l'étape 3 -->
          <Transition name="fade">
            <!-- International : la commande ne se confirme pas ici (voir étape 3) -->
            <button
              v-if="currentStep === 3"
              type="button"
              @click="submitOrder"
              :disabled="submitting || !canSubmit"
              class="btn btn-primary btn-lg co-summary__cta"
            >
              <span v-if="submitting" class="co-spinner"></span>
              <span v-else>{{ $t('drawer.confirmWithTotal', { total: formatPrice(orderTotal) }) }}</span>
            </button>
          </Transition>

          <p class="co-summary__legal">
            {{ $t("checkout.legal") }}
          </p>
        </aside>

      </div>
    </div>

    <!-- ── CTA fixé en bas sur mobile (step 3) ── -->
    <Teleport to="body">
      <Transition name="slide-up">
        <div v-if="currentStep === 3 && cartStore.itemCount && !paymentInstructions" class="co-mobile-cta hide-desktop">
          <div class="co-mobile-cta__inner">
            <div class="co-mobile-cta__total">
              <span>{{ $t('common.total') }}</span>
              <strong>{{ formatPrice(orderTotal) }}</strong>
            </div>
            <button
              @click="submitOrder"
              :disabled="submitting || !canSubmit"
              class="btn btn-primary co-mobile-cta__btn"
            >
              <span v-if="submitting" class="co-spinner"></span>
              <span v-else>{{ $t('checkout.confirmOrder') }}</span>
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Quick Order Modal (invité sans compte) -->
    <QuickOrderModal
      v-if="showQuickOrder"
      @close="showQuickOrder = false"
    />

    <!-- Commande semblable déjà en cours : on demande avant de doubler -->
    <DuplicateOrderDialog
      v-if="duplicate"
      :order="duplicate"
      :busy="submitting"
      @confirm="confirmDuplicate"
      @cancel="cancelDuplicate"
    />
  </main>
</template>

<script setup>
import BrandLogo from '@/components/ui/BrandLogo.vue'
import { ref, computed, watch, nextTick, onMounted } from 'vue'
import { useCurrencyStore } from '@/stores/currency'
import { useRouter, RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import DuplicateOrderDialog from '@/features/checkout/DuplicateOrderDialog.vue'
import { useCartStore }     from '@/features/cart/cart.store'
import { useAuthStore }     from '@/features/auth/auth.store'
import { useSettingsStore } from '@/stores/settings'
import CheckoutAuthGate     from './CheckoutAuthGate.vue'
import QuickOrderModal      from './QuickOrderModal.vue'
import AppSelect            from '@/components/ui/AppSelect.vue'
import PhoneInput           from '@/components/ui/PhoneInput.vue'
import CitySelect           from '@/components/shop/CitySelect.vue'
import CityFree             from '@/components/shop/CityFree.vue'
import { usePaymentInstructions } from '@/composables/usePaymentInstructions'
import { isAbidjan }        from '@/data/cities-ci'
import api                              from '@/api'
import { checkoutApi }                  from './checkout.api'
import { makeForm, mapErrors, FIELDS }  from './checkout.fields'
import CheckoutField                    from '@/shared/components/ui/FormField.vue'
import FlowerMark                       from '@/components/ui/FlowerMark.vue'

const { t }        = useI18n()
const router       = useRouter()

const cartStore    = useCartStore()
const authStore    = useAuthStore()
const settings     = useSettingsStore()

// ── Gate d'auth + QuickOrder ─────────────────────────────────────────────────
const showQuickOrder = ref(false)

/**
 * Pré-remplissage du formulaire à la connexion.
 * On utilise un watcher plutôt que l'emit @authenticated :
 * auth.login() fait un await interne (mergeLocalCart) qui laisse Vue
 * démonter CheckoutAuthGate avant que emit() soit appelé — l'event
 * n'est donc jamais reçu. Le watcher sur isLoggedIn est fiable.
 */
watch(
  () => authStore.isLoggedIn,
  (loggedIn) => {
    if (!loggedIn) return
    const u = authStore.user
    if (u) {
      const parts = (u.name ?? '').trim().split(/\s+/)
      form.value.first_name = parts[0] ?? ''
      form.value.last_name  = parts.slice(1).join(' ')
      form.value.email      = u.email ?? ''
      form.value.phone      = u.phone ?? ''
    }
    currentStep.value = 1
    cartStore.fetch().catch(() => {})
  }
)

// Gardé pour rétrocompatibilité (Facebook OAuth, etc.)
function onAuthenticated() {}

// ── Géolocalisation : pré-remplir la rue depuis CitySelect ───────────────────

// ── Instructions de paiement manuel (post-commande) ──────────────────────────
const { build: buildInstructions } = usePaymentInstructions()

const paymentInstructions = ref(null)   // { title, icon, number, instructions }
const confirmedOrderNumber = ref(null)
const confirmedOrderTotal  = ref(0)
const copied               = ref(null)  // 'number' | 'ref' | null

async function copyValue(text, key) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(String(text))
  } catch {
    const ta = document.createElement('textarea')
    ta.value = String(text)
    document.body.appendChild(ta)
    ta.select()
    try { document.execCommand('copy') } catch { /* noop */ }
    document.body.removeChild(ta)
  }
  copied.value = key
  setTimeout(() => { if (copied.value === key) copied.value = null }, 1800)
}

function buildPaymentInstructions(method, orderNumber, orderTotal) {
  confirmedOrderNumber.value = orderNumber
  confirmedOrderTotal.value  = orderTotal

  // Consignes construites par le composable partagé : la page de suivi de
  // commande affiche exactement les mêmes, montant et référence compris.
  paymentInstructions.value = buildInstructions(settings.data, method, orderNumber, orderTotal)
}

const adminWhatsappLink = computed(() => {
  const s = (settings.data?.value ?? settings.data ?? {})
  const phone = (s.whatsapp_admin_number ?? '').toString().trim()
  if (!phone || !confirmedOrderNumber.value) return null
  const msg = encodeURIComponent(t('checkout.waPaidMessage', {
    number: confirmedOrderNumber.value,
    amount: formatPrice(confirmedOrderTotal.value),
  }))
  return `https://wa.me/${phone.replace(/\D/g, '')}?text=${msg}`
})

/*
 * Le message WhatsApp pré-commande de l'international a été retiré : il partait
 * avec le panier mais sans numéro de commande, et invitait à quitter le tunnel
 * avant de valider. La commande se crée maintenant ici, et c'est la page de
 * suivi qui passe la main à l'agent — avec la référence en main.
 */

function goToOrder() {
  paymentInstructions.value = null
  router.push({ name: 'order', params: { number: confirmedOrderNumber.value } })
}

function goToProducts() {
  paymentInstructions.value = null
  router.push({ name: 'products' })
}

// ── Étapes ──────────────────────────────────────────────────────────────────
const STEPS = [
  { key: 'infos',    label: 'Infos' },
  { key: 'adresse',  label: 'Adresse' },
  { key: 'paiement', label: 'Paiement' },
]
const currentStep    = ref(1)
const summaryExpanded = ref(false)

function goStep(n) {
  currentStep.value = n
}

// Au changement d'étape : retire le focus auto + scroll en haut de la nouvelle
// section pour que l'utilisateur voie le titre, pas un champ au milieu.
watch(currentStep, async () => {
  await nextTick()
  document.activeElement?.blur()
  // Cible la card de la section affichée. Petit délai pour laisser la
  // transition Vue terminer le mount avant de scroller.
  requestAnimationFrame(() => {
    const section = document.querySelector('.co-section')
    if (!section) return
    const rect = section.getBoundingClientRect()
    // Si la section est déjà visible en haut, ne pas re-scroller pour rien
    if (rect.top < 0 || rect.top > window.innerHeight * 0.4) {
      const targetY = window.scrollY + rect.top - 80 // marge sous le header sticky
      window.scrollTo({ top: targetY, behavior: 'smooth' })
    }
  })
})

// ── Formulaire ───────────────────────────────────────────────────────────────
const form           = ref(makeForm())
const fieldErrors    = ref({})
const fe             = (key) => fieldErrors.value[key] ?? ''
const submitting     = ref(false)
const submitError    = ref('')
const couponFromCart = ref(false)

// ── Validations par étape ────────────────────────────────────────────────────
const step1Valid = computed(() =>
  form.value.first_name?.trim() && form.value.phone?.trim()
)
const step2Valid = computed(() =>
  form.value.shipping_country && form.value.shipping_city?.trim()
)

// ── Options pays ─────────────────────────────────────────────────────────────
const shippingCountryKeys = ['CI','SN','ML','BF','GN','TG','BJ','GH','NG','FR','BE','CH','DE','GB','CA','US','OTHER']
const shippingCountryOptions = computed(() =>
  shippingCountryKeys.map(code => ({ value: code, label: t(`checkout.countries.${code}`) }))
)

function onShippingCountryChange() {
  form.value.shipping_city    = ''
  form.value.shipping_commune = ''
  form.value.shipping_region  = ''
}

// ── Moyens de paiement ────────────────────────────────────────────────────────
const ICON_WAVE   = '<span class="pm-badge pm-badge--wave">W</span>'
const ICON_ORANGE = '<span class="pm-badge pm-badge--orange">OM</span>'
const ICON_MTN    = '<span class="pm-badge pm-badge--mtn">M</span>'
const ICON_CARD   = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><path d="M1 10h22"/></svg>'
const ICON_TRUCK  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>'

// Livraison à domicile payable à réception : uniquement dans Abidjan.
// Hors Abidjan, on n'expédie que ce qui est déjà payé → prépaiement obligatoire.
const isAbidjanDelivery = computed(() =>
  isAbidjan(form.value.shipping_city, form.value.shipping_commune)
)

/**
 * Destination de la commande — miroir de PaymentAvailability côté serveur.
 * C'est le serveur qui tranche (StoreOrderRequest) ; ceci n'est que l'affichage.
 */
const destination = computed(() => {
  const pays = (form.value.shipping_country || 'CI').toUpperCase()
  if (pays !== 'CI') return 'international'
  return isAbidjanDelivery.value ? 'abidjan' : 'interior'
})

const isInternational = computed(() => destination.value === 'international')

const paymentMethods = computed(() => {
  // Accès robuste aux settings bruts, que Pinia déballe le ref ou non.
  const s = (settings.data?.value ?? settings.data ?? {})
  const isOff = (v) => v === false || v === 'false' || v === '0' || v === 0

  // International : rien à choisir. Les frais dépendent du transporteur, du
  // poids et parfois de la douane — un agent les arrête, puis fixe le
  // règlement. Proposer Wave ou Orange Money ici, comme c'était le cas, faisait
  // choisir un moyen que le serveur refuse ensuite : commande impossible.
  if (isInternational.value) return []

  // Grand Abidjan : paiement à la livraison uniquement — nos livreurs encaissent.
  if (destination.value === 'abidjan') {
    if (isOff(s.payment_delivery_enabled)) return []
    return [{ value: 'cod', label: t('checkout.onDelivery'), icon: ICON_TRUCK, desc: t('drawer.codDesc') }]
  }

  // Intérieur de la CI : prépaiement mobile obligatoire.
  const list = []
  if (!isOff(s.payment_wave_enabled))         list.push({ value: 'wave',         label: t('drawer.wave'),        icon: ICON_WAVE,   desc: t('drawer.waveDesc') })
  if (!isOff(s.payment_orange_money_enabled)) list.push({ value: 'orange_money', label: t('drawer.orangeMoney'), icon: ICON_ORANGE, desc: t('drawer.orangeDesc') })
  return list
})

// La destination change → un moyen devenu invalide ne doit pas rester coché.
watch(paymentMethods, (methods) => {
  const choisi = form.value.payment_method
  if (choisi && !methods.some(m => m.value === choisi)) {
    form.value.payment_method = ''
  }
}, { immediate: true })

/**
 * Aucun règlement à choisir dans ce tunnel.
 *
 * Deux cas, une seule conduite — celle de la commande rapide : l'international,
 * dont les frais dépendent du transporteur, du poids et parfois de la douane, et
 * la zone pas encore tarifée. Dans les deux, la commande part sans moyen de
 * paiement et un agent fixe frais puis règlement.
 *
 * Miroir de PaymentAvailability::allowed() côté serveur, qui reste l'arbitre.
 */
const nothingToPay = computed(() => isInternational.value || shippingManual.value)

/**
 * Commande envoyable ?
 *
 * Là où il n'y a rien à choisir, rien n'est attendu de la cliente. Ailleurs, le
 * moyen de paiement est obligatoire.
 */
const canSubmit = computed(() => {
  if (!cartStore.items.length) return false
  if (nothingToPay.value) return true
  return !!form.value.payment_method
})

// ── Coupon ────────────────────────────────────────────────────────────────────
const couponCode     = ref('')
const couponApplied  = ref(false)
const couponDiscount = ref(0)
const couponError    = ref('')
const couponLoading  = ref(false)

// Commande semblable déjà en cours, et la demande qui l'a déclenchée.
const duplicate        = ref(null)
const duplicatePayload = ref(null)

/*
 * Validité du téléphone, telle que le champ la rapporte. Bloquante seulement
 * là où la règle est certaine — la Côte d'Ivoire. Ailleurs, la cliente est
 * avertie sous le champ et la commande passe : nos règles y sont des
 * approximations, et le serveur applique exactement la même distinction.
 */
const phoneValidity = ref({ valid: true, strict: true })
const phoneBlocked  = computed(() => !phoneValidity.value.valid && phoneValidity.value.strict)
// Montant chiffré par le serveur, et articles visés quand le code est ciblé.
const couponAmount   = ref(0)
const couponProducts = ref([])

/**
 * Montant de la réduction.
 *
 * Toujours une valeur venue du serveur : celle du panier quand le code y était
 * déjà posé, celle de la validation sinon. Le calcul local qui la remplaçait
 * ignorait aussi bien le ciblage que l'exclusion des articles en promotion, et
 * annonçait donc une remise que la commande ne tenait pas.
 */
const discountAmount = computed(() => {
  if (!couponApplied.value) return 0
  if (couponFromCart.value) return Number(cartStore.cart.discount ?? 0)
  return couponAmount.value
})

/** Le code ne vaut-il que pour certains articles ? */
const couponTargets = computed(() => {
  if (!couponApplied.value) return []
  if (couponFromCart.value) return cartStore.coupon?.products ?? []
  return couponProducts.value.map(p => p.name)
})

const couponLabelSuffix = computed(() => {
  if (!couponApplied.value) return ''
  if (couponFromCart.value) {
    const c = cartStore.coupon
    return c?.type === 'percentage' ? ` (${c.value}%)` : ''
  }
  return couponDiscount.value ? ` (${couponDiscount.value}%)` : ''
})

// ── Frais de livraison : quote API — le prix vient directement de la zone. ───
// Pas de livraison gratuite dans le système. Zone non identifiée ou tarif au
// kilo (poids inconnu) → « À renseigner par nos agents ».
const shippingQuote  = ref(null)
const shippingFound  = computed(() => shippingQuote.value && !shippingQuote.value.not_found)
const shippingManual = computed(() => shippingQuote.value?.not_found === true)
const shippingPerKg  = computed(() => shippingFound.value && shippingQuote.value.unit === 'per_kg')

const shippingCost = computed(() => {
  if (shippingFound.value && !shippingPerKg.value) return Number(shippingQuote.value.price) || 0
  return 0 // zone inconnue / per kg : aucun montant injecté, agents renseignent
})

const shippingLabel = computed(() => {
  // Même phrase que la commande rapide : les deux tunnels annoncent la même
  // chose pour la même destination.
  if (isInternational.value) return t('quickOrder.shippingWithAgent')
  if (shippingFound.value && !shippingPerKg.value) return formatPrice(shippingQuote.value.price)
  if (shippingManual.value || shippingPerKg.value) return t('drawer.shippingByAgents')
  return t('checkout.shippingToFill')
})

// Livraison « à renseigner » : international, hors zone, ou tarif au kilo sans
// poids connu. Dans les trois cas le total affiché n'est pas ferme.
const shippingPending = computed(() =>
  isInternational.value || shippingPerKg.value || shippingManual.value
)

let quoteTimer = null
async function refreshShippingQuote() {
  const city    = form.value.shipping_city
  const commune = form.value.shipping_commune
  const country = form.value.shipping_country || 'CI'
  // L'international ne se tarife pas à la zone — transporteur, poids, douane se
  // chiffrent au cas par cas. La commande rapide n'interroge pas le serveur non
  // plus : afficher un tarif de zone ici promettrait un montant qu'aucun agent
  // ne tiendra.
  if (isInternational.value) { shippingQuote.value = null; return }
  if (country === 'CI' && !city && !commune) { shippingQuote.value = null; return }
  try {
    const { data } = await api.get('/shipping/quote', {
      params: { city, commune, country, subtotal: cartStore.subtotal },
    })
    shippingQuote.value = data?.found ? data : { not_found: true }
  } catch (e) {
    shippingQuote.value = e.response?.status === 404 ? { not_found: true } : null
  }
}

watch(
  () => [
    form.value.shipping_city,
    form.value.shipping_commune,
    form.value.shipping_country,
    cartStore.subtotal,
  ],
  () => {
    clearTimeout(quoteTimer)
    quoteTimer = setTimeout(refreshShippingQuote, 250)
  }
)

const orderTotal = computed(() => {
  const base = couponFromCart.value
    ? Number(cartStore.total ?? 0)
    : Number(cartStore.subtotal ?? 0) - discountAmount.value
  return base + shippingCost.value
})

// ── Initialisation ────────────────────────────────────────────────────────────
onMounted(() => {
  // Rafraîchir le panier en arrière-plan (non-bloquant, comme ShopLayout)
  cartStore.fetch().catch(() => {})

  const u = authStore.user
  if (u) {
    const parts = (u.name ?? '').trim().split(/\s+/)
    form.value.first_name = parts[0] ?? ''
    form.value.last_name  = parts.slice(1).join(' ')
    form.value.email      = u.email ?? ''
    form.value.phone      = u.phone ?? ''
  }
  const c = cartStore.coupon
  if (c) {
    couponCode.value     = c.code
    couponApplied.value  = true
    couponFromCart.value = true
    couponDiscount.value = c.type === 'percentage' ? c.value : 0
  }
})

// ── Actions ────────────────────────────────────────────────────────────────────
/**
 * Valide le code et récupère le montant réellement accordé.
 *
 * La remise était devinée ici (`sous-total × valeur / 100`), donc fausse dès
 * qu'un article était déjà en promotion — et impossible à deviner du tout pour
 * un coupon ciblé, qui ne mord que sur certains articles. C'est le serveur qui
 * la chiffre maintenant, sur les lignes du panier.
 */
async function applyCoupon() {
  if (!couponCode.value.trim()) return
  couponLoading.value = true
  couponError.value   = ''
  try {
    const { data } = await checkoutApi.validateCoupon(
      couponCode.value,
      cartStore.items.map(i => ({
        product_id: i.product_id,
        variant_id: i.variant_id ?? null,
        quantity:   i.quantity,
      })),
    )
    couponApplied.value  = true
    couponFromCart.value = false
    couponDiscount.value = data.discount ?? 0
    couponAmount.value   = Number(data.discount_amount ?? 0)
    couponProducts.value = data.products ?? []
  } catch (e) {
    couponError.value = e.response?.data?.message ?? t('checkout.couponInvalid')
  } finally {
    couponLoading.value = false
  }
}

function buildPayload() {
  const shipping_address = {
    first_name:     form.value.first_name,
    last_name:      form.value.last_name,
    phone:          form.value.phone,
    email:          form.value.email,
    address_line1:  form.value.shipping_address_line1 || form.value.shipping_city,
    city:           form.value.shipping_city,
    commune:        form.value.shipping_commune  || undefined,
    region:         form.value.shipping_region   || undefined,
    country:        form.value.shipping_country,
    landmark:       form.value.landmark?.trim()  || undefined,
    receiver_phone: form.value.receiver_different && form.value.receiver_phone?.trim()
                      ? form.value.receiver_phone.trim()
                      : undefined,
  }
  return {
    shipping_address,
    billing_address:  { ...shipping_address },
    // Hors zone : aucun moyen retenu, nos agents fixent frais puis règlement.
    payment_method:   form.value.payment_method || null,
    coupon_code:      couponApplied.value ? couponCode.value : null,
    customer_note:    form.value.customer_note || null,
    shipping_cost:    shippingFound.value ? shippingCost.value : null,
    shipping_unknown: shippingManual.value || false,
    items: cartStore.items.map(item => ({
      product_id: item.product_id,
      variant_id: item.variant_id ?? null,
      quantity:   item.quantity,
      price:      item.price,
    })),
  }
}

async function submitOrder() {
  if (phoneBlocked.value) {
    submitError.value = t('phone.invalidCI')
    currentStep.value = 1
    return
  }

  await placeOrder(buildPayload())
}

/**
 * Places the same order again, this time saying it is a new one.
 *
 * The server holds nothing between the two calls: the answer travels with the
 * payload, so a reload cannot turn into a silent double.
 */
/** La cliente renonce : le tunnel garde le message sous les yeux. */
function cancelDuplicate() {
  const numero = duplicate.value?.number
  duplicate.value = null
  submitError.value = numero
    ? t('duplicate.pending', { number: numero })
    : t('duplicate.title')
}

async function confirmDuplicate() {
  const payload = duplicatePayload.value
  duplicate.value = null
  if (payload) await placeOrder({ ...payload, confirm_duplicate: true })
}

async function placeOrder(payload) {
  submitting.value  = true
  submitError.value = ''
  duplicate.value   = null
  try {
    const { data } = await checkoutApi.placeOrder(payload)
    cartStore.clear?.()

    // Paiement en ligne (GeniusPay, CinetPay…) → rediriger
    if (data.payment_url) {
      window.location.href = data.payment_url
      return
    }

    // Frais de livraison encore à fixer (hors Abidjan, international) : le
    // total n'est pas ferme, donner des instructions de paiement ferait régler
    // la mauvaise somme. La commande se finit avec un agent — la page de suivi
    // affiche le numéro puis bascule sur WhatsApp.
    if (data.shipping_fee_pending) {
      router.push({ name: 'order', params: { number: data.number }, query: { agent: '1' } })
      return
    }

    // Paiement manuel Wave / Orange Money / MTN → instructions
    if (['wave', 'orange_money', 'mtn'].includes(payload.payment_method)) {
      buildPaymentInstructions(payload.payment_method, data.number, data.total ?? orderTotal.value)
      return
    }

    // Carte (Stripe) : un payment_url est attendu. Absent → l'initialisation a échoué.
    if (['card', 'stripe'].includes(payload.payment_method)) {
      submitError.value = data.payment_error
        || t('checkout.cardPaymentError')
      return
    }

    // Autres méthodes → page commande
    router.push({ name: 'order', params: { number: data.number } })
  } catch (e) {
    // 409 : une commande semblable est encore en cours. Rien n'a été écrit —
    // on montre celle-ci, et la cliente décide.
    if (e.response?.status === 409 && e.response.data?.duplicate_order) {
      duplicate.value        = e.response.data.duplicate_order
      duplicatePayload.value = payload
      return
    }

    if (!e._serverError) {
      submitError.value = e.response?.data?.message || t('checkout.submitError')
      fieldErrors.value = mapErrors(e.response?.data?.errors ?? {})
      const errKeys = Object.keys(fieldErrors.value)
      if (errKeys.some(k => ['first_name','last_name','phone','email'].includes(k))) currentStep.value = 1
      else if (errKeys.some(k => k.startsWith('shipping_'))) currentStep.value = 2
    }
  } finally {
    submitting.value = false
  }
}

function formatPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}
</script>

<style scoped>
.co-page {
  min-height: 100vh;
  background: var(--color-bg);
}

/* ── Header stepper ── */
.co-header {
  position: sticky;
  top: 0;
  z-index: 30;
  background: rgba(255, 252, 250, 0.97);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--cream-200);
  box-shadow: 0 1px 12px rgba(0,0,0,0.04);
}

/* Barre de progression visuelle sous le stepper */
.co-progress {
  position: relative;
  height: 3px;
  background: var(--cream-200);
  overflow: hidden;
}
.co-progress__bar {
  position: absolute;
  inset: 0 auto 0 0;
  background: linear-gradient(90deg, var(--rose-400), var(--rose-500));
  transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 0 8px rgba(var(--rose-500-rgb), 0.4);
}
.co-header__inner {
  display: flex;
  align-items: center;
  gap: var(--space-6);
  padding-top: var(--space-4);
  padding-bottom: var(--space-4);
}
.co-header__logo { display: flex; }
.co-header__logo-img { height: 52px; width: auto; }
.co-header__back {
  margin-left: auto;
  font-size: 0.8125rem;
  color: var(--gray-500);
  text-decoration: none;
  transition: color var(--transition-fast);
  white-space: nowrap;
}
.co-header__back:hover { color: var(--rose-500); }

/* ── Stepper ── */
.co-steps {
  display: flex;
  align-items: center;
  gap: 0;
  flex: 1;
  justify-content: center;
}

.co-step {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  background: none;
  border: none;
  padding: 4px 8px;
  cursor: default;
  transition: opacity var(--transition-fast);
}
.co-step[disabled] { opacity: 0.4; cursor: not-allowed; }
.co-step:not([disabled]) { cursor: pointer; }

.co-step__circle {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--cream-200);
  color: var(--gray-400);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8125rem;
  font-weight: 600;
  transition: all var(--transition-normal);
  flex-shrink: 0;
}
.co-step__label {
  font-size: 0.75rem;
  font-weight: 500;
  color: var(--gray-400);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  transition: color var(--transition-fast);
}
.co-step--active .co-step__circle {
  background: var(--rose-500);
  color: #fff;
  box-shadow: 0 2px 12px rgba(var(--rose-500-rgb), 0.3);
}
.co-step--active .co-step__label { color: var(--rose-600); }
.co-step--done .co-step__circle {
  background: var(--rose-100);
  color: var(--rose-600);
}
.co-step--done .co-step__label { color: var(--rose-400); }

.co-step__line {
  flex: 0 0 32px;
  height: 1.5px;
  background: var(--cream-300);
  transition: background var(--transition-normal);
}
.co-step__line--done { background: var(--rose-400); }

/* ── Body ── */
.co-body {
  padding: var(--space-8) var(--space-6) var(--space-16);
}
.co-grid {
  display: grid;
  grid-template-columns: 1fr 400px;
  gap: var(--space-8);
  align-items: stretch;
}
/* Panier vide après confirmation : centrer la section paiement seule */
.co-grid--confirmed {
  grid-template-columns: 1fr;
}
.co-grid--confirmed aside {
  display: none;
}
.co-grid--confirmed .co-form {
  max-width: 560px;
  margin: 0 auto;
  width: 100%;
}

/* ── Form section ── */
.co-section {
  overflow: hidden;
}
.co-section__head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-4);
  padding: var(--space-6);
  border-bottom: 1px solid var(--cream-100);
}
.co-section__icon {
  flex-shrink: 0;
  margin-top: 2px;
  display: flex;
  align-items: center;
}
.co-section__title {
  font-family: var(--font-display);
  font-size: 1.125rem;
  font-weight: 500;
  color: var(--gray-800);
}
.co-section__hint {
  font-size: 0.8125rem;
  color: var(--gray-400);
  margin-top: 3px;
}
.co-section__body {
  padding: var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.co-section__foot {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-6) var(--space-5);
  border-top: 1px solid var(--cream-100);
  background: var(--cream-50);
}
.co-next-btn { margin-left: auto; }
.co-back-btn { font-size: 0.875rem; }

.co-grid-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}

/* ── Payment methods ── */
.payment-methods {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.payment-method {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  border-radius: var(--radius-md);
  border: 1.5px solid var(--cream-300);
  background: #fff;
  cursor: pointer;
  transition: all var(--transition-fast);
  position: relative;
}
.payment-method:hover { border-color: var(--rose-300); }
.payment-method--active {
  border-color: var(--rose-500);
  background: var(--rose-50);
}
.payment-method input { display: none; }
.payment-method__icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
}
.payment-method__body { flex: 1; }
.payment-method__body strong {
  display: block;
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--gray-700);
}
.payment-method__body span {
  font-size: 0.75rem;
  color: var(--gray-400);
}
.payment-method__dot {
  width: 18px; height: 18px;
  border: 1.5px solid var(--cream-300);
  border-radius: 50%;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}
.payment-method--active .payment-method__dot {
  border-color: var(--rose-500);
  background: var(--rose-500);
  box-shadow: inset 0 0 0 3px #fff;
}

/* ── Sidebar ── */
.co-summary {
  align-self: start;           /* ne s'étire pas — la col form absorbe la différence */
  position: sticky;
  top: calc(var(--navbar-height) + var(--space-6));
  padding: var(--space-5) var(--space-6);
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.co-summary__title {
  font-family: var(--font-display);
  font-size: 1.0625rem;
  font-weight: 600;
  color: var(--gray-800);
  padding-bottom: var(--space-3);
  border-bottom: 1px solid var(--cream-200);
}

.co-summary__items {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-height: 240px;
  overflow-y: auto;
  scrollbar-width: thin;
}
.co-summary__item {
  display: grid;
  grid-template-columns: 44px minmax(0, 1fr) auto;
  gap: var(--space-2);
  align-items: center;
}
/* La cellule info doit pouvoir rétrécir pour que l'ellipsis fonctionne
   et que le prix reste sur la même ligne. */
.co-summary__item-info { min-width: 0; }
.co-summary__item-img {
  width: 44px; height: 44px;
  border-radius: var(--radius-sm);
  overflow: hidden;
  background: linear-gradient(145deg, var(--rose-50), var(--cream-100));
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}
.co-summary__item-img img { width: 100%; height: 100%; object-fit: cover; }
.co-summary__item-info p {
  font-size: 0.8125rem;
  color: var(--gray-700);
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.co-summary__item-info span { font-size: 0.6875rem; color: var(--gray-400); }
.co-summary__item-price { font-size: 0.8125rem; font-weight: 600; color: var(--gray-800); white-space: nowrap; }

.co-summary__lines {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3) 0;
  border-top: 1px solid var(--cream-200);
}
.co-summary__lines li {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--gray-600);
}
.co-summary__free { color: #15803d; font-weight: 500; }
.co-summary__pending { color: #b45309; font-weight: 500; }

/* ── Point de repère ── */
.co-landmark {
  resize: none;
  min-height: 52px;
  line-height: 1.5;
  font-size: 0.875rem;
}

/* ── Receveur différent ── */
.co-receiver {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.co-receiver__toggle {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.875rem;
  color: var(--gray-600);
  cursor: pointer;
  user-select: none;
}
.co-receiver__cb {
  width: 16px;
  height: 16px;
  accent-color: var(--rose-500);
  cursor: pointer;
  flex-shrink: 0;
}
.co-receiver__field {
  margin-top: var(--space-1);
}

.co-shipping-notice {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
  margin-top: var(--space-3);
  padding: var(--space-3) var(--space-4);
  background: var(--gold-100, #fef9c3);
  border: 1px solid var(--gold-200, #fde68a);
  border-radius: var(--radius-md, 0.5rem);
}
.co-shipping-notice span { font-size: 1.125rem; flex-shrink: 0; }
.co-shipping-notice strong {
  display: block;
  font-size: 0.875rem;
  color: var(--gold-600, #b45309);
  margin-bottom: 2px;
}
.co-shipping-notice p {
  font-size: 0.8125rem;
  color: var(--gold-600, #b45309);
  line-height: 1.5;
  margin: 0;
}
.co-summary__discount { color: #15803d; }
.co-summary__total {
  font-family: var(--font-sans);
  font-size: 1.125rem !important;
  font-weight: 600;
  color: var(--gray-800) !important;
  padding-top: var(--space-2);
  border-top: 1px solid var(--cream-200);
}
.co-summary__total span:last-child { color: var(--rose-600); }

/* ── Promo dans la sidebar ── */
.co-promo {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-4);
  background: var(--cream-50);
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
}
.co-promo__input-wrap {
  display: flex;
  gap: var(--space-2);
}
.co-promo__input {
  flex: 1;
  text-transform: uppercase;
  font-size: 0.8125rem;
  padding: 8px 12px;
}

.co-summary__cta {
  width: 100%;
  justify-content: center;
}
.co-summary__legal {
  font-size: 0.6875rem;
  color: var(--gray-400);
  text-align: center;
}

/* ── Messages ── */
.co-msg { font-size: 0.8125rem; }
.co-msg--error { color: #b91c1c; }
.co-msg--success { color: #15803d; }
/* Portée d'un code ciblé : une précision, pas une alerte. */
.co-coupon-scope { color: var(--gray-500); margin-top: 2px; }

.co-shipping-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: var(--space-3);
  padding: 10px 12px;
  border-radius: var(--radius-md, 10px);
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
  font-size: 0.8125rem;
  line-height: 1.45;
}
.co-shipping-note svg { flex-shrink: 0; margin-top: 2px; }

/* ── Commande internationale : finalisation par un agent WhatsApp ── */
.co-intl {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding: var(--space-4);
  border-radius: var(--radius-md, 10px);
  background: var(--cream-50, #fdfaf8);
  border: 1px solid var(--cream-300, #e8ddd6);
}
.co-intl__lead {
  font-size: 0.9375rem;
  line-height: 1.55;
  color: var(--gray-700);
}
.co-intl__cta {
  align-self: stretch;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.co-intl__note {
  font-size: 0.75rem;
  color: var(--gray-500);
  text-align: center;
}

/* Bannière hors Abidjan — prominente, à l'étape adresse */
.co-outzone {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-top: var(--space-4);
  padding: 14px 16px;
  border-radius: var(--radius-lg, 14px);
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
  border: 1.5px solid #fdba74;
  box-shadow: 0 2px 12px rgba(234, 88, 12, .10);
}
.co-outzone__icon {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  width: 38px; height: 38px;
  border-radius: 50%;
  background: #fed7aa;
  color: #c2410c;
}
.co-outzone__body strong {
  display: block;
  font-size: 0.9375rem;
  font-weight: 700;
  color: #9a3412;
  margin-bottom: 3px;
}
.co-outzone__body p {
  margin: 0;
  font-size: 0.8125rem;
  line-height: 1.5;
  color: #b45309;
}
.co-outzone__body b { color: #9a3412; font-weight: 700; }

/* ── Badges brandés (méthodes de paiement) ── */
.pm-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 9px;
  font-weight: 800;
  font-size: 0.6875rem;
  letter-spacing: -0.01em;
  color: #fff;
  flex-shrink: 0;
  line-height: 1;
}
.pm-badge--wave   { background: #1dc3f0; }
.pm-badge--orange { background: #ff6600; }
.pm-badge--mtn    { background: #ffcb00; color: #333; }
/* Grande variante pour l'en-tête des instructions */
.pm-badge--lg {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  font-size: 0.8125rem;
}

/* ── Spinner ── */
.co-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Transitions ── */
.step-slide-enter-active,
.step-slide-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.step-slide-enter-from { opacity: 0; transform: translateX(16px); }
.step-slide-leave-to   { opacity: 0; transform: translateX(-16px); }

.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* ── Responsive ── */
@media (max-width: 1024px) {
  .co-grid {
    grid-template-columns: 1fr;
    gap: var(--space-5);
  }
  /* Sidebar sous le formulaire sur tablette/mobile */
  .co-summary {
    position: static;
    order: 2;
  }
  .co-summary__items { max-height: 160px; }
}

@media (max-width: 768px) {
  /* Mini recap mobile visible */
  .co-mini-recap { display: block; }

  /* Sidebar complète masquée sur mobile (remplacée par mini-recap + CTA téléporté) */
  .co-summary { display: none; }

  .co-grid-2 { grid-template-columns: 1fr; }
  /* Mobile : on garde un label court (10-12 chars max) pour que l'utilisateur
     sache où il en est. Le label est défini côté JS via STEPS[i].label. */
  .co-step__label {
    display: inline;
    font-size: 0.625rem;
    letter-spacing: 0.04em;
    max-width: 70px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .co-body {
    padding: var(--space-4) var(--space-3) 100px; /* 100px pour le CTA fixé */
  }
  .co-header__inner {
    padding-top: 10px;
    padding-bottom: 10px;
    gap: var(--space-2);
  }
  .co-header__logo-img { height: 32px; }
  .co-header__back {
    font-size: 0.75rem;
    /* Cacher le label "Panier" sur mobile, garder juste la flèche */
    overflow: hidden;
    max-width: 24px;
    text-indent: -9999px;
    position: relative;
  }
  .co-header__back::before {
    content: '←';
    position: absolute;
    left: 0; right: 0; top: 0;
    text-indent: 0;
    font-size: 1rem;
  }

  .co-section__head {
    padding: var(--space-4);
    gap: var(--space-3);
  }
  .co-section__icon { font-size: 1.25rem; }
  .co-section__title { font-size: 1rem; }
  .co-section__body { padding: var(--space-4); gap: var(--space-3); }
  .co-section__foot {
    padding: var(--space-3) var(--space-4);
    flex-wrap: wrap;
    gap: var(--space-2);
  }

  .co-next-btn, .co-back-btn {
    flex: 1;
    justify-content: center;
    padding: 14px;
    font-size: 0.9375rem;
  }

  .payment-method {
    padding: var(--space-4);
  }

  .co-steps { gap: 0; justify-content: center; }
  .co-step { padding: 4px 6px; }
  .co-step__line { flex: 0 0 20px; }
}

/* ── Mini récap mobile ── */
.co-mini-recap {
  display: none; /* affiché uniquement sur mobile via media query */
  background: #fff;
  border-bottom: 1px solid var(--cream-200);
  /* En mode flux normal (pas sticky) : la mini-recap doit s'effacer quand
     l'utilisateur scrolle pour ne PAS rivaliser avec le stepper sticky. */
  position: relative;
  z-index: 19;
}
.co-mini-recap__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--space-3) 0;
}
.co-mini-recap__count {
  font-size: 0.8125rem;
  color: var(--gray-500);
}
.co-mini-recap__toggle {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--rose-600);
  background: none;
  border: none;
  cursor: pointer;
  font-family: var(--font-display);
}
.co-mini-recap__detail {
  padding: var(--space-4) 0;
  border-top: 1px solid var(--cream-100);
}
.co-mini-items {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
}
.co-mini-item {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  gap: var(--space-2);
  font-size: 0.8125rem;
  color: var(--gray-600);
  align-items: center;
}
.co-mini-item__name {
  min-width: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.co-mini-item__price { white-space: nowrap; }
.co-mini-item__qty { color: var(--gray-400); }
.co-mini-item__price { font-weight: 600; color: var(--gray-800); }
.co-mini-total {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
  color: var(--gray-500);
  padding-top: var(--space-2);
  border-top: 1px solid var(--cream-100);
}
.co-mini-total--bold {
  font-family: var(--font-sans);
  font-weight: 600;
  font-size: 1rem;
  color: var(--gray-800);
  border-top: 1px solid var(--cream-200);
  margin-top: var(--space-1);
}
.co-mini-total--bold span:last-child { color: var(--rose-600); }

/* ── CTA mobile fixé en bas ── */
.co-mobile-cta {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 60;
  background: rgba(255, 252, 250, 0.97);
  backdrop-filter: blur(12px);
  border-top: 1px solid var(--cream-200);
  padding: var(--space-3) var(--space-4) env(safe-area-inset-bottom, 16px);
  box-shadow: 0 -4px 24px rgba(0,0,0,0.08);
}
.co-mobile-cta__inner {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}
.co-mobile-cta__total {
  display: flex;
  flex-direction: column;
  gap: 1px;
  flex-shrink: 0;
}
.co-mobile-cta__total span { font-size: 0.6875rem; color: var(--gray-400); }
.co-mobile-cta__total strong {
  font-family: var(--font-sans);
  font-size: 1rem;
  font-weight: 700;
  color: var(--rose-600);
}
.co-mobile-cta__btn {
  flex: 1;
  justify-content: center;
  padding: 14px;
  font-size: 0.9375rem;
}

/* ── Instructions paiement manuel ── */
.pay-instr__number-box {
  text-align: center;
  padding: var(--space-5) var(--space-4);
  background: linear-gradient(135deg, var(--rose-50), var(--cream-50));
  border: 2px solid var(--rose-200);
  border-radius: var(--radius-lg);
  cursor: pointer;
}
.pay-instr__label { font-size: 0.75rem; color: var(--gray-500); margin-bottom: var(--space-2); }
.pay-instr__number {
  font-family: var(--font-display);
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--rose-600);
  letter-spacing: 0.05em;
}
.pay-instr__copy-hint { font-size: 0.6875rem; color: var(--gray-400); margin-top: 4px; }
.pay-instr__amount-row,
.pay-instr__ref-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-2) 0;
  border-bottom: 1px solid var(--cream-100);
  font-size: 0.875rem;
  color: var(--gray-600);
}
.pay-instr__amount {
  font-family: var(--font-sans);
  font-size: 1.0625rem;
  color: var(--rose-600);
}
.pay-instr__steps {
  padding: var(--space-3) var(--space-4);
  background: var(--cream-50);
  border-radius: var(--radius-md);
  border: 1px solid var(--cream-200);
}
.pay-instr__steps-title { font-size: 0.75rem; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; }
.pay-instr__steps-text { font-size: 0.8125rem; color: var(--gray-500); line-height: 1.7; white-space: pre-line; }

/* ── Montant en évidence ── */
.pay-instr__amount-hero {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: var(--space-5) var(--space-4);
  background: linear-gradient(135deg, var(--rose-500), var(--rose-400));
  border-radius: var(--radius-lg);
  color: #fff;
  text-align: center;
}

/* Bouton « Payer avec Wave » */
.pay-instr__wave-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 14px 20px;
  border-radius: var(--radius-full, 999px);
  background: #1dc3f0; /* bleu Wave */
  color: #fff;
  font-size: 1rem;
  font-weight: 700;
  text-decoration: none;
  transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
  box-shadow: 0 4px 16px rgba(29, 195, 240, .38);
}
.pay-instr__wave-btn:hover {
  background: #06b6e0;
  transform: translateY(-1px);
  box-shadow: 0 6px 22px rgba(29, 195, 240, .48);
}
.pay-instr__wave-btn:active { transform: translateY(0); }
.pay-instr__amount-hero-label {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  opacity: 0.9;
}
.pay-instr__amount-hero-value {
  font-family: var(--font-sans);
  font-size: 2rem;
  font-weight: 700;
  line-height: 1.1;
}

/* ── Cartes copiables (numéro / référence) ── */
.pay-instr__copy-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-4);
  background: var(--cream-50);
  border: 1.5px solid var(--rose-200);
  border-radius: var(--radius-lg);
  cursor: pointer;
  text-align: left;
  transition: border-color var(--transition-fast), background var(--transition-fast);
}
.pay-instr__copy-card:hover { border-color: var(--rose-400); background: var(--rose-50); }
.pay-instr__copy-card--sm { border-style: dashed; }
.pay-instr__copy-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.pay-instr__copy-label { font-size: 0.6875rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.04em; }
.pay-instr__copy-value {
  font-family: var(--font-display);
  font-size: 1.375rem;
  font-weight: 700;
  color: var(--rose-600);
  letter-spacing: 0.04em;
  word-break: break-all;
}
.pay-instr__copy-value--sm { font-size: 1rem; color: var(--gray-800); }
.pay-instr__copy-action {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  flex-shrink: 0;
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--rose-600);
}
.pay-instr__wa {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  width: 100%;
  padding: var(--space-3);
  background: #25d366;
  color: #fff;
  border-radius: var(--radius-md);
  font-weight: 600;
  font-size: 0.875rem;
  text-decoration: none;
  transition: background var(--transition-fast);
}
.pay-instr__wa:hover { background: #1db954; }

.pay-instr__foot {
  justify-content: space-between;
  flex-wrap: wrap;
  gap: var(--space-2);
}
.pay-instr__foot .btn {
  flex: 1 1 0;
  min-width: 0;            /* autorise le rétrécissement */
  justify-content: center;
  text-align: center;
  white-space: normal;     /* le texte peut passer à la ligne au lieu de déborder */
  padding-inline: var(--space-3);
  font-size: 0.875rem;
  line-height: 1.25;
}
/* Sur petit écran : empile les boutons en pleine largeur */
@media (max-width: 480px) {
  .pay-instr__foot { flex-direction: column; }
  .pay-instr__foot .btn { width: 100%; flex: none; }
}

.co-empty__body {
  padding: var(--space-8) var(--space-6);
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
}
.co-empty__icon {
  display: flex;
  justify-content: center;
  align-items: center;
  color: var(--rose-300);
}
.co-empty__hint { color: var(--gray-500); font-size: 0.9375rem; max-width: 380px; }
.co-empty__cta { margin-top: var(--space-3); padding: 12px 28px; text-decoration: none; }

/* Transitions */
.expand-enter-active, .expand-leave-active { transition: opacity 0.2s ease; }
.expand-enter-from, .expand-leave-to { opacity: 0; }

.slide-up-enter-active, .slide-up-leave-active { transition: transform 0.25s ease, opacity 0.25s ease; }
.slide-up-enter-from, .slide-up-leave-to { transform: translateY(100%); opacity: 0; }
</style>
