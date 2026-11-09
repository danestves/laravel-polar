<?php

namespace Danestves\LaravelPolar\Testing;

use Danestves\LaravelPolar\Http\PolarClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\AssertionFailedError;

/**
 * A small, dependency-free fake registry for the Polar HTTP API.
 *
 * Register responses by method and path, then assert against what the package sent:
 *
 *     $polar = new PolarFake([
 *         'GET /v1/checkouts/' => ['id' => 'checkout_123'],
 *         'POST /v1/refunds/' => ['status' => 201, 'body' => ['id' => 'refund_123']],
 *     ]);
 *
 *     $polar->respond('GET', '/v1/orders/*', ['items' => [], 'pagination' => [...]]);
 *     $polar->assertSent('GET', '/v1/checkouts/');
 *
 * Paths are matched against the URL path only (the query string is ignored) and may contain
 * `*` wildcards, so a single registration covers every id under a collection. Requests outside
 * the host configured by `polar.server` are left alone so the fake does not shadow other
 * outbound HTTP calls in the same test.
 */
class PolarFake
{
    /**
     * Registered responses keyed by "METHOD path".
     *
     * @var array<string, array{method: string, path: string, status: int, body: array<mixed>, headers: array<string, string>}>
     */
    private array $responses = [];

    /**
     * Requests that did not match any registered response, useful when debugging a fake.
     *
     * @var list<array{method: string, path: string, url: string}>
     */
    private array $unmatched = [];

    /**
     * @param  array<string, array<string, mixed>|list<mixed>>  $responses
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $signature => $response) {
            [$method, $path] = $this->parseSignature((string) $signature);

            $this->register($method, $path, $response);
        }

        $this->registerHttpFake();
    }

    /**
     * Register (or replace) the response for a method and path.
     *
     * @param  array<mixed>  $body
     */
    public function respond(string $method, string $path, array $body = [], int $status = 200): self
    {
        $this->register($method, $path, [
            'status' => $status,
            'body' => $body,
        ]);

        return $this;
    }

    /**
     * All request/response pairs recorded by Laravel's HTTP client.
     *
     * @return array<int, array{0: Request, 1: \Illuminate\Http\Client\Response}>
     */
    public function recorded(): array
    {
        return Http::recorded()
            ->filter(fn(array $pair) => $this->isPolarRequest($pair[0]))
            ->values()
            ->all();
    }

    /**
     * Requests that matched no registered response.
     *
     * @return list<array{method: string, path: string, url: string}>
     */
    public function unmatched(): array
    {
        return $this->unmatched;
    }

    /**
     * Assert that a matching request was sent, optionally filtered by a callback.
     */
    public function assertSent(string $method, string $path, ?callable $callback = null): void
    {
        if ($this->matchingRecorded($method, $path, $callback) !== []) {
            return;
        }

        $description = $callback === null ? '' : ' satisfying the given callback';

        throw new AssertionFailedError(sprintf(
            'Expected a %s request matching [%s]%s to have been sent, but it was not. %s',
            strtoupper($method),
            $path,
            $description,
            $this->recordedSummary(),
        ));
    }

    /**
     * Assert that no matching request was sent.
     */
    public function assertNotSent(string $method, string $path): void
    {
        if ($this->matchingRecorded($method, $path) === []) {
            return;
        }

        throw new AssertionFailedError(sprintf(
            'Expected no %s request matching [%s] to have been sent, but one was. %s',
            strtoupper($method),
            $path,
            $this->recordedSummary(),
        ));
    }

    /**
     * Assert that no request at all was sent.
     */
    public function assertNothingSent(): void
    {
        if ($this->recorded() === []) {
            return;
        }

        throw new AssertionFailedError('Expected no requests to have been sent, but some were. ' . $this->recordedSummary());
    }

    /**
     * Assert the total number of recorded requests.
     */
    public function assertSentCount(int $count): void
    {
        $actual = count($this->recorded());

        if ($actual === $count) {
            return;
        }

        throw new AssertionFailedError(sprintf(
            'Expected %d request(s) to have been sent, but %d were. %s',
            $count,
            $actual,
            $this->recordedSummary(),
        ));
    }

