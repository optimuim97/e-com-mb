<template>
  <Teleport to="body">
    <div class="qo-overlay" @click.self="$emit('close')">
      <div class="qo-card">

        <!-- ── Zone scrollable ── -->
        <div class="qo-body">

          <!-- ── Confirmation post-commande ────────────────────────────── -->
          <div v-if="confirmed" class="qo-confirmed">
            <div class="qo-confirmed__checkmark">
              <svg width="56" height="56" viewBox="0 0 56 56" fill="none">
                <circle cx="28" cy="28" r="28" fill="#dcfce7"/>
                <path d="M18 28l8 8 12-14" stroke="#15803d" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <h2 class="qo-confirmed__title">{{ $t('quickOrder.confirmedTitle') }}</h2>
            <p class="qo-confirmed__number">{{ $t('quickOrder.confirmedNumber') }} <strong>{{ confirmedOrder.number }}</strong></p>

            <!--
              Hors Abidjan et international : ni les frais ni le règlement ne
              sont arrêtés. On met l'agent en avant et on tait le total, qui
              n'est qu'un sous-total — l'afficher comme définitif obligerait à
              se dédire dès le premier message.
            -->
            <div v-if="needsAgent" class="qo-agent">
              <p class="qo-agent__title">{{ $t('orders.agentTitle') }}</p>
              <p class="qo-agent__desc">{{ $t('orders.agentDesc') }}</p>
              <p class="qo-agent__subtotal">
                {{ $t('common.subtotal') }} : <strong>{{ fmtPrice(confirmedOrder.subtotal ?? confirmedOrder.total) }}</strong>
                <span>{{ $t('orders.agentSubtotalHint') }}</span>
              </p>

              <!--
                Bascule automatique, annulable. Le délai laisse le temps de
                relever le numéro de commande : si WhatsApp n'aboutit pas,
                c'est tout ce qui reste à la cliente pour nous retrouver.
              -->
              <p v-if="needsAgent && !adminWhatsappLink" class="qo-agent__redirect">
                {{ $t('orders.agentNoWhatsapp') }}
                <strong v-if="settingsStore.shopPhone">{{ settingsStore.shopPhone }}</strong>
              </p>

              <p v-if="agentRedirect.bloque.value" class="qo-agent__redirect">
                {{ $t('orders.agentBlocked') }}
              </p>

              <p v-else-if="agentRedirect.active.value" class="qo-agent__redirect">
                {{ $t('orders.agentRedirect', { count: agentRedirect.secondes.value }) }}
                <button type="button" class="qo-agent__stay" @click="agentRedirect.cancel()">
                  {{ $t('orders.agentStay') }}
                </button>
              </p>
            </div>

            <!-- Paiement Wave : montant + bouton payer -->
            <div v-if="isWavePayment && !needsAgent" class="qo-wave-box">
              <p class="qo-wave-box__label">{{ $t('quickOrder.wavePayLabel') }}</p>
              <p class="qo-wave-box__total">{{ $t('quickOrder.waveAmount') }} <strong>{{ fmtPrice(confirmedOrder.total) }}</strong></p>
              <p v-if="!wavePayUrl && waveNumber" class="qo-wave-box__number">{{ waveNumber }}</p>

              <a v-if="wavePayUrl" :href="wavePayUrl" target="_blank" rel="noopener" class="qo-wave-pay">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                {{ $t('orders.payAmountWithWave', { amount: fmtPrice(confirmedOrder.total) }) }}
              </a>
            </div>

            <!-- Étape 2 : envoyer la capture du paiement sur WhatsApp -->
            <a v-if="adminWhatsappLink" :href="adminWhatsappLink" target="_blank" rel="noopener"
              class="btn btn-whatsapp qo-wa-btn" :class="{ 'qo-wa-btn--primary': needsAgent }"
              @click="agentRedirect.cancel()">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
              {{ needsAgent
                ? $t('orders.agentCta')
                : (isWavePayment ? $t('quickOrder.sendProof') : $t('quickOrder.whatsapp')) }}
            </a>

            <!-- ── Widget complétion profil ──────────────────────── -->
            <div v-if="profileNudge" class="qo-nudge">
              <p class="qo-nudge__title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
                {{ profileNudge.title }}
              </p>
              <p class="qo-nudge__desc">{{ profileNudge.desc }}</p>

              <!-- Cas : compte rapide → ajouter email + mot de passe -->
              <form v-if="profileNudge.type === 'setup'" @submit.prevent="doSetup" class="qo-nudge__form">
                <input v-model="nudgeForm.email" type="email" class="input input--sm w-100" :placeholder="$t('auth.emailPlaceholder')" required />
                <input v-model="nudgeForm.password" type="password" class="input input--sm w-100" :placeholder="$t('quickOrder.pwPlaceholder')" required minlength="8" />
                <p v-if="nudgeError" class="qo-nudge__err">{{ nudgeError }}</p>
                <button type="submit" class="btn btn-primary btn-sm" :disabled="nudgeSaving">
                  <span v-if="nudgeSaving" class="qo-geo-spin"></span>
                  <span v-else>{{ $t('quickOrder.secure') }} →</span>
                </button>
                <p v-if="nudgeSuccess" class="qo-nudge__ok">✓ {{ nudgeSuccess }}</p>
              </form>

              <!-- Cas : pas de téléphone → ajouter le numéro -->
              <form v-else-if="profileNudge.type === 'phone'" @submit.prevent="doSavePhone" class="qo-nudge__form">
                <PhoneInput v-model="nudgeForm.phone" placeholder="07 00 00 00 00" />
                <p v-if="nudgeError" class="qo-nudge__err">{{ nudgeError }}</p>
                <button type="submit" class="btn btn-primary btn-sm" :disabled="nudgeSaving">
                  <span v-if="nudgeSaving" class="qo-geo-spin"></span>
                  <span v-else>{{ $t('common.save') }} →</span>
                </button>
                <p v-if="nudgeSuccess" class="qo-nudge__ok">✓ {{ nudgeSuccess }}</p>
              </form>
            </div>

            <div class="qo-confirmed__actions">
              <button class="btn btn-primary" @click="viewOrder">{{ $t('quickOrder.viewOrder') }}</button>

              <!--
                La facture part par email, or une grande partie de la clientèle
                n'en a pas et ne va pas en créer un pour l'occasion. Le
                téléchargement direct est donc le seul chemin qui la lui remet
                vraiment, ici, tant qu'elle a la commande sous les yeux.
              -->
              <button class="btn btn-outline btn-sm" :disabled="downloadingInvoice" @click="downloadInvoice">
                <span v-if="downloadingInvoice" class="qo-geo-spin"></span>
                <span v-else>{{ $t('quickOrder.downloadInvoice') }}</span>
              </button>

              <button v-if="!profileNudge" class="btn btn-outline btn-sm" @click="goToProfile">{{ $t('quickOrder.secureAccount') }}</button>
            </div>
            <p v-if="invoiceError" class="qo-nudge__err">{{ invoiceError }}</p>
          </div>

          <!-- ── Formulaire commande rapide ────────────────────────────── -->
          <template v-else>
            <button class="qo-close" @click="$emit('close')" :aria-label="$t('common.close')">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>

            <div class="qo-header">
              <span class="eyebrow">{{ $t('quickOrder.title') }}</span>
              <h2 class="qo-title">{{ $t('quickOrder.subtitle') }} <em>{{ $t('quickOrder.subtitleEm') }}</em> {{ $t('quickOrder.subtitleSuffix') }}</h2>
            </div>

            <!--
              Récapitulatif modifiable. Les chips en lecture seule obligeaient à
              fermer la commande rapide, rouvrir le panier, corriger, puis tout
              ressaisir : une quantité mal choisie faisait perdre le formulaire.
              Une ligne par article, un pas de quantité, rien de plus — la
              modale reste courte.
            -->
            <ul class="qo-items">
              <li v-for="item in cartItems" :key="item.id" class="qo-item">
                <span class="qo-item__name">{{ item.product?.name ?? item.name ?? $t('drawer.defaultProduct') }}</span>

                <div class="qo-item__qty">
                  <button
                    type="button"
                    class="qo-qty-btn"
                    :disabled="ligneEnCours === item.id"
                    :aria-label="$t('drawer.decrease')"
                    @click="changerQuantite(item, -1)"
                  >−</button>
                  <span class="qo-qty-value">{{ item.quantity }}</span>
                  <button
                    type="button"
                    class="qo-qty-btn qo-qty-btn--plus"
                    :disabled="ligneEnCours === item.id"
                    :aria-label="$t('drawer.increase')"
                    @click="changerQuantite(item, 1)"
                  >+</button>
                </div>

                <span class="qo-item__total">{{ fmtPrice(ligneTotal(item)) }}</span>

                <button
                  type="button"
                  class="qo-item__remove"
                  :disabled="ligneEnCours === item.id"
                  :aria-label="$t('drawer.remove')"
                  @click="retirerLigne(item)"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
              </li>
            </ul>

            <p v-if="erreurLigne" class="qo-items-error">{{ erreurLigne }}</p>

            <p v-if="!cartItems.length" class="qo-items-empty">{{ $t('quickOrder.emptyCart') }}</p>

            <!--
              novalidate : la validation est faite en JavaScript. Le navigateur
              refusait de soumettre à cause d'un champ « commune » requis mais
              masqué, qu'il ne pouvait ni signaler ni focaliser — le clic sur
              « Commander » ne produisait alors strictement rien.
            -->
            <!--
              `submit()` et non `submit` : sans les parenthèses, Vue passe
              l'événement du formulaire en premier argument, donc dans
              `confirmDuplicate` — l'API refusait la commande, et un événement
              étant « vrai », elle aurait sinon confirmé les doublons seule.
            -->
            <form id="qo-form" ref="formEl" novalidate @submit.prevent="submit()" class="qo-form">
              <div class="qo-field" data-field="name">
                <div class="qo-field-head">
                  <label class="label">{{ $t('quickOrder.name') }} *</label>
                  <span v-if="prefilled.name" class="qo-prefilled-badge">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $t('quickOrder.profileBadge') }}
                  </span>
                </div>
                <input
                  v-model="form.name"
                  type="text"
                  class="input"
                  :class="{ 'input--prefilled': prefilled.name, 'input--error': fieldErrors.name }"
                  :aria-invalid="!!fieldErrors.name"
                  :placeholder="$t('quickOrder.namePlaceholder')"
                />
                <p v-if="fieldErrors.name" class="qo-required">{{ $t('quickOrder.required') }}</p>
              </div>

              <div class="qo-field" data-field="phone">
                <div class="qo-field-head">
                  <label class="label">{{ $t('quickOrder.phone') }} *</label>
                  <span v-if="prefilled.phone" class="qo-prefilled-badge">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $t('quickOrder.profileBadge') }}
                  </span>
                </div>
                <PhoneInput
                  v-model="form.phone"
                  placeholder="07 00 00 00 00"
                  :required="true"
                  :has-error="!!fieldErrors.phone"
                  :class="{ 'input--prefilled': prefilled.phone }"
                  @validity="phoneValidity = $event"
                />
                <p v-if="fieldErrors.phone" class="qo-required">{{ $t('quickOrder.required') }}</p>
              </div>

              <div class="qo-field">
                <div class="qo-field-head">
                  <label class="label">
                    {{ $t('quickOrder.email') }} <span class="qo-optional">({{ $t('quickOrder.emailOptional') }})</span>
                  </label>
                  <span v-if="prefilled.email" class="qo-prefilled-badge">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    {{ $t('quickOrder.profileBadge') }}
                  </span>
                </div>
                <input v-model="form.email" type="email" class="input" :class="{ 'input--prefilled': prefilled.email }" :placeholder="$t('quickOrder.emailPlaceholder')" />
              </div>

              <div class="qo-field" data-field="commune">
                <div class="qo-field-head">
                  <label class="label">{{ $t('quickOrder.commune', { zone: settingsStore.homeCity }) }} *</label>
                </div>
                <!--
                  Destination affichée en premier, et non plus cachée au fond
                  de la liste des communes : personne ne cherche une adresse
                  « hors Abidjan » sous treize communes d'Abidjan. Abidjan
                  reste présélectionné, la majorité des clientes ne perd donc
                  aucun geste.
                -->
                <div class="qo-dest" role="radiogroup" :aria-label="$t('quickOrder.destinationLabel')">
                  <button
                    v-for="d in DESTINATIONS"
                    :key="d.value"
                    type="button"
                    class="qo-dest__btn"
                    :class="{ 'qo-dest__btn--active': destination === d.value }"
                    role="radio"
                    :aria-checked="destination === d.value"
                    @click="destination = d.value"
                  >
                    <span class="qo-dest__icon" v-html="d.icon"></span>
                    <span class="qo-dest__label">{{ d.label }}</span>
                  </button>
                </div>

                <!-- Combobox searchable — communes d'Abidjan uniquement -->
                <div v-if="destination === 'abidjan'" class="qo-combobox" v-click-outside="closeCommune">
                  <div
                    class="qo-combobox__trigger"
                    :class="{ 'qo-combobox__trigger--error': fieldErrors.commune }"
                    tabindex="0"
                    role="combobox"
                    :aria-expanded="communeOpen"
                    :aria-invalid="!!fieldErrors.commune"
                    @click="openCommune"
                    @keydown.enter.prevent="openCommune"
                    @keydown.space.prevent="openCommune"
                  >
                    <span v-if="communeDisplay" class="qo-combobox__value">{{ communeDisplay }}</span>
                    <span v-else class="qo-combobox__placeholder">{{ $t('quickOrder.communePlaceholder') }}</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="qo-combobox__arrow" :class="{ 'qo-combobox__arrow--open': communeOpen }">
                      <polyline points="6 9 12 15 18 9"/>
                    </svg>
                  </div>

                  <div v-if="communeOpen" class="qo-combobox__dropdown">
                    <input
                      ref="communeSearchInput"
                      v-model="communeSearch"
                      type="text"
                      class="qo-combobox__search"
                      :placeholder="$t('quickOrder.searchPlaceholder')"
                      @click.stop
                      @keydown.enter.prevent="onCommuneEnter"
                      @keydown.esc.prevent="closeCommune"
                    />
                    <ul class="qo-combobox__list">
                      <li
                        v-for="c in filteredCommunes"
                        :key="c"
                        class="qo-combobox__option"
                        :class="{ 'qo-combobox__option--active': form.commune === c }"
                        @click="selectCommune(c)"
                      >{{ c }}</li>
                      <li class="qo-combobox__option qo-combobox__option--other" @click="selectCommune('__autre__')">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        {{ $t('quickOrder.enterManually') }}
                      </li>
                      <li v-if="filteredCommunes.length === 0 && communeSearch" class="qo-combobox__empty">
                        {{ $t('common.noResults') }}
                      </li>
                    </ul>
                  </div>
                </div>

                <!-- Saisie manuelle si "Autre" -->
                <input
                  v-if="destination === 'abidjan' && form.commune === '__autre__'"
                  v-model="form.communeManuel"
                  type="text"
                  class="input qo-commune-manuel"
                  :class="{ 'input--error': fieldErrors.commune }"
                  :aria-invalid="!!fieldErrors.commune"
                  :placeholder="$t('quickOrder.communeManualPlaceholder')"
                />

                <p v-if="fieldErrors.commune" class="qo-required">{{ $t('quickOrder.required') }}</p>

              </div>

              <!-- Pays, uniquement à l'international -->
              <div v-if="estInternational" class="qo-field" data-field="country">
                <label class="label">{{ $t('fields.country') }} *</label>
                <select
                  v-model="form.country"
                  class="input"
                  :class="{ 'input--error': fieldErrors.country }"
                  :aria-invalid="!!fieldErrors.country"
                >
                  <option value="" disabled>{{ $t('quickOrder.chooseCountry') }}</option>
                  <option v-for="p in PAYS_INTERNATIONAUX" :key="p.code" :value="p.code">{{ p.nom }}</option>
                </select>
                <p v-if="fieldErrors.country" class="qo-required">{{ $t('quickOrder.required') }}</p>
              </div>

              <!-- Ville obligatoire dès qu'on sort d'Abidjan -->
              <div v-if="showCityField" class="qo-field" data-field="city">
                <label class="label">{{ $t('fields.city') }} *</label>
                <!--
                  Liste fermée en Côte d'Ivoire : une ville mal orthographiée ne
                  se rattache à aucune zone et les frais partent en traitement
                  manuel. À l'international, aucune liste ne tiendrait — et les
                  frais s'y négocient de toute façon au cas par cas.
                -->
                <!--
                  Cherchable, et sur les communes autant que sur les villes : une
                  liste déroulante de cinquante-sept villes se parcourt mal au
                  pouce, et personne ne devine sous quelle ville se range son
                  quartier. Chaque ligne annonce son tarif quand la destination
                  tombe dans une de nos zones.
                -->
                <div
                  v-if="!estInternational"
                  class="qo-combobox"
                  v-click-outside="closeCity"
                >
                  <div
                    class="qo-combobox__trigger"
                    :class="{ 'qo-combobox__trigger--error': fieldErrors.city }"
                    tabindex="0"
                    role="combobox"
                    :aria-expanded="cityOpen"
                    :aria-invalid="!!fieldErrors.city"
                    @click="openCity"
                    @keydown.enter.prevent="openCity"
                    @keydown.space.prevent="openCity"
                  >
                    <span v-if="form.city" class="qo-combobox__value">
                      {{ form.city }}
                      <span v-if="form.cityCommune" class="qo-combobox__sub">· {{ form.cityCommune }}</span>
                    </span>
                    <span v-else class="qo-combobox__placeholder">{{ $t('quickOrder.chooseCity') }}</span>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="qo-combobox__arrow" :class="{ 'qo-combobox__arrow--open': cityOpen }">
                      <polyline points="6 9 12 15 18 9"/>
                    </svg>
                  </div>

                  <div v-if="cityOpen" class="qo-combobox__dropdown">
                    <input
                      ref="citySearchInput"
                      v-model="citySearch"
                      type="text"
                      class="qo-combobox__search"
                      :placeholder="$t('quickOrder.searchCityPlaceholder')"
                      @click.stop
                      @keydown.enter.prevent="onCityEnter"
                      @keydown.esc.prevent="closeCity"
                    />
                    <ul class="qo-combobox__list">
                      <li
                        v-for="d in filteredCities"
                        :key="d.city"
                        class="qo-combobox__option qo-combobox__option--dest"
                        :class="{ 'qo-combobox__option--active': form.city === d.city }"
                        @click="selectCity(d)"
                      >
                        <span class="qo-dest-opt">
                          <span class="qo-dest-opt__name">{{ d.city }}</span>
                          <span v-if="d.matchedCommune" class="qo-dest-opt__sub">{{ d.matchedCommune.name }}</span>
                          <span v-else-if="d.communes.length > 1" class="qo-dest-opt__sub">
                            {{ d.communes.length }} {{ $t('quickOrder.communesCount') }}
                          </span>
                        </span>
                        <span class="qo-dest-opt__price" :class="{ 'qo-dest-opt__price--unknown': destPrice(d) === null }">
                          {{ destPrice(d) === null ? $t('quickOrder.priceOnRequest') : fmtPrice(destPrice(d)) }}
                        </span>
                      </li>
                      <li v-if="!filteredCities.length && citySearch" class="qo-combobox__empty">
                        {{ $t('common.noResults') }}
                      </li>
                    </ul>
                  </div>
                </div>
                <input
                  v-else
                  v-model="form.city"
                  type="text"
                  class="input"
                  :class="{ 'input--error': fieldErrors.city }"
                  :aria-invalid="!!fieldErrors.city"
                  :placeholder="$t('quickOrder.cityPlaceholder')"
                />
                <p v-if="fieldErrors.city" class="qo-required">{{ $t('quickOrder.required') }}</p>
                <p v-if="!estInternational" class="qo-city-hint">{{ $t('quickOrder.cityHint', { zone: settingsStore.homeCity }) }}</p>
              </div>

              <!-- Bannière hors Abidjan -->
              <div v-if="showCityField && !estInternational" class="qo-outzone">
                <span class="qo-outzone__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                </span>
                <div>
                  <strong>{{ $t('checkout.outsideAbidjanTitle', { zone: settingsStore.homeCity }) }}</strong>
                  <p>{{ $t('quickOrder.outzoneP1') }} <b>{{ $t('quickOrder.outzoneB1') }}</b> {{ $t('quickOrder.outzoneP2', { zone: settingsStore.homeCity }) }} <b>{{ $t('checkout.outsideAbidjanB2') }}</b>{{ $t('checkout.outsideAbidjanP3', { zone: settingsStore.homeCity }) }}</p>
                </div>
              </div>

              <!-- Bannière internationale -->
              <div v-if="estInternational" class="qo-outzone">
                <span class="qo-outzone__icon">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/></svg>
                </span>
                <div>
                  <strong>{{ $t('quickOrder.intlTitle') }}</strong>
                  <p>{{ $t('quickOrder.intlP1') }} <b>{{ $t('quickOrder.intlB1') }}</b>{{ $t('quickOrder.intlP2') }} <b>{{ $t('quickOrder.intlB2') }}</b></p>
                </div>
              </div>

              <!--
                Indication de livraison — et c'est ici que vit « Ma position ».
                Le bouton était contre le label « Commune » ; la détection
                remplit pourtant commune, ville ET cette indication. Contre ce
                champ-ci, on voit ce qu'elle a écrit.
              -->
              <div class="qo-field">
                <div class="qo-field-head">
                  <label class="label">
                    {{ $t('quickOrder.indication') }} <span class="qo-optional">({{ $t('quickOrder.indicationOptional') }})</span>
                  </label>
