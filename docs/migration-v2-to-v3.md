# Migrating from v2 to v3

`v3.0.0` removes the `polar-sh/sdk` dependency. Polar [deprecated their PHP SDK][deprecation] in July 2026 and now recommend calling the API over plain HTTP, so this package does exactly that.

Everything the SDK used to give you — typed request bodies, typed responses, typed webhook payloads — is still here. The classes moved namespace, and a few shapes changed because the package is now generated against Polar's current API version (`2026-04`) rather than whatever the last SDK release targeted.

[deprecation]: https://github.com/polarsource/polar-php

## The short version

1. Replace `Polar\Models\Components\X` with `Danestves\LaravelPolar\Data\X`.
2. Replace enums (`OrderStatus`, `SubscriptionStatus`, `RefundReason`, …) with `Danestves\LaravelPolar\Enums\X`.
3. Replace `Operations\XListRequest` objects with plain arrays of query parameters.
4. Replace `$response->listResourceX->items` with iterating the returned `Page`.
5. Replace `LaravelPolar::sdk()` with `LaravelPolar::client()`.
6. Catch `Danestves\LaravelPolar\Exceptions\PolarApiError` instead of `Polar\Models\Errors\APIException`.

Nothing changes in your database, your webhook route, your `Billable` trait usage, or your config — apart from two new optional config keys.

## Namespaces

| v2 | v3 |
| --- | --- |
| `Polar\Models\Components\Checkout` | `Danestves\LaravelPolar\Data\Checkout` |
| `Polar\Models\Components\Subscription` | `Danestves\LaravelPolar\Data\Subscription` |
| `Polar\Models\Components\WebhookOrderCreatedPayload` | `Danestves\LaravelPolar\Data\WebhookOrderCreatedPayload` |
| `Polar\Models\Components\OrderStatus` | `Danestves\LaravelPolar\Enums\OrderStatus` |
| `Polar\Models\Components\SubscriptionStatus` | `Danestves\LaravelPolar\Enums\SubscriptionStatus` |
| `Polar\Models\Errors\APIException` | `Danestves\LaravelPolar\Exceptions\PolarApiError` |

Class names and property names are unchanged, so in most files this is a find-and-replace on the `use` statements. Properties are still camelCase (`$subscription->currentPeriodEnd`), and dates are now `Carbon\CarbonImmutable` rather than `DateTime`.

## Listing endpoints

The SDK returned a paginator you had to unwrap. List methods now take an array of query parameters and return a `Page`:

```php
// v2
use Polar\Models\Operations;

$response = LaravelPolar::listProducts(new Operations\ProductsListRequest(
    organizationId: 'org_xxx',
    limit: 50,
));
$products = $response->listResourceProduct->items;

// v3
$page = LaravelPolar::listProducts([
    'organization_id' => 'org_xxx',
    'limit' => 50,
]);

foreach ($page as $product) {
    echo $product->name;
}
```

`Page` is countable and iterable, and exposes `->items`, `->collect()`, `->first()`, `->pagination->totalCount`, `->pagination->maxPage`, and `->hasMorePages($currentPage)`.

Query parameters use Polar's own snake_case names. Array filters are sent as repeated keys (`?id=a&id=b`), which is what the API expects.

Methods affected: `listProducts`, `listBenefits`, `listBenefitGrants`, `listCustomerMeters`, `listFiles`, `listOrganizations`, `listLicenseKeys`, `listCustomFields`, `listCheckoutLinks`, `listDiscounts`, `listRefunds`.

Two signatures changed beyond that:

```php
// v2
LaravelPolar::listBenefitGrants(new Operations\BenefitsGrantsRequest(id: 'ben_xxx'));
LaravelPolar::getMetrics(new Operations\MetricsGetRequest(...));

// v3
LaravelPolar::listBenefitGrants('ben_xxx');
LaravelPolar::getMetrics([
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
    'interval' => 'day',
]);
```

## Errors

Every non-2xx response throws `PolarApiError`, which carries the status and the decoded body instead of hiding them in a message:

```php
use Danestves\LaravelPolar\Exceptions\PolarApiError;

try {
    $checkout = $user->checkout(['product_xxx'])->url();
} catch (PolarApiError $e) {
    $e->status;   // 422
    $e->body;     // ['detail' => [...]] — Polar's own error payload
    $e->getMessage();
}
```

In v2 several wrappers turned a failed call into a generic `APIException` with a fixed `500`. They now surface the real status.

## API shape changes

These come from Polar's API itself, not from this package. They are the changes most likely to need a code edit.

