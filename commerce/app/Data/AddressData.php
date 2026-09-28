<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class AddressData extends Data
{
    public function __construct(
        public readonly string  $first_name,
        public readonly string  $last_name,
        public readonly string  $address_line1,
        public readonly string  $city,
        public readonly string  $country,
        public readonly ?string $last_name2    = null,
        public readonly ?string $address_line2 = null,
        public readonly ?string $state         = null,
        public readonly ?string $postal_code   = null,
        public readonly ?string $phone         = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city'          => $this->city,
            'state'         => $this->state,
            'postal_code'   => $this->postal_code,
            'country'       => $this->country,
            'phone'         => $this->phone,
        ], fn($v) => $v !== null);
    }
}
