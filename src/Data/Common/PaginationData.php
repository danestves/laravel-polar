<?php

namespace Danestves\LaravelPolar\Data\Common;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class PaginationData extends Data
{
    public function __construct(
        #[MapName('total_count')]
        public readonly int $totalCount,
        #[MapName('max_page')]
        public readonly int $maxPage,
    ) {}
}