</div>
                <textarea
                  v-model="form.indication"
                  class="input qo-indication"
                  rows="2"
                  :placeholder="$t('quickOrder.indicationPlaceholder')"
                />
              </div>

              <!-- Aucun règlement en ligne à l'international : rien à choisir. -->
              <div v-if="paymentMethods.length" class="qo-field" data-field="payment">
                <label class="label">{{ $t('quickOrder.payment') }} *</label>
                <div class="qo-payments" :class="{ 'qo-payments--error': fieldErrors.payment }">
                  <label v-for="pm in paymentMethods" :key="pm.value"
                    class="qo-payment" :class="{ 'qo-payment--active': form.payment === pm.value }">
                    <input type="radio" :value="pm.value" v-model="form.payment" />
                    <span class="qo-payment__icon" v-html="pm.icon"></span>
                    <span class="qo-payment__label">{{ pm.label }}</span>
                  </label>
                </div>
                <p v-if="fieldErrors.payment" class="qo-required">{{ $t('quickOrder.required') }}</p>
              </div>

              <!--
                Code promo. Le tunnel classique en a toujours eu un ; la
                commande rapide, non — une cliente à qui on venait d'envoyer un
                code devait repasser par le panier pour s'en servir.
              -->
              <div class="qo-field" data-field="coupon">
                <label class="label">
                  {{ $t('quickOrder.coupon') }}
                  <span class="qo-optional">({{ $t('quickOrder.noteOptional') }})</span>
                </label>
                <div class="qo-coupon">
                  <input
                    v-model="couponCode"
                    type="text"
                    class="input qo-coupon__input"
                    :placeholder="$t('quickOrder.couponPlaceholder')"
                    :disabled="couponApplied"
                    @keydown.enter.prevent="applyCoupon"
                  />
                  <button
                    type="button"
                    class="btn btn-outline btn-sm qo-coupon__btn"
                    :disabled="couponLoading || couponApplied || !couponCode.trim()"
                    @click="couponApplied ? null : applyCoupon()"
                  >
                    {{ couponLoading ? '…' : (couponApplied ? '✓' : $t('common.apply')) }}
                  </button>
                  <button
                    v-if="couponApplied"
                    type="button"
                    class="qo-coupon__clear"
                    :aria-label="$t('common.delete')"
                    @click="clearCoupon"
                  >×</button>
                </div>
                <p v-if="couponError" class="qo-required">{{ couponError }}</p>
                <p v-if="couponApplied && couponTargets.length" class="qo-coupon__scope">
                  {{ $t('checkout.couponOnlyOn', { products: couponTargets.join(', ') }) }}
                </p>
              </div>

              <div class="qo-field">
                <label class="label">{{ $t('quickOrder.note') }} <span class="qo-optional">({{ $t('quickOrder.noteOptional') }})</span></label>
                <input v-model="form.note" type="text" class="input" :placeholder="$t('quickOrder.notePlaceholder')" />
              </div>

              <p v-if="error" class="qo-error">{{ error }}</p>
            </form>

            <!--
              Sortie de secours vers le panier classique. Elle occupait une
              ligne entière du pied collant, en permanence, pour un usage rare :
              elle vit maintenant à la fin du formulaire, là où on la cherche.
            -->
            <RouterLink :to="{ name: 'checkout' }" class="qo-checkout-link" @click="$emit('close')">
              {{ $t('quickOrder.viewCart') }} →
            </RouterLink>
          </template>

        </div>
        <!-- fin .qo-body -->

        <!--
          Pied collant : montant à gauche, action à droite, sur une seule
          ligne. Le montant est une information, pas une commande — lui donner
          toute la largeur revenait à voler une ligne au formulaire, sur
          l'écran où il y en a le moins.
        -->
        <div v-if="!confirmed" class="qo-footer">
          <!--
            Détail plutôt qu'un total sec : hors Abidjan, la cliente réglait par
            Wave un montant qu'elle n'avait jamais vu, la livraison n'étant
            ajoutée que par le serveur au moment de créer la commande.
          -->
          <div class="qo-total qo-total--lignes">
            <div class="qo-total__ligne">
              <span>{{ $t('quickOrder.itemsLine') }}</span>
              <span>{{ fmtPrice(cartTotal) }}</span>
            </div>
            <div v-if="couponAmount > 0" class="qo-total__ligne qo-total__ligne--remise">
              <span>{{ $t('common.discount') }}</span>
              <span>−{{ fmtPrice(couponAmount) }}</span>
            </div>
            <div class="qo-total__ligne">
              <span>{{ $t('common.shipping') }}</span>
              <span :class="{ 'qo-total__attente': shippingPending }">{{ shippingLabel }}</span>
            </div>
          </div>

          <!--
            Total et action sur leur propre rangée. Les aligner à côté du détail
            mettait trois blocs sur une seule ligne : le bouton finissait rogné,
            donc inatteignable — le défaut le plus coûteux sur un tunnel d'achat.
          -->
          <div class="qo-footer__action">
            <div class="qo-total qo-total--final">
              <span>Total</span>
              <strong>{{ fmtPrice(Math.max(0, cartTotal - couponAmount) + shippingCost) }}</strong>
            </div>
            <button
              type="submit"
              form="qo-form"
              class="btn btn-primary qo-submit"
              :disabled="submitting || !cartItems.length"
            >
              <span v-if="submitting" class="qo-spinner"></span>
              <span v-else>{{ $t('quickOrder.submit') }}</span>
            </button>
          </div>
        </div>

      </div>
    </div>

    <!-- Commande semblable déjà en cours : on demande avant de doubler -->
    <DuplicateOrderDialog
      v-if="duplicate"
      :order="duplicate"
      :busy="submitting"
      @confirm="submit(true)"
      @cancel="cancelDuplicate"
    />
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { RouterLink } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useCurrencyStore } from '@/stores/currency'
import { useRouter } from 'vue-router'
import api from '@/api'
import DuplicateOrderDialog from '@/features/checkout/DuplicateOrderDialog.vue'
import { useCartStore } from '@/features/cart/cart.store'
import { isAbidjan } from '@/data/cities-ci'
import { filtrerDestinations } from '@/data/destinations-ci'
import { communesAbidjan, chargerCommunesAbidjan } from '@/data/abidjan-communes.js'
import { useAgentRedirect } from '@/composables/useAgentRedirect'
import { useAuthStore } from '@/features/auth/auth.store'
import { useSettingsStore } from '@/stores/settings'
import PhoneInput from '@/components/ui/PhoneInput.vue'

