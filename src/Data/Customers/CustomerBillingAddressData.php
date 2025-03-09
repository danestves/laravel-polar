<?php

namespace Danestves\LaravelPolar\Data\Customers;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CustomerBillingAddressData extends Data
{
    public function __construct(
        public readonly string $country,
        public readonly ?string $line1,
        public readonly ?string $line2,
        #[MapName('postal_code')]
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly ?string $state,
    ) {}
}
