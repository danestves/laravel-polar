<?php

namespace Danestves\LaravelPolar\Http;

use Danestves\LaravelPolar\Data\Pagination;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Spatie\LaravelData\Data;

/**
 * Talks to the Polar REST API over plain HTTP.
 *
 * Polar retired their PHP SDK in July 2026 and now recommend calling the API directly, so
 * this is the package's only transport. It is built on Laravel's HTTP client, which means
 * `Http::fake()` works in application test suites exactly as it would for any other outbound
 * call.
 */
class PolarClient
{
    public const PRODUCTION_URL = 'https://api.polar.sh';

    public const SANDBOX_URL = 'https://sandbox-api.polar.sh';

    /**
     * The Polar API version these data classes were generated against. Sent on every request
     * so a future default-version bump on Polar's side cannot silently reshape responses.
     */
    public const API_VERSION = '2026-04';

    public function __construct(
        private readonly string $accessToken,
        private readonly string $baseUrl = self::PRODUCTION_URL,
        private readonly string $version = self::API_VERSION,
        private readonly ?int $timeout = null,
    ) {}

    /**
     * Resolve the base URL for a configured server, accepting either one of Polar's
     * environment names or an explicit URL (handy for pointing at a local mock).
     */
    public static function resolveBaseUrl(?string $server): string
    {
        $server = $server ?? 'production';

        if (str_starts_with($server, 'http://') || str_starts_with($server, 'https://')) {
            return rtrim($server, '/');
        }

        return $server === 'sandbox' ? self::SANDBOX_URL : self::PRODUCTION_URL;
    }

    /**
     * Send a GET request and return the decoded body.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws PolarApiError
     */
    public function get(string $path, array $query = [], ?string $token = null): array
    {
        return $this->send('GET', $path, query: $query, token: $token);
    }

    /**
     * @param  array<string, mixed>|Data|null  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws PolarApiError
     */
    public function post(string $path, array|Data|null $body = null, array $query = [], ?string $token = null, bool $keepNulls = false): array
    {
        return $this->send('POST', $path, $body, $query, $token, $keepNulls);
    }

    /**
     * @param  array<string, mixed>|Data|null  $body
     * @return array<string, mixed>
     *
     * @throws PolarApiError
     */
    public function patch(string $path, array|Data|null $body = null, ?string $token = null, bool $keepNulls = false): array
    {
        return $this->send('PATCH', $path, $body, token: $token, keepNulls: $keepNulls);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PolarApiError
     */
    public function delete(string $path, ?string $token = null): array
    {
        return $this->send('DELETE', $path, token: $token);
    }

    /**
     * Fetch one page of a list endpoint and hydrate its items.
     *
     * Polar paginates with `page`/`limit` and reports `total_count`/`max_page`; this returns a
     * single page rather than walking every one, so callers stay in control of how much they
     * pull.
     *
     * @template TItem of Data
     *
     * @param  class-string<TItem>  $itemClass
     * @param  array<string, mixed>  $query
     * @return Page<TItem>
     *
     * @throws PolarApiError
     */
    public function page(string $path, string $itemClass, array $query = [], ?string $token = null): Page
    {
        $body = $this->get($path, $query, $token);

        $items = [];
        foreach ($body['items'] ?? [] as $item) {
            $items[] = $itemClass::from($item);
        }

        return new Page(
            $items,
            Pagination::from($body['pagination'] ?? ['total_count' => count($items), 'max_page' => 1]),
        );
    }

    /**
     * @param  array<string, mixed>|Data|null  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws PolarApiError
     */
    protected function send(
        string $method,
        string $path,
        array|Data|null $body = null,
        array $query = [],
        ?string $token = null,
        bool $keepNulls = false,
    ): array {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $queryString = $this->buildQuery($query);

        if ($queryString !== '') {
            $url .= '?' . $queryString;
        }

        $request = $this->request($token);

        $response = match ($method) {
            'GET' => $request->get($url),
            'DELETE' => $request->delete($url),
            'POST' => $request->post($url, $this->payload($body, $keepNulls)),
            'PATCH' => $request->patch($url, $this->payload($body, $keepNulls)),
            default => throw new \InvalidArgumentException("Unsupported HTTP method [{$method}]."),
        };

        if ($response->failed()) {
            throw PolarApiError::fromResponse($response, $method, $url);
        }

        return $this->decode($response);
    }

    protected function request(?string $token): PendingRequest
    {
        $request = Http::withToken($token ?? $this->accessToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Polar-Version' => $this->version,
            ])
            ->asJson();

        return $this->timeout !== null ? $request->timeout($this->timeout) : $request;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decode(Response $response): array
    {
        if ($response->status() === 204 || trim($response->body()) === '') {
            return [];
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Turn a request body into the array Polar expects.
     *
     * Nulls are dropped by default: most Polar update endpoints treat an absent key as "leave
     * this alone" and an explicit null as "clear it", and generated request objects default
     * every optional field to null. Callers that genuinely mean "clear it" pass `$keepNulls`.
     *
     * @param  array<string, mixed>|Data|null  $body
     * @return array<string, mixed>
     */
    protected function payload(array|Data|null $body, bool $keepNulls): array
    {
        if ($body === null) {
            return [];
        }

        $array = $body instanceof Data ? $body->toArray() : $body;

        return $this->normalize($array, $keepNulls);
    }

    /**
     * Prepare an array for JSON encoding: unwrap enums and dates into the scalar forms Polar
     * expects, and (unless asked otherwise) drop nulls.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function normalize(array $values, bool $keepNulls): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if ($value === null && ! $keepNulls) {
                continue;
            }

            $normalized[$key] = match (true) {
                $value instanceof Data => $this->normalize($value->toArray(), $keepNulls),
                is_array($value) => $this->normalize($value, $keepNulls),
                $value instanceof \BackedEnum => $value->value,
                $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
                default => $value,
            };
        }

        return $normalized;
    }

    /**
     * Build a query string Polar (FastAPI) understands.
     *
     * List filters must repeat the key — `?id=a&id=b` — where PHP's `http_build_query` would
     * emit `id[0]=a&id[1]=b` and be rejected.
     *
     * @param  array<string, mixed>  $query
     */
    protected function buildQuery(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) {
                    continue;
                }

                $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($this->stringify($item));
            }
        }

        return implode('&', $parts);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \DateTimeInterface => $value->format(\DateTimeInterface::ATOM),
            default => (string) $value,
        };
    }
}
