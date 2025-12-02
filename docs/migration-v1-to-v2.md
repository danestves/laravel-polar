# Migration Guide: v1 to v2

This guide will help you migrate your Laravel application from Laravel Polar v1 to v2.

## Overview

Laravel Polar v2 introduces several improvements and breaking changes to align with the latest Polar API and modern PHP/Laravel features. This guide will walk you through all the necessary changes.

## Requirements

### PHP Version

**Breaking Change:** Laravel Polar v2 requires PHP 8.3 or higher.

```bash
# Check your PHP version
php -v

# If you're using PHP 8.2 or lower, you'll need to upgrade
# PHP 8.3+ is required
```

### Laravel Version

Laravel Polar v2 supports Laravel 10.x, 11.x, and 12.x:

- Laravel 10.0 or higher
- Laravel 11.0 or higher
- Laravel 12.0 or higher

## Installation

### Step 1: Update Composer Dependencies

Update your `composer.json` to require Laravel Polar v2:

```bash
composer require danestves/laravel-polar:^2.0
```

Or manually update your `composer.json`:

```json
{
    "require": {
        "danestves/laravel-polar": "^2.0"
    }
}
```

Then run:

```bash
composer update danestves/laravel-polar
```

### Step 2: Publish Updated Configuration

The configuration file may have been updated. Republish it to ensure you have the latest version:

```bash
php artisan vendor:publish --tag="polar-config" --force
```

Review the updated `config/polar.php` file and merge any new configuration options with your existing settings.

### Step 3: Run Migrations

Check if there are any new migrations:

```bash
php artisan migrate
```

If you've customized the migrations, you may need to review the migration files:

```bash
php artisan vendor:publish --tag="polar-migrations" --force
```

## Breaking Changes

### 1. PHP 8.3+ Required

**Impact:** High

If you're running PHP 8.2 or lower, you must upgrade to PHP 8.3+ before upgrading Laravel Polar.

**Action Required:**
- Upgrade your PHP version to 8.3 or higher
- Update your server configuration
- Test your application thoroughly

### 2. Updated Dependencies

**Impact:** Medium

Laravel Polar v2 updates several dependencies:

- `polar-sh/sdk`: Updated to `^0.7.0`
- `spatie/laravel-data`: Updated to `^4.0`
- `spatie/laravel-webhook-client`: Updated to `^3.0`

**Action Required:**
- Ensure all dependencies are compatible
- Run `composer update` to update all packages
- Review any custom code that interacts with these packages

### 3. Model Casts Method

**Impact:** Low

Laravel Polar v2 uses the new `casts()` method introduced in Laravel 11. If you're using Laravel 10, this is backward compatible. However, if you've extended any models, ensure your custom casts are compatible.

**Before (Laravel 10):**
```php
protected $casts = [
    'status' => OrderStatus::class,
    'ordered_at' => 'datetime',
];
```

**After (Laravel 11+):**
```php
protected function casts(): array
{
    return [
        'status' => OrderStatus::class,
        'ordered_at' => 'datetime',
    ];
}
```

**Action Required:**
- If you've extended `Order`, `Subscription`, or `Customer` models, update your casts accordingly
- The package handles this internally, so no changes needed if you're using the models as-is

### 4. Webhook Event Payload Structure

**Impact:** Low

The webhook event payload structure remains the same, but ensure your event listeners handle the payload correctly.

**Action Required:**
- Review your webhook event listeners
- Ensure they're accessing payload data correctly
- Test webhook handling in your development environment

### 5. Checkout API Changes

**Impact:** Low

The checkout API has been enhanced with new features. Existing code should continue to work, but new features are available.

**New Features Available:**
- `withCustomFieldData()` - Support for custom field data
- `withoutDiscountCodes()` - Disable discount code input
- Enhanced billing address support

**Action Required:**
- Review checkout creation code
- Consider using new features if needed
- No changes required for existing implementations

## Code Changes

### Checkout Creation

The checkout API remains backward compatible. However, new features are available:

**Existing Code (Still Works):**
```php
$checkout = $user->checkout(['product_id_123']);
return $checkout->redirect();
```

**New Features Available:**
```php
$checkout = $user->checkout(['product_id_123'])
    ->withCustomFieldData([
        'custom_field' => 'value',
    ])
    ->withoutDiscountCodes();
```

### Subscription Management