**Subscription updates are one request type.** Polar merged the product, discount, and trial update bodies into a single `SubscriptionUpdateBase`. If you called `LaravelPolar::updateSubscription()` directly:

```php
// v2
LaravelPolar::updateSubscription('sub_xxx', new Components\SubscriptionUpdateProduct(
    productId: 'prod_xxx',
));

// v3
LaravelPolar::updateSubscription('sub_xxx', new Data\SubscriptionUpdateBase(
    productId: 'prod_xxx',
));
```

The `Subscription` model methods (`swap`, `swapAndInvoice`, `cancel`, `resume`, `applyDiscount`, `removeDiscount`, `updateTrial`) are unchanged.

**Clearing a field needs an explicit null.** Polar reads an absent key as "leave unchanged" and an explicit `null` as "clear this". The client drops nulls by default; pass `keepNulls: true` when you mean to clear:

```php
LaravelPolar::updateSubscription('sub_xxx', ['discount_id' => null], keepNulls: true);
```

`$subscription->removeDiscount()` already does this for you.

**Discount create variants collapsed from four to two.** `DiscountFixedOnceForeverDurationCreate` / `DiscountFixedRepeatDurationCreate` / `DiscountPercentageOnceForeverDurationCreate` / `DiscountPercentageRepeatDurationCreate` are now `DiscountFixedCreate` and `DiscountPercentageCreate`, each taking a `DiscountDuration`:

```php
new Data\DiscountPercentageCreate(
    name: 'Black Friday 50%',
    duration: DiscountDuration::Once,
    basisPoints: 5000,
    organizationId: 'org_xxx',
);
```

**Refund reasons split in two.** Polar accepts six reasons when creating a refund but can report a seventh (`dispute_prevention`) on an existing one, so there are two enums. `Order::refund()` takes `Enums\RefundCreateReason`; a `Data\Refund` you read back exposes `Enums\RefundReason`.

**`CheckoutStatus::Completed` is gone**, replaced by `Succeeded`.

**Invoices are fetched in two steps.** `$order->receiptUrl()` now asks Polar to generate the invoice and then reads the URL back, because generation is asynchronous on their side. It returns `null` while generation is still pending — call it again shortly after. Behaviour is otherwise unchanged.

**License key verification uses the public routes.** `validateLicenseKey`, `activateLicenseKey`, and `deactivateLicenseKey` now call `/v1/customer-portal/license-keys/*`, which need no access token — only an organization id, exactly as documented in v2. These routes are rate limited to 3 requests per second.

## Reaching unwrapped endpoints

`LaravelPolar::sdk()` is gone. Use `LaravelPolar::client()`, which handles auth, the base URL, the pinned API version, and error mapping:

```php
// v2
LaravelPolar::sdk()->files->create(...);

// v3
LaravelPolar::client()->post('/v1/files/', ['name' => 'guide.pdf', 'organization_id' => 'org_xxx']);
```

`get()`, `post()`, `patch()`, and `delete()` return the decoded body as an array. `page()` hydrates a paginated response into typed objects. To type a single response, pass the array to the matching data class: `Data\Organization::from($client->get('/v1/organizations/org_xxx'))`.

The container binding changed to match: `Polar\Polar` / `polar.sdk` are now `Danestves\LaravelPolar\Http\PolarClient` / `polar.client`.

## Testing

Because the package uses Laravel's HTTP client, `Http::fake()` now works on Polar calls:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    'https://sandbox-api.polar.sh/v1/checkouts/' => Http::response(['url' => 'https://polar.sh/checkout/x'], 201),
]);
```

The `LaravelPolar::setSdk()` / `resetSdk()` test helpers became `setClient()` / `resetClient()`, though with `Http::fake()` you rarely need them.

## New config keys

Two optional keys were added; the defaults are fine for most applications.

```php
// config/polar.php
'version' => env('POLAR_API_VERSION', \Danestves\LaravelPolar\Http\PolarClient::API_VERSION),
'timeout' => env('POLAR_TIMEOUT'),
```

`version` pins the Polar API version this package's data objects were generated against and is sent on every request, so a change to Polar's default cannot silently reshape your responses. Only change it if you regenerate the data objects (see below).

## Regenerating the data objects

The `Data` and `Enums` classes are generated from Polar's published OpenAPI document and committed to the repository. When Polar ships API changes:

```bash
composer generate-data
```

This reads `https://api.polar.sh/openapi.json`, rewrites `src/Data` and `src/Enums`, refreshes the test fixtures, and formats the result. Pass a path or URL as an argument to generate against a specific document.
