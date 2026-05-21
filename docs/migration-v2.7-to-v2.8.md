# Migrating from v2.7 to v2.8

`v2.8.0` is **purely additive** — your existing code continues to work without changes.

## What's new

Two new Cashier-style methods on `Subscription` for managing discounts mid-subscription:

```php
$subscription->applyDiscount('disc_xxx');
$subscription->removeDiscount();
```

Both methods proxy to the SDK's `SubscriptionUpdateDiscount` request and return `$this` (the synced `Subscription` model) so calls chain. The change is applied on the next billing cycle — same Polar semantics as setting `discount_id` directly on the SDK.

`applyDiscount()` mirrors Laravel Cashier's `$subscription->applyCoupon()` ergonomic, finally closing a gap that existed since the v2.5 admin discount CRUD shipped — until now, the package could only apply discounts at checkout, not on an existing subscription.

## Required user actions

None. Pure addition — `composer update danestves/laravel-polar` and the new methods are available.

## Notes

- Polar applies the discount change on the **next billing cycle**, not immediately. There is no SDK option for proration on discount changes today.
- `removeDiscount()` is equivalent to `applyDiscount(null)` at the SDK level; the explicit method exists for code clarity.
- Both methods re-throw `Polar\Models\Errors\APIException` on a non-200 response from Polar, consistent with the rest of the package.
