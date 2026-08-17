<?php

namespace Danestves\LaravelPolar\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Thrown whenever the Polar API answers with a non-2xx status.
 *
 * Polar returns a JSON body describing what went wrong (a `detail` string, or FastAPI's
 * validation-error list), so the decoded body is kept on the exception rather than being
 * flattened into the message.
 */
class PolarApiError extends Exception
{
    /**
     * @param  array<string, mixed>  $body  the decoded error payload, empty when not JSON
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $body = [],
        public readonly ?string $method = null,
        public readonly ?string $url = null,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Build the exception from a failed response, using Polar's own error description
     * when it provides one.
     */
    public static function fromResponse(Response $response, string $method, string $url): self
    {
        $body = [];

        try {
            $decoded = $response->json();
            $body = is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            // Not a JSON body (a gateway error page, say) — the status still tells the story.
        }

        return new self(
            self::describe($body, $response->status(), $method, $url),
            $response->status(),
            $body,
            $method,
            $url,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function describe(array $body, int $status, string $method, string $url): string
    {
        $detail = $body['detail'] ?? $body['error_description'] ?? $body['error'] ?? null;

        if (is_string($detail) && $detail !== '') {
            return "Polar API error {$status} on {$method} {$url}: {$detail}";
        }

        // FastAPI validation errors arrive as a list of {loc, msg, type} entries.
        if (is_array($detail) && $detail !== []) {
            $messages = [];

            foreach ($detail as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $location = is_array($item['loc'] ?? null) ? implode('.', $item['loc']) : null;
                $message = is_string($item['msg'] ?? null) ? $item['msg'] : null;

                if ($message !== null) {
                    $messages[] = $location !== null ? "{$location}: {$message}" : $message;
                }
            }

            if ($messages !== []) {
                return "Polar API error {$status} on {$method} {$url}: " . implode('; ', $messages);
            }
        }

        return "Polar API error {$status} on {$method} {$url}.";
    }
}
