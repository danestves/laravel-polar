![](https://banners.beyondco.de/laravel-polar.png?theme=dark&packageManager=composer+require&packageName=danestves%2Flaravel-polar&pattern=pieFactory&style=style_1&description=Easily+integrate+your+Laravel+application+with+Polar.sh&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg "Laravel Polar")

# Laravel Polar

[![Latest Version on Packagist](https://img.shields.io/packagist/v/danestves/laravel-polar.svg?style=flat-square)](https://packagist.org/packages/danestves/laravel-polar)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/danestves/laravel-polar/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/danestves/laravel-polar/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/danestves/laravel-polar/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/danestves/laravel-polar/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/danestves/laravel-polar.svg?style=flat-square)](https://packagist.org/packages/danestves/laravel-polar)

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://polar.sh/embed/subscribe.svg?org=danestves-llc&label=Subscribe&darkmode">
  <img alt="Subscribe on Polar" src="https://polar.sh/embed/subscribe.svg?org=danestves-llc&label=Subscribe" style="width: 104px;">
</picture>

Seamlessly integrate Polar.sh subscriptions and payments into your Laravel application. This package provides an elegant way to handle subscriptions, manage recurring payments, and interact with Polar's API. With built-in support for webhooks, subscription management, and a fluent API, you can focus on building your application while we handle the complexities of subscription billing.

## Installation

**Step 1:** You can install the package via composer:

```bash
composer require danestves/laravel-polar
```

**Step 2:** Run `:install`:

```bash
php artisan polar:install
```

This will publish the config, migrations and views, and ask to run the migrations.

Or publish and run the migrations individually:

```bash
php artisan vendor:publish --tag="polar-migrations"
```

```bash
php artisan vendor:publish --tag="polar-config"
```

```bash
php artisan vendor:publish --tag="polar-views"
```

```bash
php artisan migrate
```

This is the contents of the published config file:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Polar Access Token
    |--------------------------------------------------------------------------
    |
    | The Polar access token is used to authenticate with the Polar API.
    | You can find your access token in the Polar dashboard > Settings
    | under the "Developers" section.
    |
    */
    'access_token' => env('POLAR_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Polar Organization ID
    |--------------------------------------------------------------------------
    |
    | Optional. Some Polar endpoints (notably the public license-key
    | validate / activate / deactivate routes) require an organization id.
    | Setting this once here avoids passing it on every call.
    |
    | If unset, callers must pass the organization id explicitly when invoking
    | LaravelPolar::validateLicenseKey / activateLicenseKey / deactivateLicenseKey.
    |
    */
    'organization_id' => env('POLAR_ORGANIZATION_ID'),

    /*
    |--------------------------------------------------------------------------
    | Polar Server
    |--------------------------------------------------------------------------
    |
    | The Polar server environment to use for API requests.
    | Available options: "production" or "sandbox"
    |
    | - production: https://api.polar.sh (Production environment)
    | - sandbox: https://sandbox-api.polar.sh (Sandbox environment)
    |
    */
    'server' => env('POLAR_SERVER', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Polar Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The Polar webhook secret is used to verify that the webhook requests
    | are coming from Polar. You can find your webhook secret in the Polar
    | dashboard > Settings > Webhooks on each registered webhook.
    |
    | We (the developers) recommend using a single webhook for all your
    | integrations. This way you can use the same secret for all your
    | integrations and you don't have to manage multiple webhooks.
    |
    */
    'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Polar Url Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI where routes from Polar will be served
    | from. The URL built into Polar is used by default; however,
    | you can modify this path as you see fit for your application.
    |
    */
    'path' => env('POLAR_PATH', 'polar'),

    /*
    |--------------------------------------------------------------------------
    | Default Redirect URL
    |--------------------------------------------------------------------------
    |
    | This is the default redirect URL that will be used when a customer
    | is redirected back to your application after completing a purchase
    | from a checkout session in your Polar account.
    |
    */
    'redirect_url' => null,

    /*
    |--------------------------------------------------------------------------
    | Currency Locale
    |--------------------------------------------------------------------------
    |
    | This is the default locale in which your money values are formatted in
    | for display. To utilize other locales besides the default "en" locale
    | verify you have to have the "intl" PHP extension installed on the system.
    |
    */
    'currency_locale' => env('POLAR_CURRENCY_LOCALE', 'en'),
];
```

## Usage

### Access Token

Configure your access token. Create a new token in the Polar Dashboard > Settings > Developers and paste it in the `.env` file.

- https://sandbox.polar.sh/dashboard/<org_slug>/settings (Sandbox)
- https://polar.sh/dashboard/<org_slug>/settings (Production)

```bash
POLAR_ACCESS_TOKEN="<your_access_token>"
```

### Organization ID (optional)

Some endpoints (notably the public-facing License Key methods — `validateLicenseKey`, `activateLicenseKey`, `deactivateLicenseKey`) require an organization id. Set it once in your `.env` and the package will pick it up automatically, or pass it explicitly on every call.

```bash
POLAR_ORGANIZATION_ID="<your_organization_id>"
```

### Webhook Secret

Configure your webhook secret. Create a new webhook in the Polar Dashboard > Settings > Webhooks.

- https://sandbox.polar.sh/dashboard/<org_slug>/settings/webhooks (Sandbox)
- https://polar.sh/dashboard/<org_slug>/settings/webhooks (Production)

Configure the webhook for the following events that this package supports:

- `order.created`
- `order.updated`
- `subscription.created`
- `subscription.updated`
- `subscription.active`
- `subscription.canceled`
- `subscription.revoked`
- `benefit_grant.created`
- `benefit_grant.updated`
- `benefit_grant.revoked`
- `checkout.created`
- `checkout.updated`
- `checkout.expired`
- `customer.created`
- `customer.updated`
- `customer.deleted`
- `customer.state_changed`
- `product.created`
- `product.updated`
- `benefit.created`
- `benefit.updated`

```bash
POLAR_WEBHOOK_SECRET="<your_webhook_secret>"
```

### Billable Trait

Let’s make sure everything’s ready for your customers to checkout smoothly. 🛒

First, we’ll need to set up a model to handle billing—don’t worry, it’s super simple! In most cases, this will be your app’s User model. Just add the Billable trait to your model like this (you’ll import it from the package first, of course):

```php
use Danestves\LaravelPolar\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

Now the user model will have access to the methods provided by the package. You can make any model billable by adding the trait to it, not just the User model.

### Polar Script

Polar includes a JavaScript script that you can use to initialize the [Polar Embedded Checkout](https://docs.polar.sh/features/checkout/embed). If you going to use this functionality, you can use the `@polarEmbedScript` directive to include the script in your views inside the `<head>` tag.

```blade
<head>
    ...

    @polarEmbedScript
</head>
```

### Webhooks

This package includes a webhook handler that will handle the webhooks from Polar.

#### Webhooks & CSRF Protection

Incoming webhooks should not be affected by [CSRF protection](https://laravel.com/docs/csrf). To prevent this, exclude `polar/*` in your application's `bootstrap/app.php` file:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'polar/*',
    ]);
})
```

### Commands

This package includes a list of commands that you can use to retrieve information about your Polar account.

| Command | Description |
|---------|-------------|
| `php artisan polar:products` | List all available products with their ids |
| `php artisan polar:products --id=123` | List a specific product by id |
| `php artisan polar:products --id=123 --id=321` | List a specific products by ids |

### Checkouts

#### Single Payments

To create a checkout to show only a single payment, pass a single items to the array of products when creating the checkout.

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()->checkout(['product_id_123']);
});
```

If you want to show multiple products that the user can choose from, you can pass an array of product ids to the `checkout` method.

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()->checkout(['product_id_123', 'product_id_456']);
});
```

This could be useful if you want to offer monthly, yearly, and lifetime plans for example.

> [!NOTE]
> If you are requesting the checkout a lot of times we recommend you to cache the URL returned by the `checkout` method.

#### Custom Price

You can override the price of a product using the `charge` method.

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()->charge(1000, ['product_id_123']);
});
```

