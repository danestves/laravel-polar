<?php

namespace Danestves\LaravelPolar\Data\Checkout;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CustomFieldNumberData extends Data
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
         * The ID of the benefit.
         */
        public readonly string $id,
        /** @var array<string, string|int|bool> */
        public readonly array $metadata,
        /**
         * Allowed value: `"number"`
         */
        public readonly string $type,
        /**
         * Identifier of the custom field. It'll be used as key when storing the value.
         */
        public readonly string $slug,
        /**
         * Name of the custom field.
         */
        public readonly string $name,
        /**
         * The ID of the organization owning the custom field.
         */
        #[MapName('organization_id')]
        public readonly string $organizationId,
        public readonly CustomFieldNumberPropertiesData $properties,
    ) {}
}