const emit   = defineEmits(['close'])
const router = useRouter()

const { t } = useI18n()

const cartStore     = useCartStore()
const authStore     = useAuthStore()
const settingsStore = useSettingsStore()

const cartItems  = computed(() => cartStore.items)
const cartTotal  = computed(() => cartStore.total)
const waveNumber = computed(() => settingsStore.paymentMobileNumber)

// Déclaré tôt : de nombreux computeds/watchers dépendent de `form`
const prefilled = ref({ name: false, phone: false, email: false })
const form = ref({
  name: '',
  phone: '',
  email: '',
  commune: '',
  communeManuel: '',
  city: '',
  // Commune retenue à l'intérieur du pays : c'est elle qui fait tomber le
  // devis sur la bonne zone quand la ville seule ne suffit pas.
  cityCommune: '',
  country: 'CI',
  indication: '',
  payment: 'wave',
  note: '',
})

// Destination dont les frais ne sont pas tarifés d'avance : la commande se
// finalise avec un agent. `destination` est le choix fait plus haut dans le
// formulaire — abidjan | interior | international.
const needsAgent = computed(() => destination.value !== 'abidjan')

// Bascule vers WhatsApp après confirmation sur ces destinations : la commande
// n'est pas finie tant qu'un agent n'a pas chiffré le transport.
const agentRedirect = useAgentRedirect(5)

