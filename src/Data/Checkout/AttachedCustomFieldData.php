<?php

namespace Danestves\LaravelPolar\Data\Checkout;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class AttachedCustomFieldData extends Data
{
    public function __construct(
        /**
         * ID of the custom field.
         */
        #[MapName('custom_field_id')]
        public readonly string $customFieldId,
        /**
         * Schema for a custom field of type text.
         */
        #[MapName('custom_field')]
        public readonly CustomFieldTextData|CustomFieldNumberData|CustomFieldDateData|CustomFieldCheckboxData|CustomFieldSelectData $customField,
        /**
         * Order of the custom field in the resource.
         */
        public readonly int $order,
        /**
         * Whether the value is required for this custom field.
         */
        public readonly bool $required,
    ) {}
}
