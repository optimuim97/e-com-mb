<?php

namespace Modules\Order\Support;

/**
 * Phone numbers, as the shop actually uses them: to call the customer.
 *
 * The number was accepted as any string up to 30 characters. The quick order
 * is a public endpoint, so "abcdef" got through — and a parcel whose phone
 * number is wrong comes back to the shop.
 *
 * The rules here are deliberately modest. Côte d'Ivoire is checked properly,
 * because that is where the courier calls and the plan is simple: ten digits
 * since the 2021 migration. Everywhere else, only length is checked, per
 * dialling code — enough to catch a typo or a number left half-typed, not a
 * claim to know every operator range in the world. Matching libphonenumber
 * abroad would mean maintaining the world's numbering plans.
 *
 * ⚠ The same table lives in the shop front-end, in
 * `commerce-front/src/data/phone-countries.js`, which also carries the flags
 * and names of the selector. Change one, change the other.
 */
final class PhoneNumber
{
    /** Countries whose numbers we refuse when they are malformed. */
    public const STRICT = ['CI'];

    /**
     * National digit count allowed per dialling code, without the code itself.
     *
     * A range, not a single length: several of these countries have numbers of
     * two different lengths, and rejecting a valid one would cost an order.
     *
     * @var array<string, array{0: int, 1: int}>
     */
    private const LENGTHS = [
        '225' => [10, 10],  // Côte d'Ivoire
        '221' => [9, 9],    // Sénégal
        '223' => [8, 8],    // Mali
        '226' => [8, 8],    // Burkina Faso
        '224' => [9, 9],    // Guinée
        '228' => [8, 8],    // Togo
        '229' => [8, 10],   // Bénin
        '233' => [9, 9],    // Ghana
        '234' => [7, 11],   // Nigeria
        '237' => [9, 9],    // Cameroun
        '212' => [9, 9],    // Maroc
        '213' => [9, 9],    // Algérie
        '216' => [8, 8],    // Tunisie
        '33'  => [9, 9],    // France
        '32'  => [8, 9],    // Belgique
        '41'  => [9, 9],    // Suisse
        '49'  => [10, 11],  // Allemagne
        '44'  => [10, 10],  // Royaume-Uni
        '34'  => [9, 9],    // Espagne
        '39'  => [9, 11],   // Italie
        '351' => [9, 9],    // Portugal
        '1'   => [10, 10],  // Canada / États-Unis
    ];

    /** Anything outside this is not a phone number, whatever the country. */
    private const MIN_DIGITS = 6;
    private const MAX_DIGITS = 15;

    /** Digits only, dialling prefixes (00, +) dropped. */
    public static function digits(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    /**
     * Splits a number into its dialling code and the national part.
     *
     * Without a code, the number is read as local to the given country — what
     * a customer types on a phone.
     *
     * @return array{code: string|null, national: string}
     */
    public static function split(?string $value, string $country = 'CI'): array
    {
        $digits = self::digits($value);

        if ($digits === '') {
            return ['code' => null, 'national' => ''];
        }

        $explicit = str_starts_with(trim((string) $value), '+')
            || str_starts_with($digits, '00');

        if ($explicit) {
            $digits = ltrim($digits, '0');

            // Longest code first: 225 must win over 22.
            $codes = array_keys(self::LENGTHS);
            usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));

            foreach ($codes as $code) {
                if (str_starts_with($digits, $code)) {
                    return ['code' => $code, 'national' => substr($digits, strlen($code))];
                }
            }

            return ['code' => null, 'national' => $digits];
        }

        $own = self::dialCodeOf($country);

        // A local number sometimes carries its own country code anyway
        // ("225 07…" typed in full): don't count it twice.
        if ($own && str_starts_with($digits, $own) && strlen($digits) > self::LENGTHS[$own][1]) {
            return ['code' => $own, 'national' => substr($digits, strlen($own))];
        }

        return ['code' => $own, 'national' => $digits];
    }

    /**
     * Is the number usable to reach the customer?
     *
     * @param string $country ISO code of the delivery address, which tells us
     *                        how to read a number typed without its code.
     */
    public static function isValid(?string $value, string $country = 'CI'): bool
    {
        ['code' => $code, 'national' => $national] = self::split($value, $country);

        $length = strlen($national);

        if ($length < self::MIN_DIGITS || $length > self::MAX_DIGITS) {
            return false;
        }

        // Unknown dialling code: we have no rule, so only the outer bounds
        // above apply. Refusing here would block a country we simply never
        // listed.
        if ($code === null || ! isset(self::LENGTHS[$code])) {
            return true;
        }

        [$min, $max] = self::LENGTHS[$code];

        if ($length < $min || $length > $max) {
            return false;
        }

        return $code !== '225' || self::isValidIvorian($national);
    }

    /**
     * Côte d'Ivoire, since the 2021 migration: ten digits, and the number
     * starts with 0 (mobile: 01, 05, 07), 2 or 3 (fixed lines).
     *
     * Eight- and nine-digit numbers are the old plan — the commonest mistake,
     * and precisely the one worth catching.
     */
    private static function isValidIvorian(string $national): bool
    {
        return strlen($national) === 10 && in_array($national[0], ['0', '2', '3'], true);
    }

    /** Should a malformed number be refused for this country? */
    public static function isStrict(string $country): bool
    {
        return in_array(strtoupper($country), self::STRICT, true);
    }

    /** Dialling code of a country, when the shop delivers there. */
    private static function dialCodeOf(string $country): ?string
    {
        return match (strtoupper($country)) {
            'CI' => '225', 'SN' => '221', 'ML' => '223', 'BF' => '226',
            'GN' => '224', 'TG' => '228', 'BJ' => '229', 'GH' => '233',
            'NG' => '234', 'CM' => '237', 'MA' => '212', 'DZ' => '213',
            'TN' => '216', 'FR' => '33',  'BE' => '32',  'CH' => '41',
            'DE' => '49',  'GB' => '44',  'ES' => '34',  'IT' => '39',
            'PT' => '351', 'CA', 'US' => '1',
            default => null,
        };
    }
}