const adminWhatsappLink = computed(() => {
  const order = confirmedOrder.value
  if (!order) return null
  const adminNumber = settingsStore.whatsappNumber
  if (!adminNumber) return null

  const phone = form.value.phone
  const methodMap = {
    wave:         t('drawer.wave'),
    orange_money: t('drawer.orangeMoney'),
    delivery:     t('checkout.onDelivery'),
  }
  const method = methodMap[form.value.payment] ?? form.value.payment

  const msg = [
    `🌹 ${t('quickOrder.waNewOrder')}`,
    `${t('quickOrder.waNumber')}: ${order.number}`,
    `${t('quickOrder.waCustomer')}: ${form.value.name} (${phone})`,
    showCityField.value ? `${t('fields.city')}: ${form.value.city.trim()} (${t('quickOrder.waOutsideAbidjan', { zone: settingsStore.homeCity })})` : null,
    `${t('fields.commune')}: ${effectiveCommune.value}`,
    form.value.indication?.trim() ? `📍 ${t('quickOrder.indication')}: ${form.value.indication.trim()}` : null,
    needsAgent.value ? null : `${t('common.payment')}: ${method}`,
    needsAgent.value
      ? `${t('common.subtotal')}: ${fmtPrice(order.subtotal ?? order.total)}`
      : `${t('common.total')}: ${fmtPrice(order.total)}`,
    needsAgent.value ? `
📦 ${t('quickOrder.waAskFees')}` : null,
    isWavePayment.value ? `\n📸 ${t('quickOrder.waSendingProof')}` : null,
    form.value.note ? `📝 ${t('quickOrder.note')}: ${form.value.note}` : null,
    ``,
    `${t('quickOrder.waReplyTo')}: ${phone}`,
  ].filter(l => l !== null).join('\n')

  const clean = adminNumber.replace(/\D/g, '')
  return `https://wa.me/${clean}?text=${encodeURIComponent(msg)}`
})

/**
 * Communes et quartiers d'Abidjan proposés au choix.
 *
 * Alimentés par les zones de livraison de l'administration, pas par une liste
 * figée : le district compte des quartiers desservis qui ne sont pas des
 * communes officielles — Abatta, Faya, Gonzagueville, Riviera, N'Dotré. La
 * liste en dur les ignorait, alors qu'ils sont bien tarifés en admin. Ajouter
 * une zone suffit désormais à la proposer à la cliente.
 *
 * Repli sur les treize communes officielles si l'appel échoue : mieux vaut un
 * choix incomplet qu'un sélecteur vide.
 */
const COMMUNES = communesAbidjan

// ── Combobox commune ──────────────────────────────────────────────────────────
const communeOpen        = ref(false)
const communeSearch      = ref('')
const communeSearchInput = ref(null)

const filteredCommunes = computed(() => {
  const q = communeSearch.value.trim().toLowerCase()
  if (!q) return COMMUNES.value
  return COMMUNES.value.filter(c => c.toLowerCase().includes(q))
})

const communeDisplay = computed(() => {
  if (!form.value.commune) return ''
  if (form.value.commune === '__autre__') return form.value.communeManuel || 'Saisie manuelle'
  return form.value.commune
})

const effectiveCommune = computed(() =>
  form.value.commune === '__autre__' ? form.value.communeManuel : form.value.commune
)

// ── Détection hors zone locale (commande rapide) ────────────────────────────
// La zone locale est la ville réglée par la boutique (défaut Abidjan). Sa
// sentinelle « Hors <ville> » est aussi la commune envoyée au serveur pour
// une livraison dans le reste du pays.
const HORS_ZONE = computed(() => `Hors ${settingsStore.homeCity}`)
const escapeRe = (s) => String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const isAbidjanQuick = computed(() => {
  const c = form.value.commune
  if (!c) return true // rien choisi encore → on n'alarme pas
  if (c === HORS_ZONE.value) return false
  const val = (effectiveCommune.value || '').trim()
  if (new RegExp(`hors\\s*${escapeRe(settingsStore.homeCity)}`, 'i').test(val)) return false
  return isAbidjan(val, val)
})

// Champ Ville + bannière : affichés dès qu'on est hors Abidjan
const showCityField = computed(() => !!form.value.commune && !isAbidjanQuick.value)


/* ── Destination hors Abidjan ─────────────────────────────────────────────────
 *
 * La liste fusionne le découpage administratif et nos zones tarifées : voir
 * destinations-ci.js. La commune retenue compte autant que la ville — c'est
 * elle qui porte souvent le tarif, et jusqu'ici la commande partait avec un
 * « Hors Abidjan » qui ne pouvait correspondre à aucune zone.
 */
const cityOpen        = ref(false)
const citySearch      = ref('')
const citySearchInput = ref(null)

const filteredCities = computed(() => filtrerDestinations(citySearch.value))

/** Tarif à afficher pour une ligne : celui de la commune trouvée s'il existe. */
function destPrice(d) {
  return d.matchedCommune?.price ?? d.price
}

async function openCity() {
  cityOpen.value   = true
  citySearch.value = ''
  await nextTick()
  citySearchInput.value?.focus()
}
function closeCity() { cityOpen.value = false }

function selectCity(d) {
  form.value.city = d.city
  // « Sikensi » cherché dans le champ ville vaut Dabou + Sikensi : la cliente a
  // déjà donné l'information, on ne la lui redemande pas — et c'est cette
  // commune qui permet au devis de tomber sur la bonne zone.
  form.value.cityCommune = d.matchedCommune?.name
    ?? (d.communes.length === 1 ? d.communes[0].name : '')
  cityOpen.value = false
}

// Entrée : sélectionne le premier résultat, comme pour les communes d'Abidjan.
function onCityEnter() {
  if (filteredCities.value.length) selectCity(filteredCities.value[0])
}


async function openCommune() {
  communeOpen.value  = true
  communeSearch.value = ''
  await nextTick()
  communeSearchInput.value?.focus()
}
function closeCommune() { communeOpen.value = false }
function selectCommune(c) {
  form.value.commune = c
  if (c !== '__autre__') form.value.communeManuel = ''
  communeOpen.value = false
}

// Entrée : sélectionne le 1er résultat, sinon bascule en saisie manuelle pré-remplie
function onCommuneEnter() {
  if (filteredCommunes.value.length) {
    selectCommune(filteredCommunes.value[0])
  } else if (communeSearch.value.trim()) {
    form.value.communeManuel = communeSearch.value.trim()
    selectCommune('__autre__')
  }
}

// Directive v-click-outside (inline)
const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value(e) }
    document.addEventListener('mousedown', el._clickOutside)
  },
  unmounted(el) { document.removeEventListener('mousedown', el._clickOutside) },
}

// ── Géolocalisation (commande rapide) ────────────────────────────────────────
const ICON_MOBILE = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M12 18h.01"/></svg>'
const ICON_TRUCK  = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>'

const ICON_CITY = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l5-4v18"/><path d="M19 21V11l-9-4"/><path d="M9 9v.01M9 12v.01M9 15v.01M9 18v.01"/></svg>'

const ICON_GLOBE = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18a15 15 0 0 1 0-18z"/></svg>'

const DESTINATIONS = computed(() => [
  { value: 'abidjan',       label: t('quickOrder.destAbidjan', { zone: settingsStore.homeCity }),       icon: ICON_CITY },
  { value: 'interior',      label: t('quickOrder.destInterior', { zone: settingsStore.homeCity }),      icon: ICON_TRUCK },
  { value: 'international', label: t('quickOrder.destInternational'), icon: ICON_GLOBE },
])

/** Pays desservis hors Côte d'Ivoire, repris de la liste du champ téléphone. */
const INTERNATIONAL_CODES = ['SN', 'ML', 'BF', 'GN', 'TG', 'BJ', 'GH', 'NG', 'CM', 'MA', 'FR', 'BE', 'CH', 'CA', 'US']
const PAYS_INTERNATIONAUX = computed(() =>
  INTERNATIONAL_CODES.map(code => ({ code, nom: t(`countryNames.${code}`) }))
)

/*
 * Destination et commune restent synchronisées dans les deux sens : le bouton
 * écrit la commune, et la géolocalisation écrit `form.commune` directement —
 * une position détectée à Bouaké doit donc allumer « Hors Abidjan » sans que
 * personne n'y touche.
 */
const destination = ref('abidjan')

const estInternational = computed(() => destination.value === 'international')

/* ── Frais de livraison ───────────────────────────────────────────────────────
 *
 * Même source que le tunnel classique : le tarif vient de la zone, par
 * /shipping/quote. Rien n'est gratuit dans le système. Une zone non reconnue,
 * un tarif au kilo ou une destination internationale laissent le montant à la
 * charge des agents — et il ne doit alors surtout pas être compté comme zéro
 * dans le total affiché, sous peine d'annoncer un prix qu'on ne tiendra pas.
 */
const shippingQuote = ref(null)

const shippingFound   = computed(() => !!shippingQuote.value && !shippingQuote.value.not_found)
const shippingPerKg   = computed(() => shippingFound.value && shippingQuote.value.unit === 'per_kg')

const shippingPending = computed(() =>
  destination.value === 'international'
  || shippingPerKg.value
  || shippingQuote.value?.not_found === true
)

const shippingCost = computed(() =>
  shippingFound.value && !shippingPerKg.value ? Number(shippingQuote.value.price) || 0 : 0
)

const shippingLabel = computed(() => {
  if (destination.value === 'international') return t('quickOrder.shippingWithAgent')
  if (!effectiveCommune.value) return t('quickOrder.shippingByDestination')
  if (showCityField.value && !form.value.city) return t('quickOrder.shippingChooseCity')
  if (shippingFound.value && !shippingPerKg.value) return fmtPrice(shippingCost.value)
  if (shippingPending.value) return t('drawer.shippingByAgents')
  return '…'
})

let quoteTimer = null

