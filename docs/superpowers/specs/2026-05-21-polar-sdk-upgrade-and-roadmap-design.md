# Laravel Polar v0.4 — SDK Upgrade & Feature Roadmap

**Status:** Approved
**Date:** 2026-05-21
**Target package version line:** `0.4.x`
**Target SDK version:** `polar-sh/sdk: ^0.10.0`

## Goal

Bring `danestves/laravel-polar` onto the current `polar-sh/sdk` line and broaden the public surface to match Laravel Cashier + `lmsqueezy/laravel` ergonomics, while staying pragmatic about what users actually reach for.

The work is decomposed into one foundational PR (the SDK bump) followed by a ladder of small, independently shippable feature PRs. Each PR is scoped to land in isolation: green CI, no migrations, no breaking changes inside the `0.4` line.

## Core conventions

These apply to every PR in this roadmap.

- **SDK floor.** `polar-sh/sdk: ^0.10.0`. The package pins to the major and re-tests on minor SDK bumps.
- **API style.** Cashier-style. Customer-scoped operations live on the `Billable` trait via small `Manages*` / `Has*` concerns. Admin or global operations live as static methods on the `LaravelPolar` facade (current package convention).
- **Persistence.** Pure proxy. No new tables, no new Eloquent models for any v0.4 feature. Polar is the source of truth; calls hit the SDK on demand. Existing tables (`customers`, `subscriptions`, `orders`) are untouched.
- **Errors.** Re-throw `Polar\Models\Errors\APIException` as today. No new exception types unless an HTTP status genuinely lacks meaningful handling.
- **Events.** Each new wrapped webhook event gets a corresponding Laravel event in `src/Events/` only if Polar emits the underlying webhook.
- **Versioning.** Each PR bumps `LaravelPolar::VERSION` by a semver patch/minor. PR #1 = `0.4.0`. Subsequent feature PRs increment to `0.4.1 … 0.4.N`. No breaking changes are introduced inside the `0.4` line.
- **Release tagging.** After each PR merges to `main`, create a matching annotated git tag (`v0.4.0`, `v0.4.1`, …) on the merge commit and push it to origin. The tag value must equal the `LaravelPolar::VERSION` constant set in that PR. Packagist auto-publishes from tags, so the tag is what makes the release usable.
- **Tests.** Each PR ships Pest tests covering the new public surface, using `LaravelPolar::setSdk()` to inject a mocked SDK.
- **Docs.** Each PR appends a section to `README.md` under the appropriate parent heading and an entry to `CHANGELOG.md`.
- **Client migration notes.** If a PR introduces changes that require any user-side code change to keep working (signature changes, renamed methods, removed config keys, new required arguments, behavior changes that affect existing callers), the PR ships a `docs/migration-vX.Y-to-vA.B.md` file describing each change and the recommended fix. Pure-additive PRs (new methods that existing users can ignore) do not need a migration doc.

## Already shipped (no PR needed)

These items were initially in scope but already exist in the codebase, so they are documented here for reference rather than as PRs:

- **Customer Portal** — `$user->redirectToCustomerPortal()` and `$user->customerPortalUrl()` are already implemented in `src/Concerns/ManagesCustomer.php`.
- **Apply discount at checkout** — `Checkout::withDiscountId(string)` and `Checkout::withoutDiscountCodes()` already exist in `src/Checkout.php`.
- **Set custom field data at checkout** — `Checkout::withCustomFieldData(?array)` already exists in `src/Checkout.php`.
- **Order refund state (read-side)** — `Order` already exposes `refunded()`, `partiallyRefunded()`, `refunded_amount`, `refunded_tax_amount`, `refunded_at` plus `scopeRefunded` / `scopePartiallyRefunded`. PR #4 below only adds the **issuance** action.

## PR ladder

| # | Title | Surface added | Tables | Mirrors |
|---|---|---|---|---|
| 1 | SDK upgrade baseline | none new | none | — |
| 2 | Discounts (admin CRUD) | facade only | none | Cashier + lmsqueezy |
| 3 | License Keys | facade + Billable | none | lmsqueezy |
| 4 | Refunds (issuance) | facade + `$order->refund()` | none | Cashier |
| 5 | Receipts / Invoices | `$order->downloadInvoice()` | none | Cashier |
| 6 | Checkout Links | facade + optional Blade directive | none | Polar-specific |
| 7 | Custom Fields (admin + read accessor) | facade + `$order->customFieldData()` | none | Polar enhancement |
| 8 | Seats / Portal Members | `$subscription->members()` etc. | none | Polar v0.10 new |
| 9 | Advanced (Metrics, Wallets, Files, Orgs) | facade-only thin wrappers | none | — |

## PR details

### PR #1 — SDK upgrade baseline → `v0.4.0`

