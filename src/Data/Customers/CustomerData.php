<?php

namespace Danestves\LaravelPolar\Data\Customers;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CustomerData extends Data
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
         * The ID of the customer.
         *
         * Example: `"992fae2a-2a17-4b7a-8d9e-e287cf90131b"`
         */
        public readonly string $id,
        /** @var array<string, string|int|bool> */
        public readonly array $metadata,
        /**
         * The ID of the customer in your system. This must be unique within the organization. Once set, it can't be updated.
         *
         * Example: `"usr_1337"`
         */
        #[MapName('external_id')]
        public readonly ?string $externalId,
        /**
         * The email address of the customer. This must be unique within the organization.

         * Example: `"customer@example.com"`
         */
        public readonly string $email,
        /**
         * Whether the customer email address is verified.
         * The address is automatically verified when the customer accesses the customer portal using their email address.
         *
         * Example: `true`
         */
        #[MapName('email_verified')]
        public readonly bool $emailVerified,
        /**
         * The name of the customer.
         *
         * Example: `"John Doe"`
         */
        public readonly ?string $name,
        #[MapName('billing_address')]
        public readonly ?CustomerBillingAddressData $billingAddress,
        /**
         * Example:
         * `["911144442", "us_ein"]`
         */
        #[MapName('tax_id')]
        public readonly array $taxId,
        /**
         * The ID of the organization owning the customer.
         *
         * Example: `"1dbfc517-0bbf-4301-9ba8-555ca42b9737"`
         */
        #[MapName('organization_id')]
        public readonly string $organizationId,
        /**
         * Example: `"https://www.gravatar.com/avatar/xxx?d=blank"`
         */
        #[MapName('avatar_url')]
        public readonly ?string $avatarUrl,
    ) {}
}
