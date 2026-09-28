import { useI18n } from 'vue-i18n'
import { useCurrencyStore } from '@/stores/currency'

/**
 * Instructions de règlement pour les paiements mobiles saisis à la main
 * (Wave, Orange Money, MTN MoMo).
 *
 * Source unique, partagée par la confirmation de commande et la page de suivi.
 * La cliente lit ces consignes à deux moments — juste après avoir commandé,
 * puis en revenant payer plus tard : si les deux écrans ne disent pas la même
 * chose, c'est le montant ou la référence qui finit par diverger, et le
 * paiement arrive sans qu'on puisse le rattacher à une commande.
 *
 * Les moyens réglés en ligne par passerelle (GeniusPay, carte) n'ont rien à
 * expliquer : c'est la passerelle qui encaisse. La fonction renvoie null.
 */
export function usePaymentInstructions() {
  const { t } = useI18n()

  /** Moyens de paiement qui demandent un virement manuel de la cliente. */
  const MANUAL_METHODS = ['wave', 'orange_money', 'mtn']

  function isManual(method) {
    return MANUAL_METHODS.includes(String(method ?? ''))
  }

  /**
   * @param  {Object}  settings     réglages boutique bruts (clé → valeur)
   * @param  {string}  method       wave | orange_money | mtn
   * @param  {string}  orderNumber  référence à rappeler dans le motif
   * @param  {number}  orderTotal   montant exact à envoyer
   *
   * @returns {{title, icon, number, method, payUrl, instructions}|null}
   */
  function build(settings, method, orderNumber, orderTotal) {
    // Lecture robuste : selon l'appelant, `settings` arrive déballé par Pinia
    // ou encore sous forme de ref.
    const s = (settings?.value ?? settings ?? {})
    const lire = (cle) => (s[cle] ?? '').toString().trim()

    const numeroParDefaut = lire('payment_mobile_number')
    const formatPrix = (v) => useCurrencyStore().format(Number(v) || 0)

    const CONFIGS = {
      wave: {
        title:        t('checkout.payTitleWave'),
        icon:         '<span class="pm-badge pm-badge--wave pm-badge--lg">W</span>',
        number:       lire('payment_wave_number') || numeroParDefaut,
        instructions: lire('payment_wave_instructions'),
        ussd:         t('checkout.ussdWave'),
      },
      orange_money: {
        title:        t('checkout.payTitleOrange'),
        icon:         '<span class="pm-badge pm-badge--orange pm-badge--lg">OM</span>',
        number:       lire('payment_orange_money_number') || numeroParDefaut,
        instructions: lire('payment_orange_money_instructions'),
        ussd:         t('checkout.ussdOrange'),
      },
      mtn: {
        title:        t('checkout.payTitleMtn'),
        icon:         '<span class="pm-badge pm-badge--mtn pm-badge--lg">M</span>',
        number:       lire('payment_mtn_number') || numeroParDefaut,
        instructions: lire('payment_mtn_instructions'),
        ussd:         t('checkout.ussdMtn'),
      },
    }

    const cfg = CONFIGS[method]
    if (!cfg) return null

    // Lien marchand Wave : le montant est passé en paramètre pour que la
    // cliente n'ait pas à le retaper — c'est là que naissent les écarts.
    let payUrl = null
    if (method === 'wave') {
      const base = lire('payment_wave_link')
      if (base) {
        const montant = Math.round(Number(orderTotal) || 0)
        const sep = base.includes('?') ? (base.endsWith('?') || base.endsWith('&') ? '' : '&') : '?'
        payUrl = montant ? `${base}${sep}amount=${montant}` : base
      }
    }

    return {
      title:  cfg.title,
      icon:   cfg.icon,
      number: cfg.number,
      method,
      payUrl,
      // La boutique peut rédiger ses propres consignes ; à défaut on déroule
      // les étapes, référence de commande comprise.
      instructions: cfg.instructions
        || t('checkout.payStepsFallback', {
          ussd:      cfg.ussd,
          amount:    formatPrix(orderTotal),
          number:    cfg.number || t('checkout.numberAbove'),
          reference: orderNumber,
        }),
    }
  }

  return { build, isManual, MANUAL_METHODS }
}
