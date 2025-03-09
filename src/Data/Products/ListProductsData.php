<?php

namespace Danestves\LaravelPolar\Data\Products;

use Danestves\LaravelPolar\Data\Common\PaginationData;
use Spatie\LaravelData\Data;

class ListProductsData extends Data
{
    public function __construct(
        /** @var array<ProductData> */
        public readonly array $items,
        public readonly PaginationData $pagination,
    ) {}
}
