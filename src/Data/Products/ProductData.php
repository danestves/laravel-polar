<?php

namespace Danestves\LaravelPolar\Data\Products;

use Danestves\LaravelPolar\Data\Benefits\BenefitsData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class ProductData extends Data
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
         * The ID of the product.
         */
        public readonly string $id,
        /**
         * The name of the product.
         */
        public readonly string $name,
        /**
         * The description of the product.
         */
        public readonly ?string $description,
        /**
         * The recurring interval of the product. If `None`, the product is a one-time purchase.
         *
         * Available options: `month`, `year`
         */
        #[MapName('recurring_interval')]
        public readonly ?string $recurringInterval,
        /**
         * Whether the product is a subscription.
         */
        #[MapName('is_recurring')]
        public readonly bool $isRecurring,
        /**
         * Whether the product is archived and no longer available.
         */
        #[MapName('is_archived')]
        public readonly bool $isArchived,
        /**
         * The ID of the organization owning the product.
         */
        #[MapName('organization_id')]
        public readonly string $organizationId,
        /**
         * List of prices for this product.
         *
         * @var array<LegacyRecurringProductPriceFixedData|LegacyRecurringProductPriceCustomData|LegacyRecurringProductPriceFreeData|ProductPriceFixedData|ProductPriceCustomData|ProductPriceFreeData>
         */
        public readonly array $prices,
        /**
         * List of benefits granted by the product.
         *
         * @var array<BenefitsData>
         */
        public readonly array $benefits,
        /**
         * List of media associated with the product.
         *
         * @var array<ProductMediaData>|null
         */
        public readonly ?array $media,
    ) {}
}