async function refreshShippingQuote() {
  // L'international ne se tarife pas à la zone : inutile d'interroger le serveur.
  if (destination.value === 'international') { shippingQuote.value = null; return }

  const commune = effectiveCommune.value
  if (!commune) { shippingQuote.value = null; return }
  if (showCityField.value && !form.value.city) { shippingQuote.value = null; return }

  try {
    const { data } = await api.get('/shipping/quote', {
      params: {
        city:     showCityField.value ? form.value.city : settingsStore.homeCity,
        // Hors Abidjan, `commune` vaut « Hors Abidjan » : une étiquette, pas un
        // lieu, qui ne pouvait correspondre à aucune zone. La commune choisie
        // dans la liste la remplace dès qu'elle existe.
        commune:  showCityField.value ? (form.value.cityCommune || commune) : commune,
        country:  'CI',
        subtotal: cartStore.subtotal,
      },
    })
    shippingQuote.value = data?.found ? data : { not_found: true }
  } catch (e) {
    shippingQuote.value = e.response?.status === 404 ? { not_found: true } : null
  }
}

watch(
  () => [effectiveCommune.value, form.value.city, form.value.cityCommune, destination.value, cartStore.subtotal],
  () => {
    clearTimeout(quoteTimer)
    quoteTimer = setTimeout(refreshShippingQuote, 250)
  },
  { immediate: true },
)

watch(destination, (d) => {
  if (d === 'interior') {
    form.value.commune = HORS_ZONE.value
    form.value.communeManuel = ''
    form.value.country = 'CI'
    return
  }
  if (d === 'international') {
    form.value.commune = 'International'
    form.value.communeManuel = ''
    form.value.city = ''
    form.value.cityCommune = ''
    form.value.country = ''
    return
  }
  // Retour sur Abidjan : on efface ce que les deux autres avaient posé.
  if ([HORS_ZONE.value, 'International'].includes(form.value.commune)) {
    form.value.commune = ''
    form.value.city = ''
    form.value.cityCommune = ''
  }
  form.value.country = 'CI'
})

watch(() => form.value.commune, (c) => {
  if (c === HORS_ZONE.value) destination.value = 'interior'
  else if (c === 'International') destination.value = 'international'
  else if (c) destination.value = 'abidjan'
})

/**
 * Moyens de paiement selon la destination — miroir de PaymentAvailability,
 * que le serveur applique de son côté (QuickOrderController).
 *   Abidjan            → paiement à la livraison uniquement
 *   Intérieur de la CI → prépaiement Wave ou Orange Money
 *   International      → aucun : frais, délais et douane se négocient au cas
 *                        par cas, la commande se finalise avec un agent.
 */
const paymentMethods = computed(() => {
  if (destination.value === 'international') return []

  if (isAbidjanQuick.value) {
    return [{ value: 'delivery', label: t('quickOrder.payDelivery'), icon: ICON_TRUCK }]
  }

  return [
    { value: 'wave',         label: t('quickOrder.payWave'),        icon: ICON_MOBILE },
    { value: 'orange_money', label: t('quickOrder.payOrangeMoney'), icon: ICON_MOBILE },
  ]
})

// La destination change → un moyen devenu invalide ne doit pas rester coché.
watch(paymentMethods, (methods) => {
  if (!methods.some(m => m.value === form.value.payment)) {
    form.value.payment = methods[0]?.value ?? ''
  }
}, { immediate: true })

// Pré-remplir depuis le profil si l'utilisateur est connecté
onMounted(() => {
  chargerCommunesAbidjan()

  const u = authStore.user
  if (!u) return
  if (u.name) { form.value.name = u.name; prefilled.value.name = true }
  if (u.phone) { form.value.phone = u.phone; prefilled.value.phone = true }
  if (u.email && !u.is_generated_email) { form.value.email = u.email; prefilled.value.email = true }
})

/* ── Code promo ───────────────────────────────────────────────────────────
   Le montant vient du serveur, jamais d'un calcul local : un code ciblé ne
   remise que certains articles, et un article déjà en promotion en est exclu.
   Le total affiché doit être celui qui sera facturé. */
const couponCode     = ref('')
const couponApplied  = ref(false)
const couponAmount   = ref(0)
const couponProducts = ref([])
const couponError    = ref('')
const couponLoading  = ref(false)

const couponTargets = computed(() => couponProducts.value.map(p => p.name))

async function applyCoupon() {
  const code = couponCode.value.trim()
  if (!code) return

  couponLoading.value = true
  couponError.value   = ''
  try {
    const { data } = await api.post('/coupons/validate', {
      code,
      items: cartItems.value.map(i => ({
        product_id: i.product_id,
        variant_id: i.variant_id ?? null,
        quantity:   i.quantity,
      })),
    })
    couponApplied.value  = true
    couponAmount.value   = Number(data.discount_amount ?? 0)
    couponProducts.value = data.products ?? []
  } catch (e) {
    couponError.value = e.response?.data?.message ?? t('checkout.couponInvalid')
  } finally {
    couponLoading.value = false
  }
}

function clearCoupon() {
  couponCode.value     = ''
  couponApplied.value  = false
  couponAmount.value   = 0
  couponProducts.value = []
  couponError.value    = ''
}

/* Le panier change → l'assiette du code aussi. Le remettre à zéro vaut mieux
   que d'afficher une remise calculée sur un panier qui n'existe plus. */
watch(cartItems, () => {
  if (couponApplied.value) clearCoupon()
}, { deep: true })

const submitting     = ref(false)
const error          = ref('')
// Commande semblable déjà en cours, montrée avant d'en créer une seconde.
const duplicate      = ref(null)

/**
 * La cliente renonce : le formulaire garde le message sous les yeux.
 *
 * Sans lui, refermer la fenêtre laissait un écran muet — exactement ce qui
 * s'était produit quand la fenêtre passait derrière la modale.
 */
function cancelDuplicate() {
  const numero = duplicate.value?.number
  duplicate.value = null
  error.value = numero
    ? t('duplicate.pending', { number: numero })
    : t('duplicate.title')
}

/*
 * Validité du téléphone rapportée par le champ. Bloquante en Côte d'Ivoire,
 * où la règle est certaine et où le livreur appelle à la porte ; ailleurs la
 * cliente est avertie sous le champ, sans être empêchée de commander.
 */
const phoneValidity  = ref({ valid: true, strict: true })
const phoneBlocked   = computed(() => !phoneValidity.value.valid && phoneValidity.value.strict)
const confirmed      = ref(false)
const confirmedOrder = ref(null)

// ── Facture téléchargeable ────────────────────────────────────────────────────
const downloadingInvoice = ref(false)
const invoiceError       = ref('')

/**
 * Récupère le PDF et le remet au visiteur.
 *
 * Même endpoint que le détail de commande côté client : la facture est
 * identique des deux côtés, et un second générateur finirait par diverger.
 * L'échec est dit à voix haute — la page de confirmation ne revient jamais,
 * et un téléchargement raté en silence perdrait la facture pour de bon.
 */