Pure infrastructure release. No new public API. Existing public surface keeps the same names and signatures wherever possible; only types and internal handling change.

**Breaking changes in `polar-sh/sdk` v0.7.0 → v0.10.0 that touch this package:**

1. Webhook payload `data` shapes changed (breaking) for:
   `checkout_created`, `checkout_updated`,
   `order_created`, `order_updated`, `order_paid`, `order_refunded`,
   `subscription_created`, `subscription_updated`, `subscription_active`, `subscription_canceled`, `subscription_uncanceled`, `subscription_revoked`,
   `benefit_grant_created`, `benefit_grant_updated`, `benefit_grant_revoked`.
2. Subscription endpoints: response shapes changed across `list/get/create/update/revoke`. `update` gained `prorationBehavior: 'next_period'` enum and a `402` error status.
3. Customer Portal: `organizationId` filter removed from `subscriptions->list`, `orders->list`, `licenseKeys->list`, `downloadables->list`. Portal `orders->list/get/update` responses changed.
4. Events API: `events->get/list` response items changed shape.
5. Benefit metadata types removed: `BenefitLicenseKeysSubscriberMetadata`, `BenefitMetadata`, `CustomerUpdateTaxId`.

**PR #1 changes:**

- `composer.json`: bump `polar-sh/sdk` to `^0.10.0`. Run `composer update polar-sh/sdk`.
- `LaravelPolar::VERSION = '0.4.0'`.
- Audit `src/Handlers/ProcessWebhook.php`: walk each typed payload listed above and confirm field access still resolves; fix shape drifts. Each handled event type gets a dedicated test asserting the parsed event-payload shape.
- Audit `src/Subscription.php` accessors/casts against the new `Components\Subscription` response shape.
- `LaravelPolar::updateSubscription()` signature: accept the existing union plus the new `prorationBehavior` field path on `SubscriptionUpdateProduct`. No new method.
- Remove any internal use of `organizationId` filter on Customer Portal list endpoints (none expected — sanity scan).
- Update existing tests against new shapes.
- New file: `docs/migration-v0.3-to-v0.4.md` listing the breaking SDK changes and any user-facing implications (mostly none — the public API of *this package* is preserved).
- `CHANGELOG.md` entry under `## v0.4.0`.

**Out of scope for PR #1:** any new public API, any new event, any model field rename. Bug-for-bug compatible from the user's perspective.

### PR #2 — Discounts (admin CRUD) → `v0.4.1`

Mirrors Cashier's coupon helpers and lmsqueezy's discount helpers. The customer-side application of a discount is already covered by `Checkout::withDiscountId()` and `Checkout::withoutDiscountCodes()`; this PR adds the admin management surface only.

**New file:** `src/Concerns/ManagesDiscounts.php` — mixed into `LaravelPolar` facade.

**New facade methods:**

- `LaravelPolar::createDiscount(Components\DiscountFixedOnceForeverDurationCreate|Components\DiscountFixedRepeatDurationCreate|Components\DiscountPercentageOnceForeverDurationCreate|Components\DiscountPercentageRepeatDurationCreate $request): Components\DiscountFixedOnceForeverDuration|Components\DiscountFixedRepeatDuration|Components\DiscountPercentageOnceForeverDuration|Components\DiscountPercentageRepeatDuration`
- `LaravelPolar::updateDiscount(string $discountId, Components\DiscountUpdate $request)`
- `LaravelPolar::deleteDiscount(string $discountId): void`
- `LaravelPolar::listDiscounts(?Operations\DiscountsListRequest $request = null)`
- `LaravelPolar::getDiscount(string $discountId)`

**Tests:** facade-level CRUD against mocked SDK; error paths for invalid IDs.

### PR #3 — License Keys → `v0.4.2`

Mirrors lmsqueezy's license-key flow. Polar's flagship feature for one-off licenses.

**New files:**

- `src/Concerns/ManagesLicenseKeys.php` (facade piece)
- `src/Concerns/HasLicenseKeys.php` (Billable piece)

**Facade methods (admin, authenticated org token):**

- `LaravelPolar::listLicenseKeys(Operations\LicenseKeysListRequest $request)`
- `LaravelPolar::getLicenseKey(string $licenseKeyId)`
- `LaravelPolar::updateLicenseKey(string $licenseKeyId, Components\LicenseKeyUpdate $request)`

**Facade methods (public verification — no customer auth):**

- `LaravelPolar::validateLicenseKey(string $key, ?string $organizationId = null, ?string $activationId = null, ?array $conditions = null)`
- `LaravelPolar::activateLicenseKey(string $key, string $label, ?string $organizationId = null, ?array $meta = null)`
- `LaravelPolar::deactivateLicenseKey(string $key, string $activationId, ?string $organizationId = null)`

