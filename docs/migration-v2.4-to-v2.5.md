# Migrating from v2.4 to v2.5

`v2.5.0` is **purely additive** — your existing code continues to work without changes.

## What's new

Five new admin facade methods on `LaravelPolar` for managing discount codes. The customer-side application of a discount (`Checkout::withDiscountId()` and `Checkout::withoutDiscountCodes()`) already shipped in v2.0 and is unchanged.

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;
use Polar\Models\Operations;

// Create a discount:
$discount = LaravelPolar::createDiscount(new Components\DiscountPercentageOnceForeverDurationCreate(
    name: 'Black Friday 50%',
    type: Components\DiscountType::Percentage,
    duration: Components\DiscountDuration::Once,
    basisPoints: 5000,
    organizationId: 'org_xxx',
));

// Update a discount:
$discount = LaravelPolar::updateDiscount('disc_xxx', new Components\DiscountUpdate(
    name: 'Black Friday Extended',
));

// Delete a discount:
LaravelPolar::deleteDiscount('disc_xxx');

// List discounts:
$response = LaravelPolar::listDiscounts(); // Operations\DiscountsListResponse
$items = $response->listResourceDiscount?->items ?? [];

// Get a single discount:
$discount = LaravelPolar::getDiscount('disc_xxx');
```

The four create variants supported by the SDK are `DiscountFixedOnceForeverDurationCreate`, `DiscountFixedRepeatDurationCreate`, `DiscountPercentageOnceForeverDurationCreate`, and `DiscountPercentageRepeatDurationCreate`. The returned discount type is the corresponding non-`Create` variant.

## Required user actions

None. Pure addition — `composer update danestves/laravel-polar` and the new methods are available.

## Notes

- All five methods re-throw `Polar\Models\Errors\APIException` on non-success status codes, consistent with the rest of the package.
- `LaravelPolar::createDiscount` expects a `201 Created` from the Polar API.
- `LaravelPolar::deleteDiscount` accepts either `200 OK` or `204 No Content` from the Polar API.
