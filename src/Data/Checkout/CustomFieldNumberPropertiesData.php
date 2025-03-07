<?php

namespace Danestves\LaravelPolar\Data\Checkout;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class CustomFieldNumberPropertiesData extends Data
{
    public function __construct(
        /**
         * Minimum length: `1`
         */
        #[MapName('form_label')]
        public readonly ?string $formLabel,
        /**
         * Minimum length: `1`
         */
        #[MapName('form_help_text')]
        public readonly ?string $formHelpText,
        /**
         * Minimum length: `1`
         */
        #[MapName('form_placeholder')]
        public readonly ?string $formPlaceholder,
        public readonly ?int $ge,
        public readonly ?int $le,
    ) {}
}
