<?php

namespace Danestves\LaravelPolar\Data\Sessions;

use Danestves\LaravelPolar\Data\Customers\CustomerData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CustomerSessionData extends Data
{
    public function __construct(
        /**
         * Creation timestamp of the object.
         */
        #[MapName('created_at')]
        public readonly string $createdAt,
        /**
         * Last modification timestamp of the object.
         */
        #[MapName('modified_at')]
        public readonly ?string $modifiedAt,
        /**
         * The ID of the customer session.
         */
        public readonly string $id,
        public readonly string $token,
        #[MapName('expires_at')]
        public readonly string $expiresAt,
        #[MapName('customer_portal_url')]
        public readonly string $customerPortalUrl,
        #[MapName('customer_id')]
        public readonly string $customerId,
        public readonly CustomerData $customer,
    ) {}
}