#### Embedded Checkout

Instead of redirecting the user you can create the checkout link, pass it to the page and use our blade component:

```php
use Illuminate\Http\Request;

Route::get('/billing', function (Request $request) {
    $checkout = $request->user()->checkout(['product_id_123'])
        ->withEmbedOrigin(config('app.url'));

    return view('billing', ['checkout' => $checkout]);
});
```

Now we can use the button like this:

```blade
<x-polar-button :checkout="$checkout" />
```

The component accepts the normal props that a link element accepts. You can change the theme of the embedded checkout by using the following prop:

```blade
<x-polar-button :checkout="$checkout" data-polar-checkout-theme="dark" />
```

It defaults to light theme, so you only need to pass the prop if you want to change it.

##### Inertia

For projects usin Inertia you can render the button adding `data-polar-checkout` to the link in the following way:

`button.vue`
```vue
<template>
  <a href="<CHECKOUT_LINK>" data-polar-checkout>Buy now</a>
</template>
```

```jsx
// button.{jsx,tsx}

export function Button() {
  return (
    <a href="<CHECKOUT_LINK>" data-polar-checkout>Buy now</a>
  );
}
```

At the end is just a normal link but using an special attribute for the script to render the embedded checkout.

> [!NOTE]
> Remember that you can use the theme attribute too to change the color system in the checkout

#### Checkout Links

Checkout Links are reusable hosted URLs you can drop into marketing pages, emails, or "Buy now" buttons. They live on Polar's side and don't require a session — share the same URL with anyone. Manage them with the `LaravelPolar` facade:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;

// Create a checkout link for a single product:
$link = LaravelPolar::createCheckoutLink(new Components\CheckoutLinkCreateProduct(
    productId: 'product_id_123',
    paymentProcessor: 'stripe',
));

echo $link->url; // share this anywhere

// Or for multiple products (customer picks one):
LaravelPolar::createCheckoutLink(new Components\CheckoutLinkCreateProducts(/* ... */));

