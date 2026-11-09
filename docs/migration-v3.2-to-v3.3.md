# Migrating from v3.2 to v3.3

`v3.3` is an additive release. No existing public method was renamed or removed, and existing webhook handlers keep working. There is one migration to publish and run, plus optional configuration.

## 1. Publish and run the migration

The subscription and order tables gain lifecycle audit columns so pause, cancellation and ordering state can be queried locally:

- `polar_subscriptions`: `discount_id`, `current_period_start`, `cancel_at_period_end`, `canceled_at`, `pause_at_period_end`, `paused_at`, `resumes_at`, `started_at`, `past_due_at`, `polar_updated_at_us`
- `polar_orders`: `polar_updated_at_us`

The migration guards every column with `Schema::hasColumn()`, so a fresh install that already ran the updated create migration is safe.

```bash
php artisan vendor:publish --tag="polar-migrations"
php artisan migrate
```

## 2. Enable the new webhook events (optional)

`v3.3` adds handlers for the rest of Polar's event catalogue:

- `order.paid`, `order.refunded`
- `subscription.uncanceled`, `subscription.cycled`, `subscription.past_due`, `subscription.paused`, `subscription.resumed`, `subscription.migrated`
- `benefit_grant.cycled`
- `refund.created`, `refund.updated`
- `discount.created`, `discount.updated`, `discount.deleted`
- `member.created`, `member.updated`, `member.deleted`
- `customer_seat.assigned`, `customer_seat.claimed`, `customer_seat.revoked`
- `organization.updated`

Add the ones you need to your Polar webhook configuration. The existing `subscription.updated` catch-all still fires for most subscription transitions, so this is opt-in.

## 3. Configure retries (optional)

The HTTP client can retry `429` and `5xx` responses:

```dotenv
POLAR_RETRY_TIMES=3
POLAR_RETRY_SLEEP=100
```

`POLAR_RETRY_TIMES` counts retries after the first attempt (so `3` means up to four attempts). `POLAR_RETRY_SLEEP` is in milliseconds. Leave `POLAR_RETRY_TIMES` at `0` to keep the previous no-retry behavior. Retries apply to all requests, including POST; if you create checkouts in a loop and need strictly non-retrying writes, keep retries disabled.

## 4. Webhook ordering and idempotency

Polar does not guarantee webhook delivery order. `Subscription::sync()` and `Order::sync()` now compare the payload's `modified_at` with the local `polar_updated_at_us` and ignore events older than the state already stored. Re-delivered `order.created` events update the existing order instead of creating a duplicate.

No action is required; the first event after upgrading populates `polar_updated_at_us`.

## 5. New APIs are additive

These are new and do not change existing behavior:

- `Subscription::cancelNow()`, `revoke()`, `pause()`, `unpause()`, `resumePaused()`, `paused()`, `scopePaused()`, `onPauseGracePeriod()`
- `LaravelPolar::getCustomer()`, `createCustomer()`, `updateCustomer()`, `deleteCustomer()`, `listCustomers()`, `getCustomerByExternalId()`, `getCustomerState()`
- `LaravelPolar::getSubscription()`, `listSubscriptions()`, `listOrders()`, `getProduct()`
- `LaravelPolar::listMeters()`, `getMeter()`, `createMeter()`, `updateMeter()`
- `Billable::createAsPolarCustomer()`, `syncWithPolar()`, `customerState()`, `hasPolarCustomer()`
- `LaravelPolar::fake()` and `LaravelPolar::fakeWebhook()` for application tests
- `php artisan polar:sync` to backfill local orders and subscriptions
