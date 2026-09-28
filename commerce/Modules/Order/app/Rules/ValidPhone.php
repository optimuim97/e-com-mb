<?php

namespace Modules\Order\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Order\Support\PhoneNumber;

/**
 * Refuses a phone number the shop could not call back.
 *
 * Strict for Côte d'Ivoire, where the rule is certain and the courier phones
 * the customer at the door. Abroad, only obvious nonsense is refused — our
 * rules there are approximations, and a customer in Paris must never be
 * blocked by our guesswork.
 */
class ValidPhone implements ValidationRule
{
    public function __construct(private readonly string $country = 'CI')
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return; // `required` says whether it may be missing.
        }

        if (PhoneNumber::isValid($value, $this->country)) {
            return;
        }

        if (PhoneNumber::isStrict($this->country)) {
            $fail('Ce numéro ne semble pas valide. En Côte d\'Ivoire, il compte 10 chiffres — par exemple 07 00 00 00 00.');

            return;
        }

        // Elsewhere, only what cannot be a number at all.
        $digits = strlen(PhoneNumber::digits($value));

        if ($digits < 6 || $digits > 15) {
            $fail('Ce numéro ne semble pas valide.');
        }
    }
}
