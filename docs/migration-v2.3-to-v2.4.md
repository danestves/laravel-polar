# Migrating from v2.3 to v2.4

`v2.4.0` is **purely additive** — your existing code continues to work without changes.

## What's new

### Issue refunds directly on Orders

Two new methods on `Order`:

```php
use Polar\Models\Components\RefundReason;

// Full refund of remaining unrefunded amount (defaults to RefundReason::CustomerRequest):
$refund = $order->refund();

// Partial refund:
$refund = $order->refund(amount: 2500);

// Fully-customized refund:
$refund = $order->refund(
    amount: 2500,
    reason: RefundReason::Fraudulent,
    comment: 'flagged by risk team',
    metadata: ['ticket' => 'T-42'],
);

// List refunds for this order:
$refunds = $order->refunds(); // Illuminate\Support\Collection<int, \Polar\Models\Components\Refund>
```

### Admin-level facade methods

Two new static methods on `LaravelPolar`:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;
use Polar\Models\Operations;

// Create a refund (general-purpose, requires orderId, reason, amount):
$refund = LaravelPolar::createRefund(new Components\RefundCreate(
    orderId: 'ord_xxx',
    reason: Components\RefundReason::Duplicate,
    amount: 1000,
));

// List refunds with optional filters:
$response = LaravelPolar::listRefunds(); // Operations\RefundsListResponse
$response = LaravelPolar::listRefunds(new Operations\RefundsListRequest(
    orderId: 'ord_xxx',
));
```

## Required user actions

None. Pure addition — `composer update danestves/laravel-polar` and the new methods are available.

## Notes

- `$order->refund()` defaults `amount` to the remaining unrefunded portion (`$order->amount - $order->refunded_amount`) and `reason` to `RefundReason::CustomerRequest`.
- `$order->refund()` throws `\RuntimeException` if the order has no `polar_id` set (i.e. the order has not yet been synced from Polar).
- `$order->refunds()` returns an empty `Collection` if `polar_id` is null (defensive — no Polar call is made).
- Both `createRefund` and `listRefunds` re-throw `Polar\Models\Errors\APIException` on non-success status codes, consistent with the rest of the package.
