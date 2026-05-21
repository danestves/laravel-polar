# Changelog

All notable changes to `laravel-polar` will be documented in this file.

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