async function downloadInvoice() {
  const numero = confirmedOrder.value?.number
  if (!numero) return

  downloadingInvoice.value = true
  invoiceError.value = ''

  try {
    const response = await api.get(`/orders/${numero}/invoice`, { responseType: 'blob' })

    const url  = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href     = url
    link.download = `facture-${numero}.pdf`
    document.body.appendChild(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch {
    invoiceError.value = t('quickOrder.invoiceError')
  } finally {
    downloadingInvoice.value = false
  }
}

// ── Validation des champs requis ──────────────────────────────────────────────
//
// Faite ici plutôt que par l'attribut `required` du navigateur : la commune est
// un combobox, donc un `<div>`, que la validation native ne sait pas contrôler.
// Le contournement précédent — un `<input required>` en `display:none` — rendait
// le formulaire insoumettable *et* muet, puisqu'un champ masqué ne peut pas
// recevoir le focus ni afficher son message.

const formEl      = ref(null)
const fieldErrors = ref({})

/** Champs requis, dans l'ordre d'affichage : le premier en défaut prend le focus. */
function requiredFields() {
  return [
    { key: 'name',    ok: () => !!form.value.name.trim() },
    { key: 'phone',   ok: () => !!form.value.phone.trim() },
    { key: 'commune', ok: () => !!effectiveCommune.value.trim() },
    // Le pays n'est demandé qu'à l'international, la ville dès qu'on sort d'Abidjan.
    { key: 'country', ok: () => !estInternational.value || !!form.value.country },
    { key: 'city',    ok: () => !showCityField.value || !!form.value.city.trim() },
    // À l'international il n'y a rien à choisir : ne pas l'exiger.
    { key: 'payment', ok: () => !paymentMethods.value.length || !!form.value.payment },
  ]
}

/**
 * Amène le champ fautif sous les yeux, puis dans le focus.
 *
 * La modal défile : un champ signalé en rouge hors de l'écran ne vaut pas mieux
 * qu'un silence. Le combobox n'étant pas un champ natif, on ouvre sa liste dans
 * la foulée — c'est le geste que la cliente allait faire de toute façon.
 */
function focusField(key) {
  const root = formEl.value?.querySelector(`[data-field="${key}"]`)
  if (!root) return

  root.scrollIntoView({ block: 'center', behavior: 'smooth' })

  if (key === 'commune') {
    const manuel = root.querySelector('.qo-commune-manuel')
    if (manuel) { manuel.focus(); return }
    root.querySelector('.qo-combobox__trigger')?.focus()
    openCommune()
    return
  }

  root.querySelector('input, textarea, select')?.focus()
}

/** Renseigne `fieldErrors` et renvoie la clé du premier champ manquant. */
function validateRequired() {
  const manquants = requiredFields().filter(f => !f.ok())

  fieldErrors.value = Object.fromEntries(manquants.map(f => [f.key, true]))

  return manquants[0]?.key ?? null
}

// Un champ corrigé perd son signalement immédiatement : garder le rouge pendant
// que la cliente tape donnerait l'impression que sa saisie n'est pas prise.
watch(form, () => {
  if (!Object.keys(fieldErrors.value).length) return

  const encoreEnDefaut = requiredFields().filter(f => !f.ok()).map(f => f.key)
  fieldErrors.value = Object.fromEntries(encoreEnDefaut.map(k => [k, true]))

  if (!encoreEnDefaut.length && error.value) error.value = ''
}, { deep: true })

// ── Nudge complétion profil (post-commande) ───────────────────────────────
const nudgeForm    = ref({ email: '', password: '', phone: '' })
const nudgeSaving  = ref(false)
const nudgeError   = ref('')
const nudgeSuccess = ref('')
const nudgeDone    = ref(false)

const profileNudge = computed(() => {
  if (!confirmed.value || nudgeDone.value) return null
  const u = authStore.user
  if (!u) return null
  if (u.is_generated_email) {
    return {
      type:  'setup',
      title: t('profile.secureBannerTitle'),
      desc:  t('quickOrder.nudgeSetupDesc'),
    }
  }
  if (!u.phone) {
    return {
      type:  'phone',
      title: t('quickOrder.nudgePhoneTitle'),
      desc:  t('quickOrder.nudgePhoneDesc'),
    }
  }
  return null
})

async function doSetup() {
  nudgeSaving.value = true
  nudgeError.value  = ''
  try {
    await authStore.setupAccount({
      email:                 nudgeForm.value.email,
      password:              nudgeForm.value.password,
      password_confirmation: nudgeForm.value.password,
    })
    nudgeSuccess.value = t('profile.accountSecured')
    nudgeDone.value = true
  } catch (e) {
    const errs = e.response?.data?.errors
    nudgeError.value = errs ? Object.values(errs).flat()[0] : (e.response?.data?.message ?? t('common.error'))
  } finally {
    nudgeSaving.value = false
  }
}

async function doSavePhone() {
  if (!nudgeForm.value.phone) return
  nudgeSaving.value = true
  nudgeError.value  = ''
  try {
    await authStore.updateInfo({ phone: nudgeForm.value.phone })
    nudgeSuccess.value = t('quickOrder.phoneSaved')
    nudgeDone.value = true
  } catch (e) {
    nudgeError.value = e.response?.data?.message ?? t('common.error')
  } finally {
    nudgeSaving.value = false
  }
}

const isWavePayment = computed(() =>
  confirmedOrder.value && (confirmedOrder.value?.payments?.[0]?.provider === 'wave' || form.value.payment === 'wave')
)

// Lien « Payer avec Wave » : lien marchand + montant en paramètre
const wavePayUrl = computed(() => {
  const base = (settingsStore.paymentWaveLink || '').trim()
  if (!base || !isWavePayment.value || !confirmedOrder.value) return null
  const amount = Math.round(Number(confirmedOrder.value.total) || 0)
  if (!amount) return base
  const sep = base.includes('?')
    ? (base.endsWith('?') || base.endsWith('&') ? '' : '&')
    : '?'
  return `${base}${sep}amount=${amount}`
})

async function submit(confirmDuplicate = false) {
  // Un `return` nu ici laissait la cliente devant un bouton sans effet.
  const premierManquant = validateRequired()
  if (premierManquant) {
    error.value = t('quickOrder.requiredSummary')
    await nextTick()
    focusField(premierManquant)
    return
  }

  if (phoneBlocked.value) {
    error.value = t('phone.invalidCI')
    await nextTick()
    focusField('phone')
    return
  }

  submitting.value = true
  error.value = ''
  duplicate.value = null

  const items = cartItems.value.map(i => ({
    product_id: i.product_id,
    variant_id: i.variant_id ?? null,
    quantity:   i.quantity,
  }))

  if (!items.length) {
    error.value = t('quickOrder.emptyCart')
    submitting.value = false
    return
  }

  try {
    const { data } = await api.post('/quick-order', {
      name:           form.value.name,
      phone:          form.value.phone,
      email:          form.value.email?.trim() || null,
      commune:        showCityField.value
        ? (form.value.cityCommune || effectiveCommune.value)
        : effectiveCommune.value,
      country:        estInternational.value ? form.value.country : 'CI',
      city:           isAbidjanQuick.value ? settingsStore.homeCity : (form.value.city.trim() || null),
      landmark:       form.value.indication?.trim() || null,
      // À l'international, aucun règlement n'est choisi ici : c'est l'agent qui
      // le fixe après avoir arrêté les frais et la douane avec la cliente.
      payment_method: estInternational.value ? null : form.value.payment,
      note:           form.value.note || null,
      // Le code part, pas le montant : le serveur le rechiffre sur les lignes
      // réelles. Un code devenu inapplicable est ignoré, sans perdre la vente.
      coupon_code:    couponApplied.value ? couponCode.value.trim() : null,
      // Réponse à l'avertissement « vous avez déjà une commande semblable ».
      // Elle voyage avec la demande : le serveur ne retient rien entre les
      // deux appels, donc un rechargement ne peut pas doubler en silence.
      // Comparaison stricte : seul un vrai booléen confirme, jamais un objet
      // qu'un gestionnaire d'événement aurait glissé là.
      confirm_duplicate: confirmDuplicate === true,
      items,
    })

    if (data.access_token) {
      localStorage.setItem('auth_token', data.access_token)
      await authStore.fetchUser()
    }

    cartStore.clear()

    if (data.payment_url) {
      window.location.href = data.payment_url
      return
    }

    confirmedOrder.value = data
    confirmed.value = true

    // Le compte à rebours part une fois la confirmation affichée : la cliente
    // voit son numéro de commande avant que WhatsApp ne prenne la main.
    if (needsAgent.value) {
      nextTick(() => agentRedirect.start(() => adminWhatsappLink.value))
    }

  } catch (e) {
    // 409 : une commande semblable est encore en cours. Rien n'a été écrit —
    // on la montre, et la cliente décide.
    if (e.response?.status === 409 && e.response.data?.duplicate_order) {
      duplicate.value = e.response.data.duplicate_order
      return
    }

    error.value = e.response?.data?.message ?? t('quickOrder.errorOccurred')
  } finally {
    submitting.value = false
  }
}

function viewOrder() {
  emit('close')
  router.push({ name: 'order', params: { number: confirmedOrder.value?.number } })
}

function goToProfile() {
  emit('close')
  router.push({ name: 'profile' })
}

// ── Récapitulatif modifiable ─────────────────────────────────────────────────
//
// Le panier reste la source unique : on ne tient pas de copie locale des
// quantités, sinon la modale et le tiroir finiraient par afficher deux totaux
// différents pour le même panier.

const ligneEnCours = ref(null)
const erreurLigne  = ref('')

function ligneTotal(item) {
  return Number(item.unit_price ?? item.price ?? 0) * item.quantity
}

async function changerQuantite(item, delta) {
  if (ligneEnCours.value) return

  const nouvelle = item.quantity + delta

  // Descendre à zéro, c'est retirer l'article : on le dit plutôt que de le
  // faire disparaître sur un clic de trop.
  if (nouvelle <= 0) {
    await retirerLigne(item)
    return
  }

  ligneEnCours.value = item.id
  erreurLigne.value  = ''
  try {
    await cartStore.update(item.id, nouvelle)
  } catch (e) {
    // Stock insuffisant, article désactivé… le message vient du serveur.
    erreurLigne.value = e.response?.data?.message ?? t('quickOrder.qtyError')
  } finally {
    ligneEnCours.value = null
  }
}

async function retirerLigne(item) {
  if (ligneEnCours.value) return

  ligneEnCours.value = item.id
  erreurLigne.value  = ''
  try {
    await cartStore.remove(item.id)
  } catch (e) {
    erreurLigne.value = e.response?.data?.message ?? t('quickOrder.qtyError')
  } finally {
    ligneEnCours.value = null
  }
}

function fmtPrice(val) {
  return useCurrencyStore().format(val ?? 0)
}
</script>

<style scoped>
/* ── Overlay ── */
.qo-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  backdrop-filter: blur(4px);
  z-index: 1100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
}

/* ── Card : flex column, overflow hidden ── */
.qo-card {
  background: #fff;
  border-radius: var(--radius-2xl, 20px);
  width: 100%;
  max-width: 440px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  position: relative;
  box-shadow: 0 24px 64px rgba(0,0,0,.18);
}

/* ── Zone scrollable : tout sauf le footer ── */
.qo-body {
  flex: 1;
  overflow-y: auto;
  -webkit-overflow-scrolling: touch;
  padding: var(--space-7) var(--space-6) var(--space-4);
}

/* ── Footer collé en bas : total + bouton ── */
/*
 * Deux rangées : le détail des montants au-dessus, le total et l'action en
 * dessous. Tout mettre sur une seule ligne rognait le bouton dès que la ligne
 * « Livraison » portait autre chose qu'un montant court.
 */
.qo-footer {
  flex-shrink: 0;
  padding: var(--space-3) var(--space-6);
  border-top: 1px solid var(--cream-200);
  background: #fff;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  /* Ombre vers le haut pour signaler que ça scrolle */
  box-shadow: 0 -6px 16px rgba(0,0,0,.06);
}

.qo-footer__action {
  display: flex;
  align-items: center;
  gap: var(--space-4);
}

/* Le total prend la place restante, le bouton garde la sienne. */
.qo-total--final {
  flex: 1 1 auto;
  min-width: 0;
}

/* ── Bouton fermer ── */
.qo-close {
  position: absolute;
  top: var(--space-4);
  right: var(--space-4);
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--cream-100);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--gray-400);
  transition: all var(--transition-fast);
  z-index: 2;
}
.qo-close:hover { background: var(--rose-100); color: var(--rose-500); }

/* ── Header ── */
.qo-header { margin-bottom: var(--space-4); }
.qo-title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  color: var(--gray-800);
  margin: var(--space-1) 0 var(--space-2);
}
.qo-title em { font-style: italic; color: var(--rose-500); }
.qo-subtitle { font-size: 0.875rem; color: var(--gray-400); }

