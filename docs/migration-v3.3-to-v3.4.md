# Migrating from v3.3 to v3.4

`v3.4` is an additive release. There are two new migrations and one behavior improvement to check.

## 1. Publish and run the new migrations

`unique_polar_orders_polar_id` adds a unique index to `polar_orders.polar_id` so a replayed webhook cannot create a duplicate order. `store_external_id_on_polar_customers` stores the external id used when the billable was created in Polar.

```bash
php artisan vendor:publish --tag="polar-migrations"
php artisan migrate
```

If the table already contains duplicate non-null `polar_id` values, the migration throws instead of deleting order history, so it is not marked as run. De-duplicate those rows and run `php artisan migrate` again.

## 2. Checkout customer identity

`$user->checkout()` now sends one of:

- a stored `external_customer_id`, when the local customer has one;
- `customer_id`, for older linked customers without a stored external id; or
- a stable `external_customer_id` shaped as `morphClass:key`, when no customer is linked.

This aligns the checkout with the customer that webhooks will resolve. If checkout sends `external_customer_id`, `Checkout::withCustomerExternalId()` replaces it. If it sends `customer_id` (an older linked customer without a stored external id), that id wins; use `withCustomerId()` or `syncWithPolar()` to change the link.

## 3. New APIs are additive

- `LaravelPolar::listDisputes()`, `getDispute()`, `acceptDispute()`
- `LaravelPolar::listCustomerWallets()`, `getCustomerWallet()`
- `$user->wallets()`, `$user->wallet($id)`
- `LaravelPolar::listWebhooks()`, `createWebhook()`, `getWebhook()`, `updateWebhook()`, `deleteWebhook()`, `rotateWebhookSecret()`, `listWebhookDeliveries()`, `redeliverWebhookEvent()`

No existing method was renamed or removed.
