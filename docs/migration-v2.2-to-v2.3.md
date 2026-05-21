# Migrating from v2.2 to v2.3

`v2.3.0` upgrades the underlying `polar-sh/sdk` dependency from `^0.8.0` to `^0.10.0`. The public API of `danestves/laravel-polar` is unchanged — your existing calls to `LaravelPolar::*`, the `Billable` trait, models, and events all continue to work the same way.

## What changed under the hood

These changes happened inside `polar-sh/sdk` and are surfaced here only because you might see them if you reach into `LaravelPolar::sdk()` directly:

- **Customer is now a discriminated union.** The SDK removed the monolithic `Polar\Models\Components\Customer` and `Polar\Models\Components\CustomerState` classes. They are now:
  - `CustomerIndividual | CustomerTeam` (discriminator field: `type`, values `'individual'` and `'team'`)
  - `CustomerStateIndividual | CustomerStateTeam` (same discriminator)

  Webhook payloads for `customer.created`, `customer.updated`, `customer.deleted`, `customer.state_changed`, and any nested `customer` object inside benefit-grant payloads now carry a `type` field. The package's `ProcessWebhook` handler has been updated to dispatch on this discriminator; no action required if you rely on the dispatched Laravel events. If you call `LaravelPolar::sdk()->customers->*` directly, expect the new union types in responses.

- **`Subscription->currentPeriodEnd` is non-nullable now.** Previously nullable; now guaranteed to be a `\DateTime`. Internal package code has been updated.

- **Customer Portal list endpoints** (`subscriptions`, `orders`, `licenseKeys`, `downloadables`) no longer accept an `organizationId` filter. The package never exposed that filter, so no user-facing change.

- **A new `402` HTTP status** can be returned by `subscriptions->update` in payment-failure cases. It surfaces as a `Polar\Models\Errors\APIException` as today.

- **New `prorationBehavior: 'next_period'` option** on `SubscriptionUpdateProduct`. Use it via `Subscription::swap()`:

  ```php
  use Polar\Models\Components\SubscriptionProrationBehavior;

  $subscription->swap('prod_xxx', SubscriptionProrationBehavior::NextPeriod);
  ```

  Existing `swap()` and `swapAndInvoice()` callers continue to work unchanged (default proration behavior preserved).

## Required user actions

None. Run `composer update danestves/laravel-polar` and your app continues to work.

## Internal `VERSION` constant

`Danestves\LaravelPolar\LaravelPolar::VERSION` had drifted to `0.3.2` while the package shipped v2.x publicly. It is now `2.3.0`, aligned with the public tag. If you depended on the old value for any reason, switch to reading the Composer-installed version instead:

```php
\Composer\InstalledVersions::getVersion('danestves/laravel-polar');
```

## Reaching into the SDK directly

If you call `LaravelPolar::sdk()` to reach a Polar endpoint not wrapped by this package, review your code against the [polar-sh/sdk v0.10 release notes](https://github.com/polarsource/polar-php/releases/tag/v0.10.0) and v0.9 notes for the specific endpoints you use.