    /**
     * Forget every registered response and start recording from scratch.
     */
    public function reset(): self
    {
        $this->responses = [];
        $this->unmatched = [];

        $this->registerHttpFake();

        return $this;
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $response
     */
    private function register(string $method, string $path, array $response): void
    {
        $method = strtoupper($method);

        // A Polar body can legitimately contain a `status` field (`"status": "open"`), so an
        // envelope is only detected when `status` is numeric and the array holds nothing else,
        // or when a `body` array is present explicitly.
        $hasStatus = array_key_exists('status', $response)
            && (is_int($response['status']) || (is_string($response['status']) && ctype_digit($response['status'])));
        $hasBody = array_key_exists('body', $response) && is_array($response['body']);
        $holdsOnlyEnvelopeKeys = array_diff(array_keys($response), ['status', 'body', 'headers']) === [];

        $isEnvelope = $hasBody || ($hasStatus && $holdsOnlyEnvelopeKeys);

        $status = $isEnvelope ? (int) ($response['status'] ?? 200) : 200;
        $body = $isEnvelope ? ($response['body'] ?? []) : $response;
        $headers = $isEnvelope && is_array($response['headers'] ?? null) ? $response['headers'] : [];

        if (! is_array($body)) {
            $body = [];
        }

        $this->responses[$this->signatureKey($method, $path)] = [
            'method' => $method,
            'path' => $this->normalizePath($path),
            'status' => $status,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    private function registerHttpFake(): void
    {
        Http::fake(fn(Request $request) => $this->handle($request));
    }

    private function handle(Request $request): ?\GuzzleHttp\Promise\PromiseInterface
    {
        if (! $this->isPolarRequest($request)) {
            return null;
        }

        $method = strtoupper($request->method());
        $path = $this->requestPath($request);

        foreach ($this->responses as $registered) {
            if (! $this->methodMatches($registered['method'], $method)) {
                continue;
            }

            if ($this->pathMatches($path, $registered['path'])) {
                return Http::response($registered['body'], $registered['status'], $registered['headers']);
            }
        }

        $this->unmatched[] = [
            'method' => $method,
            'path' => $path,
            'url' => $request->url(),
        ];

        return Http::response([
            'detail' => "No Polar fake response registered for {$method} {$path}",
        ], 404);
    }

    /**
     * @return list<array{0: Request, 1: \Illuminate\Http\Client\Response}>
     */
    private function matchingRecorded(string $method, string $path, ?callable $callback = null): array
    {
        $matches = [];

        foreach ($this->recorded() as $pair) {
            [$request, $response] = $pair;

            if (! $this->methodMatches($method, strtoupper($request->method()))) {
                continue;
            }

            if (! $this->pathMatches($this->requestPath($request), $path)) {
                continue;
            }

            if ($callback !== null && ! $callback($request, $response)) {
                continue;
            }

            $matches[] = $pair;
        }

        return $matches;
    }

    private function methodMatches(string $expected, string $actual): bool
    {
        $expected = strtoupper($expected);

        return $expected === '*' || $expected === strtoupper($actual);
    }

    private function pathMatches(string $path, string $pattern): bool
    {
        $path = $this->normalizePath($path);
        $pattern = $this->normalizePath($pattern);

        $regex = '#' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';

        return (bool) preg_match($regex, $path);
    }

    private function isPolarRequest(Request $request): bool
    {
        $requestHost = parse_url($request->url(), PHP_URL_HOST);
        $polarHost = parse_url(PolarClient::resolveBaseUrl(config('polar.server')), PHP_URL_HOST);

        return is_string($requestHost)
            && is_string($polarHost)
            && strcasecmp($requestHost, $polarHost) === 0;
    }

    private function requestPath(Request $request): string
    {
        $path = parse_url($request->url(), PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    private function normalizePath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    private function signatureKey(string $method, string $path): string
    {
        return strtoupper($method) . ' ' . $this->normalizePath($path);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseSignature(string $signature): array
    {
        if (preg_match('/^\s*(\S+)\s+(.+)$/', $signature, $matches) === 1) {
            return [strtoupper($matches[1]), trim($matches[2])];
        }

        return ['*', trim($signature)];
    }

    private function recordedSummary(): string
    {
        $recorded = $this->recorded();

        if ($recorded === []) {
            return 'No requests were recorded.';
        }

        $descriptions = array_map(function (array $pair): string {
            [$request, $response] = $pair;

            return sprintf('%s %s [%d]', strtoupper($request->method()), $this->requestPath($request), $response->status());
        }, $recorded);

        return 'Recorded requests: ' . implode(', ', $descriptions) . '.';
    }
}