// Or pinned to a specific price:
LaravelPolar::createCheckoutLink(new Components\CheckoutLinkCreateProductPrice(/* ... */));
```

Update, delete, list, and fetch a single link:

```php
LaravelPolar::updateCheckoutLink('cl_xxx', new Components\CheckoutLinkUpdate(label: 'Black Friday'));
LaravelPolar::deleteCheckoutLink('cl_xxx');
LaravelPolar::listCheckoutLinks();           // optional CheckoutLinksListRequest
LaravelPolar::getCheckoutLink('cl_xxx');     // Components\CheckoutLink
```

#### Inertia.js compatibility

The `Checkout` Responsable and `$user->redirectToCustomerPortal()` auto-detect Inertia requests via the `X-Inertia` header and return `Inertia::location($url)` instead of a plain redirect. This avoids CORS errors when Inertia's XHR-based client tries to follow a redirect to an external host like `polar.sh`. No setup needed — works out of the box when `inertiajs/inertia-laravel` is installed.

For non-Inertia callers (Blade, JSON APIs, console), the behavior is unchanged — a standard `RedirectResponse` to the external URL.

If you prefer to handle the redirect yourself, call `$checkout->url()` or `$user->customerPortalUrl()` directly and route the URL however you like.

### Prefill Customer Information

You can override the user data using the following methods in your models provided by the `Billable` trait.

```php
public function polarName(): ?string; // default: $model->name
public function polarEmail(): ?string; // default: $model->email
```

### Redirects After Purchase

You can redirect the user to a custom page after the purchase using the `withSuccessUrl` method:

```php
$request->user()->checkout('variant-id')
    ->withSuccessUrl(url('/success'));
```

You can also add the `checkout_id={CHECKOUT_ID}` query parameter to the URL to retrieve the checkout session id:

```php
$request->user()->checkout('variant-id')
    ->withSuccessUrl(url('/success?checkout_id={CHECKOUT_ID}'));
```

### Custom metadata and customer metadata

You can add custom metadata to the checkout session using the `withMetadata` method:

```php
$request->user()->checkout('variant-id')
    ->withMetadata(['key' => 'value']);
```

You can also add customer metadata to the checkout session using the `withCustomerMetadata` method:

```php
$request->user()->checkout('variant-id')
    ->withCustomerMetadata(['key' => 'value']);
```

These will then be available in the relevant webhooks for you.

#### Reserved Keywords

When working with custom data, this library has a few reserved terms.

- `billable_id`
- `billable_type`
- `subscription_type`

Using any of these will result in an exception being thrown.

### Custom Fields

Polar lets you collect arbitrary structured data at checkout — company name, VAT ID, license seat count, anything you need. Define the fields once, attach them at checkout, and read them back from the resulting `Order`.

#### Defining Custom Fields

Create custom field definitions via the `LaravelPolar` facade:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;

$field = LaravelPolar::createCustomField(new Components\CustomFieldCreateText(
    slug: 'company',
    name: 'Company name',
    properties: new Components\CustomFieldTextProperties(),
));
```

The SDK exposes five create variants matching Polar's field types: `CustomFieldCreateText`, `CustomFieldCreateNumber`, `CustomFieldCreateDate`, `CustomFieldCreateCheckbox`, `CustomFieldCreateSelect`.

Manage existing definitions:

```php
LaravelPolar::updateCustomField('cf_xxx', new Components\CustomFieldUpdateText(name: 'Org name'));
LaravelPolar::deleteCustomField('cf_xxx');
LaravelPolar::listCustomFields();
LaravelPolar::getCustomField('cf_xxx');
```

#### Collecting Custom Field Data at Checkout

Attach values when starting a checkout:

```php
$user->checkout('product_id_123')
    ->withCustomFieldData([
        'company' => 'Acme, Inc.',
        'seats' => 10,
    ]);
```

#### Reading Custom Field Data from an Order

After purchase, retrieve the captured values from the `Order`:

```php
$data = $order->customFieldData(); // array<string, string|int|bool|\DateTime|null>
```

The data is fetched from Polar on demand (not persisted in `polar_orders`) and memoized on the Order instance for the lifetime of the request, so calling it twice in the same controller only hits the API once.

### Discounts

Discounts are reusable coupon codes you can either apply automatically at checkout or hand to a customer to enter themselves.

#### Managing Discount Codes

Create, update, delete, list, and fetch discounts via the `LaravelPolar` facade:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;

$discount = LaravelPolar::createDiscount(new Components\DiscountPercentageOnceForeverDurationCreate(
    name: 'Black Friday 50%',
    type: Components\DiscountType::Percentage,
    duration: Components\DiscountDuration::Once,
    basisPoints: 5000,
    organizationId: 'your-org-id',
));

LaravelPolar::updateDiscount('disc_xxx', new Components\DiscountUpdate(name: 'Black Friday extended'));
LaravelPolar::deleteDiscount('disc_xxx');
LaravelPolar::listDiscounts();
LaravelPolar::getDiscount('disc_xxx');
```

The SDK offers four create variants: `DiscountFixedOnceForeverDurationCreate`, `DiscountFixedRepeatDurationCreate`, `DiscountPercentageOnceForeverDurationCreate`, `DiscountPercentageRepeatDurationCreate`.

#### Applying a Discount at Checkout

Pin a discount to a checkout session:

```php
$user->checkout('product_id_123')
    ->withDiscountId('disc_xxx');
```

To accept customer-entered discount codes at checkout (the default), no extra setup is needed. To force a specific discount and prevent the customer from changing it:

```php
$user->checkout('product_id_123')
    ->withDiscountId('disc_xxx')
    ->withoutDiscountCodes();