These use the public `LicenseKeys` SDK client (not `PolarLicenseKeys`). If `$organizationId` is omitted, fall back to a new optional config key `polar.organization_id`. Throw a clear exception if neither is set.

**Billable method:** `$user->licenseKeys(): \Illuminate\Support\Collection` — mints a customer session for `$this->customer->polar_id`, then calls `PolarLicenseKeys::list()` with that token, returning a Collection of license-key DTOs.

**Tests:** each facade method against mocked SDK; Billable accessor; config fallback for org id.

### PR #4 — Refunds (issuance) → `v0.4.3`

Mirrors Cashier's `$invoice->refund()` / `$user->refund()`. The read-side already exists on `Order` (`refunded()`, `partiallyRefunded()`, `refunded_amount`, `refunded_at`, scopes). This PR adds the action to issue a refund and to query the refund history.

**New file:** `src/Concerns/ManagesRefunds.php` (facade).

**New facade methods:**

- `LaravelPolar::createRefund(Components\RefundCreate $request): Components\Refund`
- `LaravelPolar::listRefunds(?Operations\RefundsListRequest $request = null)`
- `LaravelPolar::getRefund(string $refundId): Components\Refund`

**New Order methods (`src/Order.php`):**

- `refund(?int $amountMinorUnits = null, ?string $reason = null, ?string $comment = null): Components\Refund` — proxies to `LaravelPolar::createRefund()` with `orderId` pre-filled by `$this->polar_id`. Defaults to a full refund if `$amountMinorUnits` is null.
- `refunds(): \Illuminate\Support\Collection` — proxies to `LaravelPolar::listRefunds()` filtered by `orderId`.

**Tests:** facade CRUD; order helpers; partial vs full refund branching.

### PR #5 — Receipts / Invoices → `v0.4.4`

Mirrors Cashier's `$invoice->downloadInvoice()`.

**New Order methods (`src/Order.php`):**

- `receiptUrl(): ?string` — mints a customer session for the order's customer, then calls `customerPortal->orders->generateInvoice()` and returns the `url` from the response. Cached per request via a memoized property to avoid re-minting.
- `downloadInvoice(): \Illuminate\Http\RedirectResponse` — `redirect()->away($this->receiptUrl())`.

**Tests:** asserts the generated URL is returned; asserts the redirect helper resolves correctly; asserts memoization within a request.

### PR #6 — Checkout Links → `v0.4.5`

Polar-specific, useful for marketing pages, lookalike of lmsqueezy's "buy now" URLs.

**New file:** `src/Concerns/ManagesCheckoutLinks.php` (facade).

**New facade methods:**

- `LaravelPolar::createCheckoutLink(Components\CheckoutLinkCreateProduct|Components\CheckoutLinkCreateProductPrice|Components\CheckoutLinkCreateProducts $request)`
- `LaravelPolar::updateCheckoutLink(string $checkoutLinkId, Components\CheckoutLinkUpdate $request)`
- `LaravelPolar::deleteCheckoutLink(string $checkoutLinkId): void`
- `LaravelPolar::listCheckoutLinks(?Operations\CheckoutLinksListRequest $request = null)`
- `LaravelPolar::getCheckoutLink(string $checkoutLinkId)`

**Optional Blade directive:** `@polarCheckoutLink('link_id')` renders the URL string for inline use. Registered in `LaravelPolarServiceProvider::packageBooted()`. Skip if it's controversial — the helper is a nice-to-have.

**Tests:** facade CRUD; Blade directive resolution.

### PR #7 — Custom Fields (admin + read accessor) → `v0.4.6`

Polar enhancement — define custom fields, capture data at checkout, read it back from the order. The customer-side setter (`Checkout::withCustomFieldData()`) already exists, so this PR only adds the admin CRUD facade and an Order read accessor.

**New file:** `src/Concerns/ManagesCustomFields.php` (facade).

**New facade methods:**

- `LaravelPolar::createCustomField(Components\CustomFieldCreateText|Components\CustomFieldCreateNumber|Components\CustomFieldCreateDate|Components\CustomFieldCreateCheckbox|Components\CustomFieldCreateSelect $request)`
- `LaravelPolar::updateCustomField(string $customFieldId, Components\CustomFieldUpdate $request)`
- `LaravelPolar::deleteCustomField(string $customFieldId): void`
- `LaravelPolar::listCustomFields(?Operations\CustomFieldsListRequest $request = null)`
- `LaravelPolar::getCustomField(string $customFieldId)`

**Order accessor:** `$order->customFieldData(): array` — fetches the order from Polar on demand (the data is not persisted in `polar_orders`) and returns `customFieldData`. Memoized per Order instance. Returns an empty array if no fields were collected.

**Tests:** facade CRUD; order accessor with and without custom fields present; memoization within a request.

### PR #8 — Seats / Portal Members → `v0.4.7`

