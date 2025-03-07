<?php

namespace Danestves\LaravelPolar\Data\Products;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;

class ProductMediaData extends Data
{
    public function __construct(
        /**
         * The ID of the product media.
         */
        public readonly string $id,
        #[MapName('organization_id')]
        public readonly string $organizationId,
        public readonly string $name,
        public readonly string $path,
        #[MapName('mime_type')]
        public readonly string $mimeType,
        public readonly int $size,
        #[MapName('storage_version')]
        public readonly ?string $storageVersion,
        #[MapName('checksum_etag')]
        public readonly ?string $checksumEtag,
        #[MapName('checksum_sha256_base64')]
        public readonly ?string $checksumSha256Base64,
        #[MapName('checksum_sha256_hex')]
        public readonly ?string $checksumSha256Hex,
        #[MapName('last_modified_at')]
        public readonly ?string $lastModifiedAt,
        public readonly ?string $version,
        /**
         * Allowed value: `"product_media"`
         */
        public readonly string $service,
        #[MapName('is_uploaded')]
        public readonly bool $isUploaded,
        #[MapName('created_at')]
        public readonly string $createdAt,
        #[MapName('size_readable')]
        public readonly string $sizeReadable,
        #[MapName('public_url')]
        public readonly ?string $publicUrl,
    ) {}
}
