<?php

namespace Modules\Order\Support;

use Modules\Order\Models\Order;

/**
 * The address a customer can actually be written to.
 *
 * A quick order invents an address to create the account — the customer never
 * gave one. Writing to it produces a bounce, and enough bounces damage the
 * shop's sending reputation, which then costs the emails that do matter.
 *
 * The rule lived inside the order-confirmation listener. The payment reminder
 * needed the very same rule, and a second copy would have drifted: this is now
 * the only place that decides what a reachable address is.
 */
final class CustomerEmail
{
    /**
     * TLDs reserved by RFC 6761, plus .local (mDNS): never routable on the
     * internet, whatever the address in front of them.
     */
    private const BLOCKED_TLDS = ['local', 'invalid', 'test', 'example', 'localhost'];

    /** Domains this project generates on purpose to fill a required field. */
    private const BLOCKED_DOMAINS = ['clients.local', 'boutique.local', 'commerce.local', 'localhost'];

    public static function isReal(?string $email): bool
    {
        $email = strtolower(trim((string) $email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = substr(strrchr($email, '@'), 1);

        foreach (self::BLOCKED_TLDS as $tld) {
            if ($domain === $tld || str_ends_with($domain, '.' . $tld)) {
                return false;
            }
        }

        return ! in_array($domain, self::BLOCKED_DOMAINS, true);
    }

    /**
     * Where to write for this order: the address given at checkout first, the
     * account's only as a fallback. Null when the customer is unreachable by
     * email — she is then handled by hand, from the « À traiter » screen.
     */
    public static function for(Order $order): ?string
    {
        $candidates = [
            $order->shipping_address['email'] ?? null,
            $order->user?->email,
        ];

        foreach ($candidates as $candidate) {
            if (self::isReal($candidate)) {
                return trim((string) $candidate);
            }
        }

        return null;
    }
}
