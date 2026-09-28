<?php

namespace Modules\Order\Support;

use Modules\Admin\Models\Setting;

/**
 * The shop's own mailbox, kept in copy of what is written to customers.
 *
 * Every message sent to a customer lands there too. Without it the shop has no
 * trace of what was promised, to whom, and when: a customer says « on m'a dit
 * que… » and nobody can check. It is also the only way to notice that the
 * reminders are going out at all.
 *
 * Blind copy on purpose: the customer has no reason to see the shop's internal
 * address, and a visible copy invites a reply to the wrong mailbox.
 */
final class ShopMailbox
{
    /** Setting holding the shop's address, already used across the admin. */
    public const SETTING = 'shop_email';

    /**
     * Blind copy for a message going out to a customer.
     *
     * @param  string|null  $recipient  Skipped when it is the shop itself — a
     *                                  test message must not arrive twice.
     * @return array<int, string>
     */
    public static function followUpBcc(?string $recipient = null): array
    {
        $shop = strtolower(trim((string) Setting::get(self::SETTING, '')));

        if (! CustomerEmail::isReal($shop)) {
            return [];
        }

        if ($recipient !== null && strtolower(trim($recipient)) === $shop) {
            return [];
        }

        return [$shop];
    }
}
