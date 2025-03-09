<?php

namespace Danestves\LaravelPolar\Data\Products;

use Danestves\LaravelPolar\Enums\ListProductsSorting;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class ListProductsRequestData extends Data
{
    public function __construct(
        /**
         * Filter by product ID.
         *
         * @var ?string|array<string>|null
         */
        public readonly string|array|null $id,
        /**
         * Filter by organization ID.
         *
         * Example: `"1dbfc517-0bbf-4301-9ba8-555ca42b9737"`
         *
         * @var string|array<string>|null
         */
        #[MapName('organization_id')]
        public readonly string|array|null $organizationId,
        /**
         * Filter by product name.
         */
        public readonly ?string $query,
        /**
         * Filter on archived products.
         */
        #[MapName('is_archived')]
        public readonly ?bool $isArchived,
        /**
         * Filter on recurring products. If `true`, only subscriptions tiers are returned.
         * If `false`, only one-time purchase products are returned.
         */
        #[MapName('is_recurring')]
        public readonly ?bool $isRecurring,
        /**
         * Filter products granting specific benefit.
         *
         * @var string|array<string>|null
         */
        #[MapName('benefit_id')]
        public readonly string|array|null $benefitId,
        /**
         * Page number, defaults to 1.
         *
         * Required range: `x > 0`
         */
        public readonly ?int $page,
        /**
         * Size of a page, defaults to 10. Maximum is 100.
         *
         * Required range: `x > 0`
         */
        public readonly ?int $limit,
        /**
         * Sorting criterion. Several criteria can be used simultaneously and will be applied in order. Add a minus sign - before the criteria name to sort by descending order.
         *
         * Available options: `created_at`, `-created_at`, `name`, `-name`, `price_amount_type`, `-price_amount_type`, `price_amount`, `-price_amount`
         *
         * @var array<ListProductsSorting>|null
         */
        public readonly ?array $sorting,
    ) {}
}
