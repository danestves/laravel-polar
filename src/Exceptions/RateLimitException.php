<?php

namespace Danestves\LaravelPolar\Exceptions;

/**
 * Thrown for 429 responses. `retryAfter` mirrors the `Retry-After` header in seconds when
 * Polar sends one, so callers can pace their own retries even with automatic retries off.
 */
class RateLimitException extends PolarApiError
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        int $status = 0,
        array $body = [],
        ?string $method = null,
        ?string $url = null,
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message, $status, $body, $method, $url);
    }
}
