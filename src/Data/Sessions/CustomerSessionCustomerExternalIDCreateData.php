<?php

namespace Danestves\LaravelPolar\Data\Sessions;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CustomerSessionCustomerExternalIDCreateData extends Data
{
    public function __construct(
        /**
         * External ID of the customer to create a session for.
         */
        #[MapName('customer_external_id')]
        public readonly string $customerExternalId,
    ) {}
}
