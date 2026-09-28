<?php

namespace Modules\Order\Data;

use Spatie\LaravelData\Data;

class AddressData extends Data
{
    public function __construct(
        public readonly string  $first_name,
        public readonly string  $address_line1,
        public readonly string  $city,
        public readonly string  $country,
        // last_name est optionnel (StoreOrderRequest le déclare nullable).
        // Défaut '' pour éviter un ArgumentCountError (500) quand il est omis.
        public readonly string  $last_name     = '',
        public readonly ?string $last_name2    = null,
        public readonly ?string $address_line2 = null,
        public readonly ?string $state         = null,
        public readonly ?string $postal_code   = null,
        public readonly ?string $phone          = null,
        public readonly ?string $email          = null,
        public readonly ?string $landmark       = null,
        public readonly ?string $receiver_phone = null,
        public readonly ?string $commune        = null,
        public readonly ?string $region         = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'first_name'     => $this->first_name,
            'last_name'      => $this->last_name,
            'address_line1'  => $this->address_line1,
            'address_line2'  => $this->address_line2,
            'city'           => $this->city,
            'commune'        => $this->commune,
            'region'         => $this->region,
            'state'          => $this->state,
            'postal_code'    => $this->postal_code,
            'country'        => $this->country,
            'phone'          => $this->phone,
            'email'          => $this->email,
            'landmark'       => $this->landmark,
            'receiver_phone' => $this->receiver_phone,
        ], fn($v) => $v !== null);
    }
}