/* ── Frais à convenir avec un agent ── */
.qo-agent__redirect {
  margin-top: 10px;
  padding-top: 10px;
  border-top: 1px solid #bbf7d0;
  font-size: 0.75rem;
  color: #15803d;
  line-height: 1.5;
}
.qo-agent__stay {
  display: block;
  margin-top: 4px;
  border: none;
  background: none;
  padding: 0;
  font-size: 0.75rem;
  color: #6b7280;
  text-decoration: underline;
  cursor: pointer;
}

.qo-agent {
  margin: 0 0 var(--space-4);
  padding: 14px 16px;
  border-radius: var(--radius-lg, 14px);
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  text-align: left;
}
.qo-agent__title {
  font-size: 0.9375rem;
  font-weight: 700;
  color: #15803d;
  margin-bottom: 4px;
}
.qo-agent__desc {
  font-size: 0.8125rem;
  line-height: 1.55;
  color: #4b5563;
  margin-bottom: 10px;
}
.qo-agent__subtotal {
  font-size: 0.875rem;
  color: #374151;
}
.qo-agent__subtotal span {
  display: block;
  margin-top: 2px;
  font-size: 0.75rem;
  color: #9ca3af;
}

/* Dans ce cas, WhatsApp n'est pas une option : c'est la suite du parcours. */
.qo-wa-btn--primary {
  font-size: 0.9375rem;
  padding-top: 13px;
  padding-bottom: 13px;
}

/* ── Récapitulatif modifiable ──
   Une ligne par article, compacte : la modale contient déjà un formulaire
   complet, le récapitulatif ne doit pas repousser « Commander » hors écran. */
