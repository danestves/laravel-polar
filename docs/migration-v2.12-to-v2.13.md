# Migrating from v2.12 to v2.13

`v2.13.0` is **purely additive** — your existing code continues to work without changes.

## What's new

Two Cashier-style methods on the `Billable` trait for managing a customer's saved payment methods:

```php
$user->paymentMethods();                  // Collection<int, PaymentMethodCard|PaymentMethodGeneric>
$user->deletePaymentMethod('pm_xxx');     // void
```

Both methods mint a short-lived customer session under the hood, so this works without sharing the org-scoped admin token with the client. The same pattern is used by `$user->licenseKeys()` and `$order->receiptUrl()`.

```php
@foreach ($user->paymentMethods() as $method)
    <li>{{ $method->type }} ending in {{ $method->last4 ?? '?' }}</li>
@endforeach
```

```php
Route::delete('/billing/payment-methods/{pm}', function (Request $request, string $pm) {
    $request->user()->deletePaymentMethod($pm);

    return redirect()->back();
});
```

## Required user actions

None. Pure addition — `composer update danestves/laravel-polar` and the new methods are available.

## Notes

- Items returned by `$user->paymentMethods()` are a union of two SDK types: `Polar\Models\Components\PaymentMethodCard` (cards) and `Polar\Models\Components\PaymentMethodGeneric` (other types). Both expose a `type` discriminator and the fields appropriate to each kind.
- Both methods throw `Danestves\LaravelPolar\Exceptions\InvalidCustomer` when the billable has no associated Polar customer yet — call `createAsCustomer()` first or wait for the first webhook to sync.
- `$user->paymentMethods()` returns an empty `Collection` if Polar returns no items.
- `$user->deletePaymentMethod()` accepts either `200 OK` or `204 No Content` from the Polar API; anything else throws `Polar\Models\Errors\APIException`.
- Adding new payment methods is still done via the customer portal (`$user->redirectToCustomerPortal()`) — Polar does not expose a server-side "add payment method" endpoint, so the package doesn't either.