```

#### Applying a Discount to an Existing Subscription

Apply or remove a discount on an active subscription. The change takes effect on the next billing cycle:

```php
$user->subscription()->applyDiscount('disc_xxx');
$user->subscription()->removeDiscount();
```

### Customers

#### Customer Portal

Customers can update their personal information (e.g., name, email address) by accessing their [self-service customer portal](https://docs.polar.sh/features/customer-portal). To redirect customers to this portal, call the `redirectToCustomerPortal()` method on your billable model (e.g., the User model).

```php
use Illuminate\Http\Request;

Route::get('/customer-portal', function (Request $request) {
    return $request->user()->redirectToCustomerPortal();
});
```

Optionally, you can obtain the signed customer portal URL directly:

```php
$url = $user->customerPortalUrl();
```

#### Payment Methods

List a customer's saved payment methods:

```php
$methods = $user->paymentMethods(); // Collection<int, PaymentMethodCard|PaymentMethodGeneric>

foreach ($methods as $method) {
    // $method->type is the discriminator: 'card' for PaymentMethodCard, etc.
    // PaymentMethodCard exposes brand / last4 / expMonth / expYear etc.
}
```

Delete one:

```php
$user->deletePaymentMethod('pm_xxx');
```

Both methods mint a short-lived customer session under the hood, so they work without sharing your org-scoped admin token with the client.

> [!NOTE]
> Polar does not expose a server-side "add payment method" endpoint — customers add new payment methods through the customer portal. Send them there with `$user->redirectToCustomerPortal()`.

Both methods throw `Danestves\LaravelPolar\Exceptions\InvalidCustomer` if the billable has no associated Polar customer yet.

### Orders

#### Retrieving Orders

You can retrieve orders by using the `orders` relationship on the billable model:

```blade
<table>
    @foreach ($user->orders as $order)
        <td>{{ $order->ordered_at->toFormattedDateString() }}</td>
        <td>{{ $order->polar_id }}</td>
        <td>{{ $order->amount }}</td>
        <td>{{ $order->tax_amount }}</td>
        <td>{{ $order->refunded_amount }}</td>
        <td>{{ $order->refunded_tax_amount }}</td>
        <td>{{ $order->currency }}</td>
        <!-- Add more columns as needed -->
    @endforeach
</table>
```

#### Check order status

You can check the status of an order by using the `status` attribute:

```php
$order->status;
```

Or you can use some of the helper methods offers by the `Order` model:

```php
$order->paid();
```

Aside from that, you can run two other checks: refunded, and partially refunded.  If the order is refunded, you can utilize the refunded_at timestamp:

```blade
@if ($order->refunded())
    Order {{ $order->polar_id }} was refunded on {{ $order->refunded_at->toFormattedDateString() }}
@endif
```

You may also see if an order was for a certain product:

```php
if ($order->hasProduct('product_id_123')) {
    // ...
}
```

Furthermore, you can check if a consumer has purchased a specific product:

```php
if ($user->hasPurchasedProduct('product_id_123')) {
    // ...
}
```

#### Refunding Orders

Issue a refund directly from an `Order` instance. By default, `refund()` refunds the remaining unrefunded amount with reason `customer_request`:

```php
$order->refund();
```

For a partial refund, pass an explicit amount (in minor units / cents):

```php
$order->refund(amount: 2500);
```

Pass a reason, comment, and metadata to fully describe a refund:

```php
use Polar\Models\Components\RefundReason;

$order->refund(
    amount: 2500,
    reason: RefundReason::Fraudulent,
    comment: 'flagged by risk team',
    metadata: ['ticket' => 'T-42'],
);
```

List previous refunds for an order as a Collection:

```php
$refunds = $order->refunds(); // Illuminate\Support\Collection<int, \Polar\Models\Components\Refund>
```

For admin / cross-order refund management, use the facade directly:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;
use Polar\Models\Operations;

LaravelPolar::createRefund(new Components\RefundCreate(
    orderId: 'ord_xxx',
    reason: Components\RefundReason::Duplicate,
    amount: 1000,
));

LaravelPolar::listRefunds(new Operations\RefundsListRequest(orderId: 'ord_xxx'));
```

#### Receipts / Invoices

Polar generates PDF invoices on demand for each order. The `Order` model exposes Cashier-style helpers to surface them to customers:

```php
// Get the URL (e.g. for an <a href> in a Blade view):
$url = $order->receiptUrl(); // ?string, memoized per Order instance

// Or redirect directly (for a controller route):
return $order->downloadInvoice(); // Illuminate\Http\RedirectResponse
```

`receiptUrl()` mints a short-lived customer session, calls Polar's generate-invoice endpoint, and reads the URL from the response. The result is memoized on the Order instance, so calling it twice in the same request only hits Polar once.

```blade
@if ($order->receiptUrl())
    <a href="{{ $order->receiptUrl() }}">Download invoice</a>
@endif
```

```php
Route::get('/orders/{order}/invoice', function (Order $order) {
    return $order->downloadInvoice(); // throws RuntimeException if no URL is available
});
```

### Subscriptions

#### Creating Subscriptions

