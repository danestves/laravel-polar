<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Exceptions\PolarApiError;
use Danestves\LaravelPolar\Http\PolarClient;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('sends the bearer token, accept header and pinned API version', function () {
    fakePolar('v1/products/*', ['items' => [], 'pagination' => ['total_count' => 0, 'max_page' => 1]]);

    LaravelPolar::listProducts();

    Http::assertSent(function ($request) {
        return $request->hasHeader('Authorization', 'Bearer polar_oat_test')
            && $request->hasHeader('Accept', 'application/json')
            && $request->hasHeader('Polar-Version', PolarClient::API_VERSION);
    });
});

it('targets the sandbox or production host based on config', function (string $server, string $expected) {
    Config::set('polar.server', $server);
    LaravelPolar::resetClient();

    Http::fake([
        '*' => Http::response(['items' => [], 'pagination' => ['total_count' => 0, 'max_page' => 1]]),
    ]);

    LaravelPolar::listProducts();

    Http::assertSent(fn($request) => str_starts_with($request->url(), $expected));
})->with([
    ['sandbox', PolarClient::SANDBOX_URL],
    ['production', PolarClient::PRODUCTION_URL],
]);

it('repeats list filters rather than encoding them as array indexes', function () {
    fakePolar('v1/products/*', ['items' => [], 'pagination' => ['total_count' => 0, 'max_page' => 1]]);

    LaravelPolar::listProducts(['id' => ['prod_a', 'prod_b'], 'is_archived' => false]);

    // FastAPI expects `?id=a&id=b`; PHP's http_build_query would emit `id[0]=a` and 422.
    Http::assertSent(function ($request) {
        return str_contains(urldecode($request->url()), 'id=prod_a&id=prod_b')
            && str_contains(urldecode($request->url()), 'is_archived=false');
    });
});

it('drops null query parameters entirely', function () {
    fakePolar('v1/customer-seats*', polarFixture('SeatsList'));

    LaravelPolar::listSeats(subscriptionId: 'sub_123');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'subscription_id=sub_123')
            && ! str_contains($request->url(), 'order_id');
    });
});

it('hydrates a subscription response into typed data', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription', [
        'id' => 'sub_123',
        'status' => 'active',
        'product_id' => 'prod_abc',
        'amount' => 4200,
    ]));

    $subscription = LaravelPolar::updateSubscription('sub_123', ['product_id' => 'prod_abc']);

    expect($subscription)->toBeInstanceOf(Data\Subscription::class)
        ->and($subscription->id)->toBe('sub_123')
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->amount)->toBe(4200)
        ->and($subscription->createdAt)->toBeInstanceOf(\Carbon\CarbonImmutable::class);
});

it('strips nulls from request bodies by default', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription'));

    LaravelPolar::updateSubscription('sub_123', ['product_id' => 'prod_abc', 'discount_id' => null]);

    Http::assertSent(fn($request) => $request->data() === ['product_id' => 'prod_abc']);
});

it('keeps explicit nulls when asked, so a field can be cleared', function () {
    fakePolar('v1/subscriptions/*', polarFixture('Subscription'));

    LaravelPolar::updateSubscription('sub_123', ['discount_id' => null], keepNulls: true);

    Http::assertSent(fn($request) => $request->data() === ['discount_id' => null]);
});

it('returns a typed page for list endpoints', function () {
    fakePolarList('v1/products/*', [
        polarFixture('Product', ['id' => 'prod_a', 'name' => 'Starter']),
        polarFixture('Product', ['id' => 'prod_b', 'name' => 'Pro']),
    ], totalCount: 12, maxPage: 6);

    $page = LaravelPolar::listProducts();

    expect($page->items)->toHaveCount(2)
        ->and($page->first()->name)->toBe('Starter')
        ->and($page->pagination->totalCount)->toBe(12)
        ->and($page->pagination->maxPage)->toBe(6)
        ->and($page->hasMorePages(currentPage: 1))->toBeTrue()
        ->and($page->hasMorePages(currentPage: 6))->toBeFalse()
        ->and(collect($page)->pluck('id')->all())->toBe(['prod_a', 'prod_b']);
});

it('treats a 204 with no body as success', function () {
    fakePolar('v1/benefits/*', [], 204);

    LaravelPolar::deleteBenefit('ben_123');

    Http::assertSent(fn($request) => $request->method() === 'DELETE');
});

it('raises a PolarApiError carrying the status and decoded body', function () {
    fakePolar('v1/benefits/*', ['detail' => 'Benefit not found'], 404);

    $call = fn() => LaravelPolar::getBenefit('ben_missing');

    expect($call)->toThrow(PolarApiError::class);

    try {
        $call();
    } catch (PolarApiError $e) {
        expect($e->status)->toBe(404)
            ->and($e->body)->toBe(['detail' => 'Benefit not found'])
            ->and($e->getMessage())->toContain('Benefit not found')
            ->and($e->getMessage())->toContain('404');
    }
});

it('flattens FastAPI validation errors into the message', function () {
    fakePolar('v1/checkouts/*', [
        'detail' => [
            ['loc' => ['body', 'products'], 'msg' => 'Field required', 'type' => 'missing'],
        ],
    ], 422);

    try {
        LaravelPolar::createCheckoutSession(['products' => []]);
        expect(false)->toBeTrue('expected a PolarApiError');
    } catch (PolarApiError $e) {
        expect($e->status)->toBe(422)
            ->and($e->getMessage())->toContain('body.products: Field required');
    }
});

it('throws when no access token is configured', function () {
    Config::set('polar.access_token', null);
    LaravelPolar::resetClient();

    expect(fn() => LaravelPolar::listProducts())->toThrow(\Exception::class, 'Polar API key not set.');
});
