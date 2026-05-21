# Migrating from v2.5 to v2.6

`v2.6.0` is **purely additive** — your existing code continues to work without changes.

## What's new

Five new admin facade methods on `LaravelPolar` for managing checkout links.

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;
use Polar\Models\Operations;

// Create a checkout link (three variants supported by the SDK):
$link = LaravelPolar::createCheckoutLink(new Components\CheckoutLinkCreateProduct(
    productId: 'prod_xxx',
    paymentProcessor: 'stripe',
));

// Or for multiple products / specific price:
LaravelPolar::createCheckoutLink(new Components\CheckoutLinkCreateProducts(...));
LaravelPolar::createCheckoutLink(new Components\CheckoutLinkCreateProductPrice(...));

// Update a checkout link:
LaravelPolar::updateCheckoutLink('cl_xxx', new Components\CheckoutLinkUpdate(
    label: 'Updated label',
));

// Delete a checkout link:
LaravelPolar::deleteCheckoutLink('cl_xxx');

// List checkout links:
$response = LaravelPolar::listCheckoutLinks(); // Operations\CheckoutLinksListResponse
$items = $response->listResourceCheckoutLink?->items ?? [];

// Get a single checkout link:
$link = LaravelPolar::getCheckoutLink('cl_xxx');

// Use the link's URL in your views:
echo $link->url;
```

## Required user actions

None. Pure addition — `composer update danestves/laravel-polar` and the new methods are available.

## Notes

- All five methods re-throw `Polar\Models\Errors\APIException` on non-success status codes.
- `LaravelPolar::createCheckoutLink` expects a `201 Created` from the Polar API.
- `LaravelPolar::deleteCheckoutLink` accepts either `200 OK` or `204 No Content`.
