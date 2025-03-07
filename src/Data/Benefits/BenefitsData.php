<?php

namespace Danestves\LaravelPolar\Data\Benefits;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class BenefitsData extends Data
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
        /**
         * The type of the benefit.
         *
         * Available options: `custom`, `discord`, `github_repository`, `downloadables`, `license_keys`
         */
        public readonly string $type,
        /**
         * The description of the benefit.
         */
        public readonly string $description,
        /**
         * Whether the benefit is selectable when creating a product.
         */
        public readonly bool $selectable,
        /**
         * Whether the benefit is deletable.
         */
        public readonly bool $deletable,
        /**
         * The ID of the organization owning the benefit.
         */
        #[MapName('organization_id')]
        public readonly string $organizationId,
    ) {
        //
    }
}
