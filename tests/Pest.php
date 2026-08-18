<?php

use Danestves\LaravelPolar\Http\PolarClient;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Tests\Fixtures\PolarFixtures;
use Danestves\LaravelPolar\Tests\TestCase;
use Illuminate\Support\Facades\Http;

uses(TestCase::class)->in(__DIR__);

/**
 * Absolute URL for a Polar API path, matching the sandbox base URL the tests configure.
 */
function polarUrl(string $path): string
{
    return PolarClient::SANDBOX_URL . '/' . ltrim($path, '/');
}

/**
 * Fake a single Polar endpoint.
 *
 * The path may contain a `*` wildcard, as `Http::fake()` matches on the whole URL.
 *
 * @param  array<string, mixed>|list<mixed>  $body
 */
function fakePolar(string $path, array $body = [], int $status = 200): void
{
    Http::fake([
        polarUrl($path) => Http::response($body, $status),
    ]);
}

/**
 * Fake a Polar list endpoint, wrapping the items in Polar's `{items, pagination}` envelope.
 *
 * @param  list<array<string, mixed>>  $items
 */
function fakePolarList(string $path, array $items, ?int $totalCount = null, int $maxPage = 1): void
{
    fakePolar($path, [
        'items' => $items,
        'pagination' => [
            'total_count' => $totalCount ?? count($items),
            'max_page' => $maxPage,
        ],
    ]);
}

/**
 * Build a spec-valid payload for a Polar schema. See tests/Fixtures/PolarFixtures.php.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function polarFixture(string $schema, array $overrides = []): array
{
    return PolarFixtures::make($schema, $overrides);
}

/**
 * Swap in a specific client instance (rarely needed — prefer `Http::fake()`).
 */
function setLaravelPolarClient(?PolarClient $client): void
{
    LaravelPolar::setClient($client);
}

function resetLaravelPolarClient(): void
{
    LaravelPolar::resetClient();
}