Starting a subscription is simple. For this, we require our product's variant id. Copy the product id and start a new subscription checkout using your billable model:

```php
use Illuminate\Http\Request;

Route::get('/subscribe', function (Request $request) {
    return $request->user()->subscribe('product_id_123');
});
```

When a customer completes their checkout, the incoming `SubscriptionCreated` event webhook connects it to your billable model in the database. You may then get the subscription from your billable model:

```php
$subscription = $user->subscription();
```

#### Checking Subscription Status

Once a consumer has subscribed to your services, you can use a variety of methods to check on the status of their subscription. The most basic example is to check if a customer has a valid subscription.

```php
if ($user->subscribed()) {
    // ...
}
```

You can utilize this in a variety of locations in your app, such as middleware, rules, and so on, to provide services. To determine whether an individual subscription is valid, you can use the `valid` method:

```php
if ($user->subscription()->valid()) {
    // ...
}
```

This method, like the subscribed method, returns true if your membership is active, on trial, past due, or cancelled during its grace period.

You may also check if a subscription is for a certain product:

```php
if ($user->subscription()->hasProduct('product_id_123')) {
    // ...
}
```

If you wish to check if a subscription is on a specific product while being valid, you can use:

```php
if ($user->subscribedToProduct('product_id_123')) {
    // ...
}
```

