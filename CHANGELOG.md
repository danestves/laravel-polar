# Changelog

All notable changes to `laravel-polar` will be documented in this file.

## v3.1.0 - 2026-09-02

`v3.1.0` fixes product price hydration. On `v3.0.0`, any product or checkout carrying a price that is not a legacy recurring price failed to parse — including every seat-based product.

### Fixes

**Modern product prices could not be hydrated** ([#90](https://github.com/danestves/laravel-polar/issues/90)). Polar returns prices as `LegacyRecurringProductPrice|ProductPrice`, and both halves of that union discriminate on `amount_type`. spatie/laravel-data resolves a union-typed property to its *first* data class and never tries the others, so every price was handed to `LegacyRecurringProductPrice::morph()` — which only knows `custom` and `fixed`:

| price | on `v3.0.0` |
| --- | --- |
| `seat_based` | `CannotCreateAbstractClass` |
| `metered_unit` | `CannotCreateAbstractClass` |
| `custom` | `TypeError` on `LegacyRecurringProductPriceCustom::$minimumAmount` |
| `fixed` | silently hydrated as a *legacy* price, with `type`, `recurring_interval` and `legacy` left uninitialised |

This hit `LaravelPolar::createCheckout()`, `Product`, `CheckoutLink`, `Subscription`, and the `checkout.created` / `checkout.updated` typed webhooks. Only legacy prices carry `type: "recurring"`, so the base class now keys on that field as well and hands everything else to `ProductPrice::morph()`. All six price shapes are covered by tests.

### Changes

- **`spatie/laravel-data` minimum raised to `^4.19`.** Optional morph keys need the resolver guard added in 4.19.0; on 4.18 and below a supplied discriminator gets overwritten by its default. Run `composer update spatie/laravel-data` if you are pinned lower.
- **`composer generate-data` now reads `https://docs.polar.sh/openapi.json`.** The old `https://api.polar.sh/openapi.json` returns 404. Still API version `2026-04`.
- **`SubscriptionUpdateBase` no longer accepts `metadata`.** Polar dropped the field from the subscription update body, so passing it did nothing. The `Subscription` model methods (`swap`, `cancel`, `applyDiscount`, `updateTrial`, …) are unaffected.

### Upgrading

```bash
composer update danestves/laravel-polar

```
No migrations, no config changes. If you construct `Data\SubscriptionUpdateBase` directly with `metadata:`, drop that argument.

## v3.0.0 - 2026-08-18

`v3.0.0` removes the deprecated `polar-sh/sdk` dependency. Polar [deprecated their PHP SDK](https://github.com/polarsource/polar-php) in July 2026 and now recommend calling the API directly over HTTP, so that is what this package does.

Everything the SDK gave you — typed request bodies, typed responses, typed webhook payloads — is still here. The classes moved namespace, and a few shapes changed because the package is now generated against Polar's current API version (`2026-04`).

**Upgrading:** see the [v2 → v3 migration guide](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2-to-v3.md). There is one migration to publish and run.

### What's new

- **No abandoned dependency.** `composer require danestves/laravel-polar` no longer pulls in a deprecated package.
- **Typed data objects** under `Danestves\LaravelPolar\Data` and enums under `Danestves\LaravelPolar\Enums`, generated from [Polar's published OpenAPI document](https://api.polar.sh/openapi.json) and refreshed with `composer generate-data`. Polar API changes are now a one-command regeneration.
- **The API version is pinned** (`Polar-Version: 2026-04`) and sent on every request, so a change to Polar's default cannot silently reshape your responses.
- **Real errors.** `PolarApiError` carries the actual status and Polar's own error body, where several v2 wrappers reported a fixed `500`.
- **`Http::fake()` works** on Polar calls in your own test suite.
- New optional config keys: `polar.version` and `polar.timeout`.

### Fixes

Three defects surfaced while auditing the code against Polar's current schema:

- **Every `order.created` webhook would have failed.** Polar removed `Order.amount` in favour of a `subtotal_amount` / `net_amount` / `total_amount` breakdown. `polar_orders.amount` is now filled from `net_amount`, the exact equivalent of the old field, so `$order->amount` and `$order->refund()` behave as before.
- **`Order.refunded_at` no longer exists.** Refund timestamps live on the refund now; the column falls back to the order's `modified_at` when it is in a refunded state.
- **Orders with no product could not be recorded.** `product_id` is nullable in Polar's schema but the column was `NOT NULL`. Publish and run the migration to widen it.

### Webhooks survive schema drift

Local records now sync from the raw webhook payload first. If a payload cannot be typed — Polar adding a required field, or an enum value this release predates — the failure is logged and only the typed event is skipped, rather than taking your billing sync down. `WebhookReceived` and `WebhookHandled` still fire. Watch for `Polar webhook payload could not be parsed` in your logs; the fix is `composer generate-data`.

Unknown order/subscription statuses and a missing order amount still fail loudly, because silently storing a wrong status or amount is worse than stopping.

### Breaking changes

- `Polar\Models\Components\*` → `Danestves\LaravelPolar\Data\*`; enums → `Danestves\LaravelPolar\Enums\*`.
- List endpoints take an array of query parameters and return a `Page` instead of an SDK response object.
- `LaravelPolar::sdk()` → `LaravelPolar::client()`.
- `Polar\Models\Errors\APIException` → `Danestves\LaravelPolar\Exceptions\PolarApiError`.
- Discount create variants collapsed from four to two; subscription update bodies merged into `SubscriptionUpdateBase`; `CheckoutStatus::Completed` is now `Succeeded`.
- One database migration to publish and run.

Full detail, including every rename, is in the [migration guide](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2-to-v3.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.13.2...v3.0.0

## v2.13.2 - 2026-05-22

### What's Fixed

* fix(ingestEvents): accept any 2xx as success by @danestves in https://github.com/danestves/laravel-polar/pull/87

`LaravelPolar::ingestEvents()` (and by extension `$user->ingestUsageEvent()` / `$user->ingestUsageEvents()`) was throwing `Failed to ingest events` on every successful call. Polar's `/v1/events/ingest` endpoint actually returns HTTP **200** (not 202), so every successful ingestion was being misreported as failure.

The check is now any 2xx is success — matches HTTP convention and is robust to Polar tweaking the exact code in the future.

See [`docs/migration-v2.13.1-to-v2.13.2.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.13.1-to-v2.13.2.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.13.1...v2.13.2

## v2.13.1 - 2026-05-22

### What's Fixed

* fix(inertia): auto-detect X-Inertia and return Inertia::location() to avoid CORS by @danestves in https://github.com/danestves/laravel-polar/pull/86

`Checkout::toResponse()` and `$user->redirectToCustomerPortal()` now auto-detect Inertia.js requests via the `X-Inertia` header and return `Inertia::location($url)` instead of a plain redirect. Fixes CORS errors in Inertia apps where XHR-based form submissions would fail trying to follow a 303 to `polar.sh`.

```
Access to XMLHttpRequest at 'https://sandbox.polar.sh/checkout/...'
... has been blocked by CORS policy



```
No setup needed — works out of the box when `inertiajs/inertia-laravel` is installed. The package has zero hard dependency on Inertia (detection is gated behind `class_exists()`).

Non-Inertia callers see no behavior change.

See [`docs/migration-v2.13.0-to-v2.13.1.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.13.0-to-v2.13.1.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.13.0...v2.13.1

## v2.12.0 - 2026-05-21

### What's Changed

* feat(advanced): metrics, files list, organizations, and sdk() escape hatch by @danestves in https://github.com/danestves/laravel-polar/pull/82

Closes the v2.x roadmap kicked off with PR #73 (SDK upgrade to polar-sh/sdk ^0.10).

```php
LaravelPolar::getMetrics(new Operations\MetricsGetRequest(...));
LaravelPolar::listFiles();
LaravelPolar::listOrganizations();
LaravelPolar::getOrganization('org_xxx');

// Escape hatch for anything not wrapped:
LaravelPolar::sdk()->...




```
See [`docs/migration-v2.11-to-v2.12.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.11-to-v2.12.md) for the escape-hatch recipe.

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.11.0...v2.12.0

## v2.11.0 - 2026-05-21

### What's Changed

* feat(seats): admin CRUD + Cashier-style Subscription helpers by @danestves in https://github.com/danestves/laravel-polar/pull/81

Team / seat management.

```php
$subscription->seats();                                 // Components\SeatsList
$subscription->assignSeat(email: 'alice@example.com');  // invitation email
$subscription->revokeSeat('seat_xxx');
$subscription->resendSeatInvitation('seat_xxx');

LaravelPolar::listSeats(subscriptionId: 'sub_xxx');
LaravelPolar::assignSeat(new Components\SeatAssign(...));
LaravelPolar::revokeSeat('seat_xxx');
LaravelPolar::resendSeatInvitation('seat_xxx');





```
See [`docs/migration-v2.10-to-v2.11.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.10-to-v2.11.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.10.0...v2.11.0

## v2.10.0 - 2026-05-21

### What's Changed

* feat(receipts): $order->receiptUrl() and $order->downloadInvoice() by @danestves in https://github.com/danestves/laravel-polar/pull/80

Adds Cashier-style invoice/receipt access on the Order model.

```php
$order->receiptUrl();       // ?string, memoized per instance
$order->downloadInvoice();  // RedirectResponse — strict (throws on null)






```
See [`docs/migration-v2.9-to-v2.10.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.9-to-v2.10.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.9.0...v2.10.0

## v2.9.0 - 2026-05-21

### What's Changed

* feat(license-keys): admin CRUD, public validate/activate/deactivate, Billable accessor by @danestves in https://github.com/danestves/laravel-polar/pull/79

Adds License Keys support across all three usage patterns: admin management, end-user validate/activate/deactivate, and a Cashier-style `$user->licenseKeys()` accessor.

```php
LaravelPolar::listLicenseKeys();
LaravelPolar::getLicenseKey('lk_xxx');
LaravelPolar::updateLicenseKey('lk_xxx', new Components\LicenseKeyUpdate(limitActivations: 10));

LaravelPolar::validateLicenseKey('LIC-XXXX-XXXX');
LaravelPolar::activateLicenseKey('LIC-XXXX-XXXX', label: 'MacBook');
LaravelPolar::deactivateLicenseKey('LIC-XXXX-XXXX', activationId: 'act_xxx');

$user->licenseKeys();







```
New optional config: `polar.organization_id` / `POLAR_ORGANIZATION_ID` for the public-facing methods.

See [`docs/migration-v2.8-to-v2.9.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.8-to-v2.9.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.8.0...v2.9.0

## v2.8.0 - 2026-05-21

### What's Changed

* feat(subscription): Cashier-style applyDiscount / removeDiscount by @danestves in https://github.com/danestves/laravel-polar/pull/78

Closes the Cashier-parallel gap from the v2.5 admin Discount CRUD: the package can now apply a discount on an existing subscription, not just at checkout.

```php
$subscription->applyDiscount('disc_xxx');
$subscription->removeDiscount();








```
Both methods return `$this` for chaining and apply on the next billing cycle (Polar's default for discount changes).

See [`docs/migration-v2.7-to-v2.8.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.7-to-v2.8.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.7.0...v2.8.0

## v2.7.0 - 2026-05-21

### What's Changed

* feat(custom-fields): admin CRUD + Order::customFieldData accessor by @danestves in https://github.com/danestves/laravel-polar/pull/77

Adds the admin management surface for Polar custom fields and a memoized read accessor for the custom-field data collected at checkout.

```php
LaravelPolar::createCustomField(new Components\CustomFieldCreateText(slug: 'company', name: 'Company name', properties: new Components\CustomFieldTextProperties()));
LaravelPolar::updateCustomField('cf_xxx', new Components\CustomFieldUpdateText(name: 'New label'));
LaravelPolar::deleteCustomField('cf_xxx');
LaravelPolar::listCustomFields();
LaravelPolar::getCustomField('cf_xxx');

// Read collected data from an Order (fetched on demand, memoized per instance):
$order->customFieldData();









```
See [`docs/migration-v2.6-to-v2.7.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.6-to-v2.7.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.6.0...v2.7.0

## v2.6.0 - 2026-05-21

### What's Changed

* feat(checkout-links): admin CRUD via LaravelPolar facade by @danestves in https://github.com/danestves/laravel-polar/pull/76

Adds five admin facade methods on `LaravelPolar` for managing checkout links.

```php
LaravelPolar::createCheckoutLink(new Components\CheckoutLinkCreateProduct(
productId: 'prod_xxx',
paymentProcessor: 'stripe',
));
LaravelPolar::updateCheckoutLink('cl_xxx', new Components\CheckoutLinkUpdate(label: 'New label'));
LaravelPolar::deleteCheckoutLink('cl_xxx');
LaravelPolar::listCheckoutLinks();
LaravelPolar::getCheckoutLink('cl_xxx');










```
See [`docs/migration-v2.5-to-v2.6.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.5-to-v2.6.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.5.0...v2.6.0

## v2.5.0 - 2026-05-21

### What's Changed

* feat(discounts): admin CRUD via LaravelPolar facade by @danestves in https://github.com/danestves/laravel-polar/pull/75

Adds five admin facade methods on `LaravelPolar` for managing discount codes. Purely additive — no breaking changes.

```php
LaravelPolar::createDiscount(new Components\DiscountPercentageOnceForeverDurationCreate(...));
LaravelPolar::updateDiscount('disc_xxx', new Components\DiscountUpdate(name: 'New name'));
LaravelPolar::deleteDiscount('disc_xxx');
LaravelPolar::listDiscounts();
LaravelPolar::getDiscount('disc_xxx');











```
See [`docs/migration-v2.4-to-v2.5.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.4-to-v2.5.md).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.4.0...v2.5.0

## v2.4.0 - 2026-05-21

### What's Changed

* feat(refunds): issue refunds via $order->refund() by @danestves in https://github.com/danestves/laravel-polar/pull/74

This release adds the ability to issue and list refunds directly from an `Order` model and via the `LaravelPolar` facade. Purely additive — no breaking changes.

```php
use Polar\Models\Components\RefundReason;

$order->refund();                                              // refund the remaining unrefunded amount
$order->refund(amount: 2500, reason: RefundReason::Fraudulent); // partial refund with custom reason
$order->refunds();                                             // Collection of Refund items for this order












```
See [`docs/migration-v2.3-to-v2.4.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.3-to-v2.4.md) for full method signatures.

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.3.0...v2.4.0

## v2.3.0 - 2026-05-21

### What's Changed

* feat: support for polar-sh/sdk v0.10.0 by @danestves in https://github.com/danestves/laravel-polar/pull/73

This release upgrades the underlying `polar-sh/sdk` dependency from `^0.8.0` to `^0.10.0`. The public API of `danestves/laravel-polar` is unchanged — your existing calls to `LaravelPolar::*`, the `Billable` trait, models, and events continue to work the same way.

See [`docs/migration-v2.2-to-v2.3.md`](https://github.com/danestves/laravel-polar/blob/main/docs/migration-v2.2-to-v2.3.md) for a detailed walkthrough of the SDK-level changes (Customer is now a discriminated union, Subscription currentPeriodEnd is non-nullable, new prorationBehavior options, etc.).

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.2.0...v2.3.0

## v2.1.0 - 2026-03-02

### What's Changed

* Add return url for checkouts by @damms005 in https://github.com/danestves/laravel-polar/pull/58
* feat: support for polar-sh/sdk v0.8.0 by @adiologydev in https://github.com/danestves/laravel-polar/pull/60

### New Contributors

* @damms005 made their first contribution in https://github.com/danestves/laravel-polar/pull/58

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.0.4...v2.1.0

## v2.0.4 - 2026-02-26

### What's Changed

* chore(deps): bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 by @dependabot[bot] in https://github.com/danestves/laravel-polar/pull/57
* Update standard-webhooks dependency to stable version by @heyjorgedev in https://github.com/danestves/laravel-polar/pull/56

### New Contributors

* @heyjorgedev made their first contribution in https://github.com/danestves/laravel-polar/pull/56

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.0.3...v2.0.4

## v2.0.3 - 2025-12-10

### What's Changed

* fix: `createCustomerSession` status code by @andrzejchmura in https://github.com/danestves/laravel-polar/pull/52
* fix documentation about embedded checkout by @einenlum in https://github.com/danestves/laravel-polar/pull/53
* fix: specify factories for models by @einenlum in https://github.com/danestves/laravel-polar/pull/51

### New Contributors

* @andrzejchmura made their first contribution in https://github.com/danestves/laravel-polar/pull/52
* @einenlum made their first contribution in https://github.com/danestves/laravel-polar/pull/53

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.0.2...v2.0.3

## v2.0.2 - 2025-12-03

### What's Changed

* fix: handle null parameters spread by @adiologydev in https://github.com/danestves/laravel-polar/pull/49

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.0.1...v2.0.2

## v2.0.1 - 2025-12-02

### What's Changed

* refactor: convert empty arrays to null in metadata handling methods by @adiologydev in https://github.com/danestves/laravel-polar/pull/48

### New Contributors

* @adiologydev made their first contribution in https://github.com/danestves/laravel-polar/pull/48

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v2.0.0...v2.0.1

## v2.0.0 - 2025-12-02

### What's Changed

🎉 **We're excited to announce Laravel Polar v2.0.0!** This major release brings significant improvements, new features, and important breaking changes to align with the latest Polar API and modern PHP/Laravel standards.

### 🚀 What's New

#### Major Features

- **✨ Laravel 11 & 12 Support**: Full support for Laravel 11.x and 12.x with modern Laravel features
- **🔌 Enhanced Webhook Support**: Added 10 new webhook event types for better integration capabilities
- **📊 Benefits Management**: Complete support for Polar Benefits API
- **📈 Customer Meters**: Full support for usage-based billing and customer meters
- **🎯 Improved Checkout API**: Enhanced checkout functionality with custom fields and discount code controls
- **🔧 Polar SDK Integration**: Migrated to use Polar SDK Components for better type safety and API alignment

#### New Webhook Events

This release introduces support for 10 new webhook event types:

- `checkout.created` - Fired when a checkout session is created
- `checkout.updated` - Fired when a checkout session is updated
- `customer.created` - Fired when a customer is created
- `customer.updated` - Fired when a customer is updated
- `customer.deleted` - Fired when a customer is deleted
- `customer.state_changed` - Fired when a customer's state changes
- `product.created` - Fired when a product is created
- `product.updated` - Fired when a product is updated
- `benefit.created` - Fired when a benefit is created
- `benefit.updated` - Fired when a benefit is updated

#### Enhanced Checkout Features

The checkout API now supports additional features:

- **Custom Field Data**: Use `withCustomFieldData()` to pass custom data to checkout sessions
- **Discount Code Control**: Use `withoutDiscountCodes()` to disable discount code input
- **Enhanced Billing Address**: Improved billing address support

#### Code Quality Improvements

- Refactored webhook processing for better error handling
- Improved timestamp parsing and status handling
- Streamlined JSON serialization
- Enhanced test coverage
- Removed redundant code and comments
- Better type safety with Polar SDK Components

### ⚠️ Breaking Changes

#### PHP Version Requirement

**🚨 BREAKING**: Laravel Polar v2 requires **PHP 8.3 or higher**.

If you're running PHP 8.2 or lower, you must upgrade before installing v2.0.0.

#### Updated Dependencies

The following dependencies have been updated:

- `polar-sh/sdk`: `^0.7.0` (previously `^0.6.0`)
- `spatie/laravel-data`: `^4.0` (previously `^3.0`)
- `spatie/laravel-webhook-client`: `^3.0` (previously `^2.0`)

#### Model Casts Method

Laravel Polar v2 uses Laravel 11's new `casts()` method. If you've extended any models (`Order`, `Subscription`, or `Customer`), ensure your custom casts are compatible.

#### Enum to SDK Components Migration

Internal enums have been replaced with Polar SDK Components for better type safety and API alignment. This change is mostly internal, but if you've extended or referenced internal enums, you may need to update your code.

### 📦 Installation

To upgrade to v2.0.0:

```bash
composer require danestves/laravel-polar:^2.0




















```
After installation:

1. **Republish configuration**:
   
   ```bash
   php artisan vendor:publish --tag="polar-config" --force
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   ```
2. **Run migrations** (if any new ones exist):
   
   ```bash
   php artisan migrate
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   
   ```

### 🔄 Migration Guide

For detailed migration instructions, please see our comprehensive [Migration Guide](docs/migration-v1-to-v2.md).

#### Quick Migration Checklist

- [ ] Verify PHP 8.3+ is installed
- [ ] Confirm Laravel 11.x or 12.x
- [ ] Update `composer.json` to require `^2.0`
- [ ] Run `composer update`
- [ ] Republish configuration files
- [ ] Run database migrations
- [ ] Test checkout flow
- [ ] Test subscription management
- [ ] Test webhook handling
- [ ] Review and update any custom code

### 🐛 Bug Fixes

- Fixed typo in README regarding embedded checkout attribute
- Improved error handling in Checkout and LaravelPolar classes
- Enhanced timestamp parsing in webhook processing
- Fixed status assignment in Subscription model
- Improved benefit type handling in webhook processing

### 🔧 Improvements

- Streamlined JSON serialization in webhook processing
- Enhanced error handling throughout the package
- Improved test coverage
- Better code organization and structure
- Updated GitHub Actions workflows
- Removed unused code and dependencies

### 📚 Documentation

- Enhanced README with new webhook events documentation
- Added comprehensive migration guide
- Updated API server configuration documentation
- Improved inline code documentation

### 🙏 Contributors

Thank you to all contributors who helped make this release possible!

### 📖 Full Changelog

For a complete list of changes, see the [CHANGELOG.md](CHANGELOG.md).

### 🔗 Links

- [Documentation](README.md)
- [Migration Guide](docs/migration-v1-to-v2.md)
- [GitHub Repository](https://github.com/danestves/laravel-polar)
- [Polar API Documentation](https://docs.polar.sh)

### 💬 Support

If you encounter any issues during migration:

1. Check the [GitHub Issues](https://github.com/danestves/laravel-polar/issues)
2. Review the [Migration Guide](docs/migration-v1-to-v2.md)
3. Open a new issue with details about your problem


---

**Note**: This is a major release with breaking changes. Please review the migration guide carefully before upgrading in production environments.

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.2.4...v2.0.0

## v1.2.4 - 2025-10-17

### What's Changed

* chore(deps): bump aglipanci/laravel-pint-action from 2.5 to 2.6 by @dependabot[bot] in https://github.com/danestves/laravel-polar/pull/40

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v.1.2.4...v1.2.4

## v.1.2.4 - 2025-08-11

### What's Changed

* Update subscription API method and subscriptionData to match Polar API by @jbardnz in https://github.com/danestves/laravel-polar/pull/38

### New Contributors

* @jbardnz made their first contribution in https://github.com/danestves/laravel-polar/pull/38

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.2.3...v.1.2.4

## v1.2.3 - 2025-07-23

### What's Changed

* fix: ordered_at value by @jmaekki in https://github.com/danestves/laravel-polar/pull/36

### New Contributors

* @jmaekki made their first contribution in https://github.com/danestves/laravel-polar/pull/36

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.2.2...v1.2.3

## v1.2.2 - 2025-07-02

### What's Changed

* fix: correctly transform data to array on subscription by @danestves in https://github.com/danestves/laravel-polar/pull/33

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.2.1...v1.2.2

## v1.2.1 - 2025-06-04

### What's Changed

* fix: undefined subscription type by @danestves in https://github.com/danestves/laravel-polar/pull/30

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.2.0...v1.2.1

## v1.2.0 - 2025-06-04

### What's Changed

* chore(deps): bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 by @dependabot in https://github.com/danestves/laravel-polar/pull/27
* feat: latest polar schema by @danestves in https://github.com/danestves/laravel-polar/pull/28
* chore: update dependencies by @danestves in https://github.com/danestves/laravel-polar/pull/29

### New Contributors

* @dependabot made their first contribution in https://github.com/danestves/laravel-polar/pull/27

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.1.2...v1.2.0

## v1.1.2 - 2025-04-10

### What's Changed

* feat: add Pending status to OrderStatus enum by @danestves in https://github.com/danestves/laravel-polar/pull/21
* fix: update taxId property to allow null values by @danestves in https://github.com/danestves/laravel-polar/pull/22

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.1.1...v1.1.2

## v1.1.1 - 2025-03-16

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.1.0...v1.1.1

## v1.1.0 - 2025-03-11

### What's Changed

* feat: webhook parse and data by @danestves in https://github.com/danestves/laravel-polar/pull/17

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.0.1...v1.1.0

## v1.0.1 - 2025-03-10

### What's Changed

* fix: checkout payload mapping the values by @danestves in https://github.com/danestves/laravel-polar/pull/16

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v1.0.0...v1.0.1

## v1.0.0 - 2025-03-09

As of now, we have rewritten the package to entirely use API calls, it should be working like before, only the core has changed with same functionality

### What's Changed

* feat: rewrite queries to use API calls by @danestves in https://github.com/danestves/laravel-polar/pull/15

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.3.2...v1.0.0

## v0.3.2 - 2025-03-07

### What's Changed

* fix: read all config files by @danestves in https://github.com/danestves/laravel-polar/pull/14

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.3.1...v0.3.2

## v0.3.1 - 2025-03-07

### What's Changed

* fix: do not throw on customer metadata by @danestves in https://github.com/danestves/laravel-polar/pull/12

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.3.0...v0.3.1

## v0.3.0 - 2025-03-07

### What's Changed

* feat: update sdk to latest version by @danestves in https://github.com/danestves/laravel-polar/pull/10
* feat: support customer external id by @danestves in https://github.com/danestves/laravel-polar/pull/11

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.2.0...v0.3.0

## v0.2.0 - 2025-03-03

### What's Changed

* fix: customer metadata wrong assumption by @danestves in https://github.com/danestves/laravel-polar/pull/8
* fix: correct handling of webhooks by @danestves in https://github.com/danestves/laravel-polar/pull/9

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.1.3...v0.2.0

## v0.1.3 - 2025-02-24

### What's Changed

* feat: descriptive name for embed script by @danestves in https://github.com/danestves/laravel-polar/pull/3

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.1.2...v0.1.3

## v0.1.2 - 2025-02-24

### What's Changed

* fix: scape the at character on js link by @danestves in https://github.com/danestves/laravel-polar/pull/2

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.1.1...v0.1.2

## v0.1.1 - 2025-02-24

### What's Changed

* fix: namespaces and add install command by @danestves in https://github.com/danestves/laravel-polar/pull/1

### New Contributors

* @danestves made their first contribution in https://github.com/danestves/laravel-polar/pull/1

**Full Changelog**: https://github.com/danestves/laravel-polar/compare/v0.1.0...v0.1.1

## v0.1.0 - 2025-02-23

🍾  First version of the package, for docs, please refer to the README

**Full Changelog**: https://github.com/danestves/laravel-polar/commits/v0.1.0