Subscription management remains the same:

```php
// Still works as before
$user->subscription()->swap('product_id_123');
$user->subscription()->cancel();
$user->subscription()->resume();
```

### Benefits Management

Benefits management API remains unchanged:

```php
// Still works as before
$benefits = $user->listBenefits('org-id');
$benefit = $user->getBenefit('benefit-id');
```

### Customer Meters

Customer meters API remains unchanged:

```php
// Still works as before
$user->ingestUsageEvent('event_name', ['property' => 'value']);
$meters = $user->listCustomerMeters();
```

## Configuration

### Environment Variables

No changes to environment variables are required. Continue using:

```env
POLAR_ACCESS_TOKEN=your_access_token
POLAR_WEBHOOK_SECRET=your_webhook_secret
POLAR_PATH=polar
POLAR_CURRENCY_LOCALE=en
```

### CSRF Protection

Ensure your CSRF protection excludes the Polar webhook path:

**Laravel 10:**
```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'polar/*',
];
```

**Laravel 11+:**
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'polar/*',
    ]);
})
```

## Testing

After upgrading, thoroughly test the following:

### 1. Checkout Flow

```php
// Test checkout creation
$checkout = $user->checkout(['product_id']);
assert($checkout instanceof \Danestves\LaravelPolar\Checkout);

// Test checkout redirect
$response = $checkout->redirect();
assert($response instanceof \Illuminate\Http\RedirectResponse);
```

### 2. Subscription Management

```php
// Test subscription status checks
assert($user->subscribed() === true || $user->subscribed() === false);

// Test subscription retrieval
$subscription = $user->subscription();
assert($subscription instanceof \Danestves\LaravelPolar\Subscription);
```

### 3. Webhook Handling

Test that webhooks are still processed correctly:

```php
// Simulate webhook payload
$payload = [
    'type' => 'subscription.created',
    'data' => [...],
];

// Ensure your listeners still work
```

### 4. Orders and Customers

```php
// Test order retrieval
$orders = $user->orders;
assert($orders instanceof \Illuminate\Database\Eloquent\Collection);

// Test customer relationship
$customer = $user->customer;
assert($customer instanceof \Danestves\LaravelPolar\Customer);
```

## Troubleshooting

### Issue: Composer Update Fails

**Solution:**
```bash
# Clear composer cache
composer clear-cache

# Remove vendor directory and composer.lock
rm -rf vendor composer.lock

# Reinstall dependencies
composer install
```

### Issue: PHP Version Error

**Solution:**
- Ensure PHP 8.3+ is installed
- Check your PHP version: `php -v`
- Update your server configuration if needed

### Issue: Migration Errors

**Solution:**
```bash
# Check migration status
php artisan migrate:status

# Rollback if needed
php artisan migrate:rollback

# Run migrations again
php artisan migrate
```

### Issue: Webhook Not Working

**Solution:**
- Verify webhook secret is correct
- Check CSRF protection excludes `polar/*`
- Review webhook event listeners
- Check Laravel logs for errors

## Rollback Plan

If you need to rollback to v1:

```bash
# Update composer.json
composer require danestves/laravel-polar:^1.0

# Run composer update
composer update danestves/laravel-polar

# Clear config cache
php artisan config:clear
```

## Additional Resources

- [Laravel Polar Documentation](../../README.md)
- [Polar API Documentation](https://docs.polar.sh)
- [Laravel Documentation](https://laravel.com/docs)

## Support

If you encounter any issues during migration:

1. Check the [GitHub Issues](https://github.com/danestves/laravel-polar/issues)
2. Review the [CHANGELOG](../../CHANGELOG.md)
3. Open a new issue with details about your problem

## Summary Checklist

- [ ] PHP 8.3+ installed and verified
- [ ] Laravel 10.x, 11.x, or 12.x confirmed
- [ ] Updated `composer.json` to require `^2.0`
- [ ] Ran `composer update`
- [ ] Republished configuration files
- [ ] Ran database migrations
- [ ] Tested checkout flow
- [ ] Tested subscription management
- [ ] Tested webhook handling
- [ ] Reviewed and updated custom code
- [ ] Tested in development environment
- [ ] Deployed to staging environment
- [ ] Verified production deployment

---

**Note:** This migration guide covers the major changes. For specific edge cases or custom implementations, refer to the full documentation or open an issue for support.
