/**
 * Countries offered by the phone field, and how long their numbers are.
 *
 * `min` / `max` are the national digit counts, dialling code excluded. A range
 * rather than one length: several of these countries have numbers of two
 * lengths, and refusing a valid one would cost an order.
 *
 * ⚠ The same table lives on the server, in
 * `commerce/Modules/Order/app/Support/PhoneNumber.php`, which has the last word
 * — the shop front can be bypassed. Change one, change the other.
 */
export const COUNTRIES = [
  { code: 'CI', name: "Côte d'Ivoire", flag: '🇨🇮', dial: '+225', min: 10, max: 10 },
  { code: 'SN', name: 'Sénégal',       flag: '🇸🇳', dial: '+221', min: 9,  max: 9  },
  { code: 'ML', name: 'Mali',          flag: '🇲🇱', dial: '+223', min: 8,  max: 8  },
  { code: 'BF', name: 'Burkina Faso',  flag: '🇧🇫', dial: '+226', min: 8,  max: 8  },
  { code: 'GN', name: 'Guinée',        flag: '🇬🇳', dial: '+224', min: 9,  max: 9  },
  { code: 'TG', name: 'Togo',          flag: '🇹🇬', dial: '+228', min: 8,  max: 8  },
  { code: 'BJ', name: 'Bénin',         flag: '🇧🇯', dial: '+229', min: 8,  max: 10 },
  { code: 'GH', name: 'Ghana',         flag: '🇬🇭', dial: '+233', min: 9,  max: 9  },
  { code: 'NG', name: 'Nigeria',       flag: '🇳🇬', dial: '+234', min: 7,  max: 11 },
  { code: 'CM', name: 'Cameroun',      flag: '🇨🇲', dial: '+237', min: 9,  max: 9  },
  { code: 'MA', name: 'Maroc',         flag: '🇲🇦', dial: '+212', min: 9,  max: 9  },
  { code: 'DZ', name: 'Algérie',       flag: '🇩🇿', dial: '+213', min: 9,  max: 9  },
  { code: 'TN', name: 'Tunisie',       flag: '🇹🇳', dial: '+216', min: 8,  max: 8  },
  { code: 'FR', name: 'France',        flag: '🇫🇷', dial: '+33',  min: 9,  max: 9  },
  { code: 'BE', name: 'Belgique',      flag: '🇧🇪', dial: '+32',  min: 8,  max: 9  },
  { code: 'CH', name: 'Suisse',        flag: '🇨🇭', dial: '+41',  min: 9,  max: 9  },
  { code: 'DE', name: 'Allemagne',     flag: '🇩🇪', dial: '+49',  min: 10, max: 11 },
  { code: 'GB', name: 'Royaume-Uni',   flag: '🇬🇧', dial: '+44',  min: 10, max: 10 },
  { code: 'ES', name: 'Espagne',       flag: '🇪🇸', dial: '+34',  min: 9,  max: 9  },
  { code: 'IT', name: 'Italie',        flag: '🇮🇹', dial: '+39',  min: 9,  max: 11 },
  { code: 'PT', name: 'Portugal',      flag: '🇵🇹', dial: '+351', min: 9,  max: 9  },
  { code: 'CA', name: 'Canada',        flag: '🇨🇦', dial: '+1',   min: 10, max: 10 },
  { code: 'US', name: 'États-Unis',    flag: '🇺🇸', dial: '+1',   min: 10, max: 10 },
]

/**
 * Countries whose numbers are refused when malformed.
 *
 * Côte d'Ivoire only: that is where the courier phones the customer at the
 * door, and where the plan is certain. Abroad our rules are approximations —
 * the form says so, but it never blocks the order.
 */
export const STRICT = ['CI']

export const isStrictCountry = (code) => STRICT.includes(code)

/**
 * Côte d'Ivoire, since the 2021 migration: ten digits, starting with 0
 * (mobile 01, 05, 07), 2 or 3 (fixed lines). Eight- and nine-digit numbers are
 * the old plan — the commonest mistake, and the one worth catching.
 */
function isValidIvorian(digits) {
  return digits.length === 10 && ['0', '2', '3'].includes(digits[0])
}

/** Is this national number usable for that country? */
export function isValidNumber(digits, country) {
  const d = String(digits ?? '').replace(/\D/g, '')

  if (!d) return false
  if (country?.code === 'CI') return isValidIvorian(d)

  const min = country?.min ?? 6
  const max = country?.max ?? 15

  return d.length >= min && d.length <= max
}
