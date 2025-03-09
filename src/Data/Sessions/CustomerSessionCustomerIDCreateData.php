<?php

namespace Danestves\LaravelPolar\Data\Sessions;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CustomerSessionCustomerIDCreateData extends Data
{
    public function __construct(
        /**
         * ID of the customer to create a session for.
         */
        #[MapName('customer_id')]
        public readonly string $customerId,
    ) {}
}