.qo-items {
  list-style: none;
  margin: 0 0 var(--space-4);
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.qo-item {
  display: grid;
  grid-template-columns: 1fr auto auto auto;
  align-items: center;
  gap: var(--space-3);
  padding: 7px 0;
  border-bottom: 1px solid var(--rose-50);
}

.qo-item:last-child { border-bottom: none; }

.qo-item__name {
  font-size: 0.8125rem;
  color: var(--gray-700, #3f3a37);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.qo-item__qty {
  display: flex;
  align-items: center;
  gap: 2px;
}

.qo-qty-btn {
  width: 22px;
  height: 22px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--rose-100);
  background: #fff;
  color: var(--rose-600);
  border-radius: var(--radius-full);
  font-size: 0.875rem;
  line-height: 1;
  cursor: pointer;
  transition: background .15s ease;
}

.qo-qty-btn:hover:not(:disabled) { background: var(--rose-50); }
.qo-qty-btn--plus { background: var(--rose-50); }
.qo-qty-btn:disabled { opacity: .45; cursor: default; }

.qo-qty-value {
  min-width: 20px;
  text-align: center;
  font-size: 0.8125rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

.qo-item__total {
  font-size: 0.8125rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.qo-item__remove {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border: none;
  background: transparent;
  color: var(--gray-400);
  border-radius: var(--radius-full);
  cursor: pointer;
  transition: background .15s ease, color .15s ease;
}

.qo-item__remove:hover:not(:disabled) { background: #fee2e2; color: #ef4444; }
.qo-item__remove:disabled { opacity: .45; cursor: default; }

.qo-items-error {
  margin: 0 0 var(--space-4);
  padding: 8px 10px;
  border-radius: var(--radius-md, 10px);
  background: #fee2e2;
  color: #b91c1c;
  font-size: 0.75rem;
}

.qo-items-empty {
  margin: 0 0 var(--space-4);
  font-size: 0.8125rem;
  color: var(--gray-400);
}

/* ── Formulaire ── */
.qo-form { display: flex; flex-direction: column; gap: var(--space-4); }
.qo-field { display: flex; flex-direction: column; gap: var(--space-1); }
.qo-optional { font-size: 0.75rem; color: var(--gray-400); font-weight: 400; }
.qo-indication { resize: none; min-height: 52px; line-height: 1.5; }

.qo-city-hint {
  margin-top: 5px;
  font-size: 0.75rem;
  color: var(--gray-400);
  line-height: 1.4;
}

/* Bannière hors Abidjan (commande rapide) */
.qo-outzone {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 12px 14px;
  border-radius: var(--radius-lg, 12px);
  background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
  border: 1.5px solid #fdba74;
}
.qo-outzone__icon {
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #fed7aa;
  color: #c2410c;
}
.qo-outzone strong {
  display: block;
  font-size: 0.875rem;
  font-weight: 700;
  color: #9a3412;
  margin-bottom: 2px;
}
.qo-outzone p {
  margin: 0;
  font-size: 0.78125rem;
  line-height: 1.45;
  color: #b45309;
}
.qo-outzone b { color: #9a3412; font-weight: 700; }

/* ── Combobox commune ── */
.qo-combobox { position: relative; }

.qo-combobox__trigger {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 10px 14px;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-md);
  background: #fff;
  cursor: pointer;
  font-size: 0.9375rem;
  transition: border-color var(--transition-fast);
  min-height: 44px;
}
.qo-combobox__trigger:hover { border-color: var(--gray-300); }

.qo-combobox__value { color: var(--gray-800); flex: 1; }
.qo-combobox__placeholder { color: var(--gray-400); flex: 1; font-size: 0.875rem; }

.qo-combobox__arrow {
  flex-shrink: 0;
  color: var(--gray-400);
  transition: transform 0.2s ease;
}
.qo-combobox__arrow--open { transform: rotate(180deg); }

.qo-combobox__dropdown {
  position: absolute;
  top: calc(100% + 4px);
  left: 0; right: 0;
  background: #fff;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-lg);
  box-shadow: 0 8px 24px rgba(0,0,0,.12);
  z-index: 50;
  overflow: hidden;
}

.qo-combobox__search {
  display: block;
  width: 100%;
  padding: 10px 14px;
  border: none;
  border-bottom: 1px solid var(--cream-200);
  font-size: 0.875rem;
  outline: none;
  background: var(--cream-50);
}
.qo-combobox__search::placeholder { color: var(--gray-400); }

.qo-combobox__list {
  list-style: none;
  max-height: 200px;
  overflow-y: auto;
  padding: var(--space-1) 0;
}

.qo-combobox__option {
  padding: 9px 14px;
  font-size: 0.875rem;
  cursor: pointer;
  color: var(--gray-700);
  transition: background var(--transition-fast);
  display: flex;
  align-items: center;
  gap: var(--space-2);
}
.qo-combobox__option:hover { background: var(--cream-50); }
.qo-combobox__option--active {
  background: var(--rose-50);
  color: var(--rose-600);
  font-weight: 500;
}
.qo-combobox__option--other {
  color: var(--gray-500);
  border-top: 1px solid var(--cream-100);
  margin-top: var(--space-1);
  font-style: italic;
}
.qo-combobox__option--other:hover { background: var(--cream-100); color: var(--gray-700); }

/* Destination hors Abidjan : nom à gauche, tarif à droite. Le prix figure dans
   la liste elle-même — le découvrir après avoir choisi sa ville, c'est devoir
   revenir en arrière pour comparer. */
.qo-combobox__option--dest { justify-content: space-between; }
.qo-dest-opt { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.qo-dest-opt__name { font-weight: 500; }
.qo-dest-opt__sub {
  font-size: 0.6875rem;
  color: var(--gray-400);
}
.qo-dest-opt__price {
  flex-shrink: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--rose-600);
  white-space: nowrap;
}
.qo-dest-opt__price--unknown {
  font-weight: 500;
  font-style: italic;
  color: var(--gray-400);
}
.qo-combobox__sub { font-size: 0.75rem; color: var(--gray-400); }

.qo-combobox__empty {
  padding: 10px 14px;
  font-size: 0.8125rem;
  color: var(--gray-400);
  text-align: center;
}

.qo-commune-manuel {
  margin-top: var(--space-2);
}

/* ── Géo dans QO ── */
.qo-field-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
}
/*
  Indicateur de chargement. Le nom vient de la géolocalisation, mais il sert
  aussi au bouton d'invitation et au téléchargement de facture — d'où son
  maintien alors que le bouton « Ma position » a déménagé.
*/
.qo-geo-spin {
  display: inline-block;
  width: 10px; height: 10px;
  border: 1.5px solid currentColor;
  border-top-color: transparent;
  border-radius: 50%;
  animation: qogeo-spin 0.65s linear infinite;
}
@keyframes qogeo-spin { to { transform: rotate(360deg); } }

.qo-geo-msg {
  font-size: 0.7rem;
  padding: 4px 8px;
  border-radius: var(--radius-sm);
  margin-top: 2px;
}
.qo-geo-msg--success { background: #dcfce7; color: #15803d; }
.qo-geo-msg--partial { background: #fef9c3; color: #854d0e; }
.qo-geo-msg--error   { background: #fef2f2; color: #dc2626; }

/* ── Paiement ── */
.qo-payments {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-2);
}
.qo-payment {
  border: 2px solid var(--cream-300);
  border-radius: var(--radius-md);
  padding: var(--space-3) var(--space-2);
  cursor: pointer;
  text-align: center;
  transition: all var(--transition-fast);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.qo-payment input { display: none; }
.qo-payment__icon { font-size: 1.5rem; }
.qo-payment__label { font-size: 0.7rem; font-weight: 500; color: var(--gray-600); }
.qo-payment--active { border-color: var(--rose-400); background: var(--rose-50); }
.qo-payment--active .qo-payment__label { color: var(--rose-600); }

/* ── Total (dans le footer) ── */
/*
 * Montant empilé sous son libellé : en colonne étroite, « Total » et le
 * chiffre côte à côte forçaient soit un retour à la ligne, soit une largeur
 * qui rognait le bouton. Le fond crème et le cadre disparaissent — dans un
 * pied déjà séparé par un filet et une ombre, ils ne délimitaient plus rien.
 */
.qo-total {
  display: flex;
  flex-direction: column;
  justify-content: center;
  flex: 0 0 auto;
  line-height: 1.15;
  color: var(--gray-500);
}
.qo-total > span {
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

/* ── Choix de destination ─────────────────────────────────────────────────── */
/*
 * Deux colonnes, et le troisième bouton occupe toute la largeur. Sur trois
 * colonnes, « Hors Abidjan » et « International » se coupent ; laissé seul dans
 * une grille à deux colonnes, le troisième a l'air d'un oubli. Étendu, il se lit
 * comme le cas particulier qu'il est.
 */
.qo-dest {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-2);
  margin-bottom: var(--space-2);
}
.qo-dest__btn:last-child:nth-child(odd) {
  grid-column: 1 / -1;
}
.qo-dest__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: 12px 10px;
  border: 1.5px solid var(--cream-300);
  border-radius: var(--radius-md);
  background: #fff;
  color: var(--gray-600);
  font-size: 0.9375rem;
  font-weight: 500;
  cursor: pointer;
  transition: all var(--transition-fast);
  /* Cible tactile confortable : c'est le premier geste du formulaire. */
  min-height: 48px;
}
.qo-dest__btn:hover { border-color: var(--rose-300); color: var(--rose-600); }
.qo-dest__btn--active {
  border-color: var(--rose-500);
  background: var(--rose-50);
  color: var(--rose-600);
  font-weight: 600;
  box-shadow: 0 0 0 3px rgba(var(--rose-500-rgb), 0.12);
}
.qo-dest__icon { display: flex; flex: none; }
.qo-dest__label { white-space: nowrap; }

@media (max-width: 380px) {
  /* Deux libellés côte à côte ne tiennent plus : on empile plutôt que de
     rogner un texte qui porte toute la décision. */
  .qo-dest { grid-template-columns: 1fr; }
  .qo-dest__btn { justify-content: flex-start; }
}

/* Détail articles / livraison, au-dessus du total */
.qo-total--lignes {
  flex-direction: column;
  align-items: stretch;
  gap: 2px;
  margin-bottom: 0;
  background: transparent;
  padding: 0 var(--space-1);
  font-size: 0.8125rem;
}
.qo-total__ligne {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}
.qo-total__ligne--remise > span { color: var(--rose-600); }

/* ── Code promo ── */
.qo-coupon { display: flex; align-items: center; gap: var(--space-2); }
.qo-coupon__input { flex: 1; min-width: 0; text-transform: uppercase; }
.qo-coupon__input:disabled { background: var(--cream-50); color: var(--gray-500); }
.qo-coupon__btn { flex: 0 0 auto; }
.qo-coupon__clear {
  flex: 0 0 auto;
  border: 0;
  background: none;
  color: var(--gray-400);
  font-size: 1.125rem;
  line-height: 1;
  cursor: pointer;
  padding: 0 4px;
}
.qo-coupon__clear:hover { color: var(--rose-600); }
.qo-coupon__scope {
  margin: 4px 0 0;
  font-size: 0.75rem;
  color: var(--gray-500);
}

.qo-total__ligne > span:last-child {
  font-weight: 600;
  color: var(--gray-700);
  text-align: right;
}
/* Montant non chiffrable : il ne doit pas se lire comme un prix ferme. */
.qo-total__attente {
  font-weight: 500 !important;
  font-style: italic;
  color: var(--gray-500) !important;
}

.qo-total strong {
  font-family: var(--font-sans);
  font-size: 1.1875rem;
  font-weight: 800;
  color: var(--rose-600);
  white-space: nowrap;
}

/*
 * Le bouton prend toute la place restante et garde 52 px de haut : c'est lui
 * qui fixe la hauteur du pied, et la cible tactile ne doit pas payer le gain
 * de place. Plus de `btn-lg` : ses 18 px de padding vertical et ses 40 px
 * horizontaux sont dimensionnés pour une page, pas pour une barre d'action.
 */
.qo-submit {
  flex: 1 1 auto;
  min-height: 52px;
  padding: 12px 20px;
  justify-content: center;
  gap: var(--space-2);
}

.qo-checkout-link {
  display: block;
  text-align: center;
  font-size: 0.8125rem;
  font-weight: 500;
  color: var(--gray-500);
  text-decoration: underline;
  text-underline-offset: 3px;
  transition: color var(--transition-fast);
  /* Détachée du dernier champ : au bout d'un formulaire, un lien collé à la
     saisie se clique par erreur. */
  margin: var(--space-5) 0 var(--space-2);
  padding: var(--space-2);
}
.qo-checkout-link:hover { color: var(--gray-800); }

.qo-error {
  font-size: 0.875rem;
  color: #dc2626;
  background: #fef2f2;
  padding: var(--space-3);
  border-radius: var(--radius-md);
}

/* ── Champs requis non remplis ───────────────────────────────────────────────
   Bordure rouge doublée d'un halo : la bordure seule se perd sur les petits
   écrans, où le champ signalé occupe toute la largeur. */
.input--error,
.qo-combobox__trigger--error {
  border-color: #dc2626;
  background: #fef2f2;
}

.input--error:focus,
.qo-combobox__trigger--error:focus {
  outline: none;
  border-color: #dc2626;
  box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.18);
}

.qo-payments--error {
  border-radius: var(--radius-md);
  box-shadow: 0 0 0 2px #dc2626;
}

.qo-required {
  margin-top: 0.35rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #dc2626;
  display: flex;
  align-items: center;
  gap: 0.3rem;
}

/* Le losange tient lieu d'icône : pas de SVG à charger, et le repère reste
   lisible pour qui distingue mal le rouge du texte voisin. */
.qo-required::before {
  content: '';
  width: 0.5rem;
  height: 0.5rem;
  background: #dc2626;
  transform: rotate(45deg);
  flex: none;
}

.qo-spinner {
  display: inline-block;
  width: 18px; height: 18px;
  border: 2px solid rgba(255,255,255,.4);
  border-top-color: #fff;
  border-radius: 50%;
  animation: qs-spin 0.7s linear infinite;
}
@keyframes qs-spin { to { transform: rotate(360deg); } }

/* ── Confirmation ── */
.qo-confirmed {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-4) 0;
}
.qo-confirmed__checkmark { display: flex; justify-content: center; }
.qo-confirmed__title {
  font-family: var(--font-display);
  font-size: 1.5rem;
  color: var(--gray-800);
}
.qo-confirmed__number { font-size: 0.9375rem; color: var(--gray-500); }

/* ── Wave Box ── */
.qo-wave-box {
  width: 100%;
  background: #eff6ff;
  border: 1.5px solid #bfdbfe;
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  text-align: center;
}
.qo-wave-box__label { font-size: 0.8125rem; color: #1d4ed8; font-weight: 600; margin-bottom: var(--space-2); }
.qo-wave-box__number { font-size: 1.5rem; font-weight: 700; color: #1e40af; letter-spacing: 0.05em; }
.qo-wave-box__total { font-size: 0.875rem; color: #1d4ed8; margin-top: var(--space-2); }
.qo-wave-box__ref { font-size: 0.8125rem; color: #3b82f6; }

/* Bouton « Payer avec Wave » */
.qo-wave-pay {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  margin-top: var(--space-3);
  padding: 12px 18px;
  border-radius: var(--radius-full, 999px);
  background: #1dc3f0; /* bleu Wave */
  color: #fff;
  font-size: 0.9375rem;
  font-weight: 700;
  text-decoration: none;
  transition: transform 0.15s, box-shadow 0.15s, background 0.15s;
  box-shadow: 0 4px 14px rgba(29, 195, 240, .35);
}
.qo-wave-pay:hover {
  background: #06b6e0;
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(29, 195, 240, .45);
}
.qo-wave-pay:active { transform: translateY(0); }

/* ── WhatsApp ── */
.qo-wa-btn { width: 100%; justify-content: center; gap: var(--space-2); }

/* ── Actions confirmation ── */
.qo-confirmed__actions {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  width: 100%;
}

/* ── Pré-remplissage profil ── */
.qo-prefilled-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: 0.625rem;
  font-weight: 500;
  color: var(--gray-400);
  letter-spacing: 0.03em;
}
.input--prefilled {
  border-color: var(--gray-300) !important;
}

/* ── Nudge complétion profil ── */
.qo-nudge {
  width: 100%;
  background: linear-gradient(135deg, #fff9fb, #fff);
  border: 1.5px solid var(--rose-200);
  border-radius: var(--radius-xl);
  padding: var(--space-4);
  text-align: left;
}
.qo-nudge__title {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: 0.9375rem;
  font-weight: 700;
  color: var(--gray-800);
  margin-bottom: var(--space-1);
}
.qo-nudge__desc {
  font-size: 0.8125rem;
  color: var(--gray-500);
  margin-bottom: var(--space-3);
  line-height: 1.5;
}
.qo-nudge__form {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

/*
 * Champs sur toute la largeur, comme le bouton juste dessous.
 *
 * `.input--sm` est la variante des barres de filtres au-dessus d'un tableau :
 * elle plafonne à 220 px. Ici, dans une carte étroite, les champs restaient
 * donc plus courts que le bouton, et le bloc paraissait de travers.
 */
.qo-nudge__form .input--sm {
  width: 100%;
  min-width: 0;
  max-width: none;
}
.input--sm {
  padding: 8px 12px;
  font-size: 0.875rem;
  width: 100% !important;
}
.qo-nudge__err {
  font-size: 0.8125rem;
  color: #dc2626;
  background: #fef2f2;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
}
.qo-nudge__ok {
  font-size: 0.8125rem;
  color: #15803d;
  font-weight: 600;
}

/* ── Mobile ── */
@media (max-width: 480px) {
  .qo-overlay { padding: 0; align-items: flex-end; }
  .qo-card {
    max-width: 100%;
    max-height: 95vh;
    border-radius: var(--radius-2xl, 20px) var(--radius-2xl, 20px) 0 0;
  }
  /* Marges resserrées et bord latéral réduit : sur un écran de téléphone, le
     pied ne doit plus être une zone mais une barre. */
  .qo-footer {
    padding: var(--space-2) var(--space-4);
    padding-bottom: max(var(--space-2), env(safe-area-inset-bottom));
    gap: var(--space-3);
  }
  .qo-total strong { font-size: 1.0625rem; }
}
</style>