Polar v0.10 introduced first-class team/member management for subscriptions. Two surface points: admin-managed seats and customer-portal member self-service.

**New files:**

- `src/Concerns/ManagesSeats.php` (admin facade)
- `src/Concerns/HasMembers.php` (mixed into `Subscription`)

**Facade methods (admin):**

- `LaravelPolar::listSeats(Operations\SeatsListRequest $request)`
- `LaravelPolar::assignSeat(Components\SeatAssign $request)`
- `LaravelPolar::revokeSeat(string $seatId)`
- `LaravelPolar::resendSeatInvitation(string $seatId)`

**Subscription methods (customer-portal members):**

- `members(): \Illuminate\Support\Collection` — mints a customer session for `$this->customer->polar_id`, then calls `customerPortal->members->list()`.
- `addMember(string $email)`
- `removeMember(string $memberId)`
- `updateMember(string $memberId, Components\MemberUpdate $request)`

**Tests:** facade CRUD; subscription helpers via mocked customer-session flow.

### PR #9 — Advanced (Metrics / Wallets / Files / Organizations) → `v0.4.8`

Thin facade-only wrappers. Read-mostly. No Billable surface. Pure escape-hatch.

**New file:** `src/Concerns/ManagesAdvanced.php` (facade) — or split into one file per area if it grows.

**New facade methods:**

- `LaravelPolar::listMetrics(?Operations\MetricsGetRequest $request = null)` plus `getMetricsLimits()`.
- `LaravelPolar::listOrganizations(?Operations\OrganizationsListRequest $request = null)`, `LaravelPolar::getOrganization(string $orgId)`.
- `LaravelPolar::listWallets(?Operations\WalletsListRequest $request = null)`.
- `LaravelPolar::listFiles(?Operations\FilesListRequest $request = null)`.

**README:** add a short closing section "For anything not covered here, use `LaravelPolar::sdk()` directly — it returns the underlying `Polar\Polar` client." This is the documented escape hatch.

**Tests:** smoke tests per facade method.

## Out of scope (explicitly)

- OAuth2 client flows — different package concern.
- Local persistence for any of: discounts, license keys, refunds, checkout links, custom fields, seats, files.
- Payments / payment methods — low-level; checkout already covers user payment.
- Downloadables admin — managed via Benefits already.
- Multi-organization-per-environment support — single org per env stays the assumption.

## Risks and open items

- **Webhook payload reshape (PR #1).** The V2 release already restructured payloads; v0.10 reshapes them again. `ProcessWebhook.php` is the riskiest piece. Mitigated by: per-event-type tests asserting the parsed payload shape; explicit type guards on each branch.
- **`prorationBehavior` enum (PR #1).** Existing callers of `updateSubscription` may want a default. Decision: keep current behavior — do not pass the field, let Polar default. Users opting in pass it explicitly via the `SubscriptionUpdateProduct` body.
- **License-key activation requires `organizationId`.** PR #3 introduces optional config key `polar.organization_id`. If unset and not passed as argument, throw a clear exception (not a Polar SDK error).
- **Customer session minting for invoice URLs (PR #5).** Each call issues a short-lived session. Memoize per request to avoid re-minting on the same `$order` instance. Cross-request caching is out of scope.
- **Blade directive (PR #6).** Adds a small bit of magic; if review feedback dislikes it, drop without affecting the rest of the PR.

## Acceptance signals

For each PR:
- `composer test` green.
- `composer lint` clean (Pint + PHPStan level configured in repo).
- `LaravelPolar::VERSION` bumped.
- `CHANGELOG.md` updated.
- `README.md` updated where user-facing surface changed.
- `docs/migration-vX.Y-to-vA.B.md` present iff the PR forces any user-side code change (see Core conventions).
- Tests cover the new public methods and Billable accessors.
- No new migrations.
- No removal or rename of existing public methods within the `0.4` line.

## Release flow after merge

For every PR in this roadmap, the post-merge release flow is:

1. PR is reviewed and merged into `main` via squash or merge commit.
2. Locally on `main`:
   ```bash
   git checkout main
   git pull --ff-only
   VERSION=$(php -r 'require "vendor/autoload.php"; echo Danestves\\LaravelPolar\\LaravelPolar::VERSION;')
   git tag -a "v$VERSION" -m "Release v$VERSION"
   git push origin "v$VERSION"
   ```
3. Packagist picks up the new tag automatically (the danestves/laravel-polar package is configured to auto-update from GitHub). Verify the new version appears on https://packagist.org/packages/danestves/laravel-polar.
4. If the PR shipped a migration doc, announce the release with a link to that doc (release notes, Twitter/X, README badge).

This step does NOT happen inside the upgrade branch / worktree — it happens on `main` after merge. The implementation plan should mention it as a post-merge action.