Alternatively, if you use different [subscription types](#multiple-subscriptions), you can pass a type as an additional parameter:

```php
if ($user->subscribed('swimming')) {
    // ...
}

if ($user->subscribedToProduct('product_id_123', 'swimming')) {
    // ...
}
```

#### Cancelled Status

To see if a user has cancelled their subscription, you can use the cancelled method:

```php
if ($user->subscription()->cancelled()) {
    // ...
}
```

When they are in their grace period, you can utilize the `onGracePeriod` check.

```php
if ($user->subscription()->onGracePeriod()) {
    // ...
}
```

#### Past Due Status

If a recurring payment fails, the subscription will become past due.  This indicates that the subscription is still valid, but your customer's payments will be retried in two weeks.

```php
if ($user->subscription()->pastDue()) {
    // ...
}
```

#### Subscription Scopes

There are several subscription scopes available for querying subscriptions in specific states:

```php
// Get all active subscriptions...
$subscriptions = Subscription::query()->active()->get();

// Get all of the cancelled subscriptions for a specific user...
$subscriptions = $user->subscriptions()->cancelled()->get();
```

Here's all available scopes:

```php
Subscription::query()->incomplete();
Subscription::query()->incompleteExpired();
Subscription::query()->onTrial();
Subscription::query()->active();
Subscription::query()->pastDue();
Subscription::query()->unpaid();
Subscription::query()->cancelled();
```

#### Changing Plans

When a consumer is on a monthly plan, they may desire to upgrade to a better plan, alter their payments to an annual plan, or drop to a lower-cost plan. In these cases, you can allow them to swap plans by giving a different product id to the `swap` method:

```php
use App\Models\User;

$user = User::find(1);

$user->subscription()->swap('product_id_123');
```

This will change the customer's subscription plan, however billing will not occur until the next payment cycle. If you want to immediately invoice the customer, you can use the `swapAndInvoice` method instead.

```php
$user = User::find(1);

$user->subscription()->swapAndInvoice('product_id_123');
```

#### Multiple Subscriptions

In certain situations, you may wish to allow your consumer to subscribe to numerous subscription kinds.  For example, a gym may provide a swimming and weight lifting subscription.  You can let your customers subscribe to one or both.

To handle the various subscriptions, you can offer a type of subscription as the second argument when creating a new one:

```php
$user = User::find(1);

$checkout = $user->subscribe('product_id_123', 'swimming');
```

You can now always refer to this specific subscription type by passing the type argument when getting it:

```php
$user = User::find(1);

// Retrieve the swimming subscription type...
$subscription = $user->subscription('swimming');

// Swap plans for the gym subscription type...
$user->subscription('gym')->swap('product_id_123');

// Cancel the swimming subscription...
$user->subscription('swimming')->cancel();
```

#### Cancelling Subscriptions

To cancel a subscription, call the `cancel` method.

```php
$user = User::find(1);

$user->subscription()->cancel();
```

This will cause your subscription to be cancelled.  If you cancel your subscription in the middle of the cycle, it will enter a grace period, and the ends_at column will be updated.  The customer will continue to have access to the services offered for the duration of the period.  You may check the grace period by calling the `onGracePeriod` method:

```php
if ($user->subscription()->onGracePeriod()) {
    // ...
}
```

Polar does not offer immediate cancellation.  To resume a subscription while it is still in its grace period, use the resume method.

```php
$user->subscription()->resume();
```

When a cancelled subscription approaches the end of its grace period, it becomes expired and cannot be resumed.

#### Subscription Trials

Polar supports trials on subscriptions. Trial data is automatically synced when subscriptions are created or updated via webhooks.

You can check if a subscription is currently on trial:

```php
if ($user->subscription()->onTrial()) {
    // ...
}
```

Or check directly on the billable:

```php
if ($user->onTrial()) {
    // ...
}
```

You can retrieve the trial end date:

```php
$trialEnd = $user->subscription()->trialEndsAt();
```

You can also check if a trial has expired:

```php
if ($user->subscription()->hasExpiredTrial()) {
    // ...
}
```

To update the trial period of a subscription:

```php
$user->subscription()->updateTrial(now()->addDays(30));
```

#### Team Seats

For team / multi-seat subscriptions, manage seat assignments directly from the `Subscription` model.

List seats on a subscription (including counts of available and total seats):

```php
$seatsList = $user->subscription()->seats();

$seatsList->seats;          // array<Polar\Models\Components\CustomerSeat>
$seatsList->availableSeats; // int
$seatsList->totalSeats;     // int
```

Assign a seat. Pass an `email` to send an invitation, or a `customerId` / `externalCustomerId` to assign directly to an existing customer:

```php
$user->subscription()->assignSeat(email: 'alice@example.com');
$user->subscription()->assignSeat(customerId: 'cust_xxx');
$user->subscription()->assignSeat(
    email: 'alice@example.com',
    metadata: ['role' => 'admin'],
);
```

Revoke or resend invitations:

```php
$user->subscription()->revokeSeat('seat_xxx');
$user->subscription()->resendSeatInvitation('seat_xxx');
```

For admin / cross-subscription seat management, the facade variant is also available:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;

LaravelPolar::listSeats(subscriptionId: 'sub_xxx');
LaravelPolar::assignSeat(new Components\SeatAssign(
    subscriptionId: 'sub_xxx',
    email: 'alice@example.com',
));
LaravelPolar::revokeSeat('seat_xxx');
LaravelPolar::resendSeatInvitation('seat_xxx');
```

### Benefits

Benefits are automated features that are granted to customers when they purchase your products. You can manage benefits using both the `LaravelPolar` facade (for create/update/delete operations) and methods on your billable model (for listing and retrieving benefits).

#### Creating Benefits

Create benefits programmatically using the `LaravelPolar` facade:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;

$benefit = LaravelPolar::createBenefit(
    new Components\BenefitCustomCreate(
        description: 'Premium Support',
        organizationId: 'your-org-id',
        properties: new Components\BenefitCustomCreateProperties(),
    )
);
```

#### Listing Benefits

List all benefits for an organization using your billable model:

```php
$benefits = $user->listBenefits('your-org-id');
```

#### Getting a Specific Benefit

Retrieve a specific benefit by ID using your billable model:

```php
$benefit = $user->getBenefit('benefit-id-123');
```

#### Listing Benefit Grants

Get all grants for a specific benefit using your billable model:

```php
$grants = $user->listBenefitGrants('benefit-id-123');
```

#### Updating Benefits

Update an existing benefit using the `LaravelPolar` facade:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;

$benefit = LaravelPolar::updateBenefit(
    'benefit-id-123',
    new Components\BenefitCustomUpdate(
        description: 'Updated Premium Support',
        properties: new Components\BenefitCustomUpdateProperties(),
    )
);
```

#### Deleting Benefits

Delete a benefit using the `LaravelPolar` facade:

```php
LaravelPolar::deleteBenefit('benefit-id-123');
```

### Usage-Based Billing

Track customer usage events for metered billing. This allows you to charge customers based on their actual usage of your service.

#### Tracking Usage Events

Track a single usage event for a customer:

```php
$user->ingestUsageEvent('api_request', [
    'endpoint' => '/api/v1/data',
    'method' => 'GET',
    'duration_ms' => 145,
]);
```

#### Batch Event Ingestion

For usage-based billing, you can track multiple events at once:

```php
$user->ingestUsageEvents([
    [
        'eventName' => 'api_request',
        'properties' => [
            'endpoint' => '/api/v1/data',
            'method' => 'GET',
        ],
    ],
    [
        'eventName' => 'storage_used',
        'properties' => [
            'bytes' => 1048576,
        ],
        'timestamp' => time(),
    ],
]);
```

#### Listing Customer Meters

List all meters for a customer:

```php
$meters = $user->listCustomerMeters();
```

#### Getting a Specific Customer Meter

Retrieve a specific customer meter by ID using the `LaravelPolar` facade:

```php
use Danestves\LaravelPolar\LaravelPolar;

$meter = LaravelPolar::getCustomerMeter('meter-id-123');
```

> [!NOTE]
> Usage events are sent to Polar for processing. They are not stored locally in your database. Use Polar's dashboard or API to view processed usage data.

### License Keys

Polar supports issuing license keys as a benefit type — useful for desktop apps, SDKs, paid CLIs, and anything else that needs an offline-checkable credential. The package wraps three concentric surfaces: admin management, public verification (from end-user machines), and a Cashier-style accessor on the billable model.

> [!NOTE]
> The public verification methods (`validateLicenseKey`, `activateLicenseKey`, `deactivateLicenseKey`) need a Polar organization id. Either pass it explicitly on every call, or set `POLAR_ORGANIZATION_ID` in your `.env` and the package will pick it up automatically.
>
> ```bash
> POLAR_ORGANIZATION_ID="your-org-id"
> ```

#### Admin Management

Use the `LaravelPolar` facade with your org-scoped access token. These methods derive the organization from the token, so no extra config is needed:

```php
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;

LaravelPolar::listLicenseKeys();                                  // optional filters
LaravelPolar::getLicenseKey('lk_xxx');                            // LicenseKeyWithActivations
LaravelPolar::updateLicenseKey('lk_xxx', new Components\LicenseKeyUpdate(
    limitActivations: 10,
));
```

#### Public Verification

Use these from your end-user's machine (a desktop app, CLI, etc.) to validate and manage activations. They require an organization id — either passed explicitly or via the `polar.organization_id` config key:

```php
// Validate a key (optionally bound to a specific activation):
LaravelPolar::validateLicenseKey(
    key: 'LIC-XXXX-XXXX-XXXX',
    activationId: 'act_xxx',
);

// Activate a license on a new device:
$activation = LaravelPolar::activateLicenseKey(
    key: 'LIC-XXXX-XXXX-XXXX',
    label: 'My MacBook Pro',
    meta: ['hostname' => 'macbook-pro', 'os' => 'darwin'],
);

// Deactivate an activation:
LaravelPolar::deactivateLicenseKey(
    key: 'LIC-XXXX-XXXX-XXXX',
    activationId: 'act_xxx',
);
```

Passing the organization id explicitly overrides the config:

```php
LaravelPolar::validateLicenseKey('LIC-XXXX-XXXX-XXXX', organizationId: 'org_xxx');
```

#### Listing a Customer's License Keys

Use the Cashier-style accessor on your billable model. It mints a short-lived customer session under the hood, so the customer doesn't see your admin token:

```php
$keys = $user->licenseKeys();                  // Collection<int, LicenseKeyRead>
$keys = $user->licenseKeys(benefitId: 'b_xx'); // scope to a single benefit
```

### Advanced

Thin facade wrappers around the rest of Polar's API surface. These exist as convenience helpers for occasional use; for anything heavier, see [Reaching into the SDK directly](#reaching-into-the-sdk-directly).

#### Metrics (revenue analytics)

```php
use Brick\DateTime\LocalDate;
use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;
use Polar\Models\Operations;

$metrics = LaravelPolar::getMetrics(new Operations\MetricsGetRequest(
    startDate: LocalDate::of(2026, 1, 1),
    endDate:   LocalDate::of(2026, 1, 31),
    interval:  Components\TimeInterval::Day,
));

// $metrics->periods is an array of Components\MetricPeriod
```

#### Files

List downloadable assets, product media, and org avatars:

```php
$response = LaravelPolar::listFiles();
$items = $response->listResourceFileRead?->items ?? [];
```

#### Organizations

```php
$orgs = LaravelPolar::listOrganizations();
$org  = LaravelPolar::getOrganization('org_xxx'); // Components\Organization
```

### Reaching into the SDK directly

The package wraps the common Polar operations with Laravel-idiomatic helpers, but it does **not** wrap every endpoint. For anything not covered above — Wallets, Files create/update/delete, OAuth2 flows, Metrics dashboards, Organizations create/update, and so on — drop straight into the underlying Polar SDK client:

```php
use Danestves\LaravelPolar\LaravelPolar;

$sdk = LaravelPolar::sdk(); // returns Polar\Polar — the underlying SDK client

$sdk->customerPortal->wallets->list(...);
$sdk->files->create(...);
$sdk->organizations->update(...);
$sdk->oauth2->...;
```

This is the documented and supported way to call any unwrapped endpoint. The package keeps a thin layer for the common cases; `sdk()` covers the long tail.

### Handling Webhooks

Polar can send webhooks to your app, allowing you to react. By default, this package handles the majority of the work for you. If you have properly configured webhooks, it will listen for incoming events and update your database accordingly. We recommend activating all event kinds so you may easily upgrade in the future.

#### Webhook Events

The package dispatches the following webhook events:

**Order Events:**
- `Danestves\LaravelPolar\Events\OrderCreated`
- `Danestves\LaravelPolar\Events\OrderUpdated`

**Subscription Events:**
- `Danestves\LaravelPolar\Events\SubscriptionCreated`
- `Danestves\LaravelPolar\Events\SubscriptionUpdated`
- `Danestves\LaravelPolar\Events\SubscriptionActive`
- `Danestves\LaravelPolar\Events\SubscriptionCanceled`
- `Danestves\LaravelPolar\Events\SubscriptionRevoked`

**Benefit Grant Events:**
- `Danestves\LaravelPolar\Events\BenefitGrantCreated`
- `Danestves\LaravelPolar\Events\BenefitGrantUpdated`
- `Danestves\LaravelPolar\Events\BenefitGrantRevoked`

**Checkout Events:**
- `Danestves\LaravelPolar\Events\CheckoutCreated`
- `Danestves\LaravelPolar\Events\CheckoutUpdated`
- `Danestves\LaravelPolar\Events\CheckoutExpired`

**Customer Events:**
- `Danestves\LaravelPolar\Events\CustomerCreated`
- `Danestves\LaravelPolar\Events\CustomerUpdated`
- `Danestves\LaravelPolar\Events\CustomerDeleted`
- `Danestves\LaravelPolar\Events\CustomerStateChanged`

**Product Events:**
- `Danestves\LaravelPolar\Events\ProductCreated`
- `Danestves\LaravelPolar\Events\ProductUpdated`

**Benefit Events:**
- `Danestves\LaravelPolar\Events\BenefitCreated`
- `Danestves\LaravelPolar\Events\BenefitUpdated`

Each of these events has a `$payload` property containing the webhook payload. Some events also expose convenience properties for direct access to related models:

**Events with Convenience Properties:**

| Event | Convenience Properties |
|-------|----------------------|
| `OrderCreated`, `OrderUpdated` | `$billable`, `$order` |
| `SubscriptionCreated`, `SubscriptionUpdated`, `SubscriptionActive`, `SubscriptionCanceled`, `SubscriptionRevoked` | `$billable`, `$subscription` |
| `BenefitGrantCreated`, `BenefitGrantUpdated`, `BenefitGrantRevoked` | `$billable` |

**Events with Only `$payload`:**

| Event | Access Pattern |
|-------|----------------|
| `CheckoutCreated`, `CheckoutUpdated`, `CheckoutExpired` | `$event->payload->checkout` |
| `CustomerCreated`, `CustomerUpdated`, `CustomerDeleted`, `CustomerStateChanged` | `$event->payload->customer` |
| `ProductCreated`, `ProductUpdated` | `$event->payload->product` |
| `BenefitCreated`, `BenefitUpdated` | `$event->payload->benefit` |

**Example Usage:**

```php
// Events with convenience properties
public function handle(OrderCreated $event): void
{
    $order = $event->order; // Direct access
    $billable = $event->billable; // Direct access
}

// Events with only payload
public function handle(CheckoutCreated $event): void
{
    $checkout = $event->payload->checkout; // Access via payload
}
```

If you wish to respond to these events, you must establish listeners for them. You can create separate listener classes for each event type, or use a single listener class with multiple methods.

#### Using Separate Listener Classes

Create individual listener classes for each event:

```php
<?php

namespace App\Listeners;

use Danestves\LaravelPolar\Events\CheckoutCreated;

class HandleCheckoutCreated
{
    public function handle(CheckoutCreated $event): void
    {
        $checkout = $event->payload->checkout;
        // Handle checkout creation...
    }
}
```

```php
<?php

namespace App\Listeners;

use Danestves\LaravelPolar\Events\SubscriptionUpdated;

class HandleSubscriptionUpdated
{
    public function handle(SubscriptionUpdated $event): void
    {
        $subscription = $event->subscription;
        // Handle subscription update...
    }
}
```

#### Using a Single Listener Class

Alternatively, you can use a single listener class with multiple methods. For this approach, you'll need to register the listener as an event subscriber:

```php
<?php

namespace App\Listeners;

use Danestves\LaravelPolar\Events\CheckoutCreated;
use Danestves\LaravelPolar\Events\SubscriptionUpdated;
use Danestves\LaravelPolar\Events\WebhookHandled;
use Illuminate\Events\Dispatcher;

class PolarEventListener
{
    /**
     * Handle received Polar webhooks.
     */
    public function handleWebhookHandled(WebhookHandled $event): void
    {
        if ($event->payload['type'] === 'subscription.updated') {
            // Handle the incoming event...
        }
    }

    /**
     * Handle checkout created events.
     */
    public function handleCheckoutCreated(CheckoutCreated $event): void
    {
        $checkout = $event->payload->checkout;
        // Handle checkout creation...
    }

    /**
     * Handle subscription updated events.
     */
    public function handleSubscriptionUpdated(SubscriptionUpdated $event): void
    {
        $subscription = $event->subscription;
        // Handle subscription update...
    }

    /**
     * Register the listeners for the subscriber.
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            WebhookHandled::class,
            [self::class, 'handleWebhookHandled']
        );

        $events->listen(
            CheckoutCreated::class,
            [self::class, 'handleCheckoutCreated']
        );

        $events->listen(
            SubscriptionUpdated::class,
            [self::class, 'handleSubscriptionUpdated']
        );
    }
}
```

The [Polar documentation](https://docs.polar.sh/integrate/webhooks/events) includes an example payload.

#### Registering Listeners

**For separate listener classes**, register them in your `EventServiceProvider`:

```php
<?php

namespace App\Providers;

use App\Listeners\HandleCheckoutCreated;
use App\Listeners\HandleSubscriptionUpdated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Danestves\LaravelPolar\Events\CheckoutCreated;
use Danestves\LaravelPolar\Events\SubscriptionUpdated;
use Danestves\LaravelPolar\Events\WebhookHandled;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        WebhookHandled::class => [
            // Add your listeners here
        ],
        CheckoutCreated::class => [
            HandleCheckoutCreated::class,
        ],
        SubscriptionUpdated::class => [
            HandleSubscriptionUpdated::class,
        ],
    ];
}
```

**For event subscribers**, register the subscriber in your `EventServiceProvider`:

```php
<?php

namespace App\Providers;

use App\Listeners\PolarEventListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $subscribe = [
        PolarEventListener::class,
    ];
}
```

Laravel v11 and v12 will automatically discover listeners and subscribers if they follow Laravel's naming conventions.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [laravel/cashier (Stripe)](https://github.com/laravel/cashier-stripe)
- [laravel/cashier (Paddle)](https://github.com/laravel/cashier-paddle)
- [lemonsqueezy/laravel](https://github.com/lmsqueezy/laravel)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
