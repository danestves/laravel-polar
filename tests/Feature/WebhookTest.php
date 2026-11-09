<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Events\BenefitCreated;
use Danestves\LaravelPolar\Events\BenefitGrantCreated;
use Danestves\LaravelPolar\Events\BenefitGrantRevoked;
use Danestves\LaravelPolar\Events\BenefitGrantUpdated;
use Danestves\LaravelPolar\Events\BenefitUpdated;
use Danestves\LaravelPolar\Events\CheckoutCreated;
use Danestves\LaravelPolar\Events\CheckoutUpdated;
use Danestves\LaravelPolar\Events\CustomerCreated;
use Danestves\LaravelPolar\Events\CustomerDeleted;
use Danestves\LaravelPolar\Events\CustomerStateChanged;
use Danestves\LaravelPolar\Events\CustomerUpdated;
use Danestves\LaravelPolar\Events\DiscountCreated;
use Danestves\LaravelPolar\Events\DiscountDeleted;
use Danestves\LaravelPolar\Events\DiscountUpdated;
use Danestves\LaravelPolar\Events\OrderCreated;
use Danestves\LaravelPolar\Events\OrderPaid;
use Danestves\LaravelPolar\Events\OrderRefunded;
use Danestves\LaravelPolar\Events\OrderUpdated;
use Danestves\LaravelPolar\Events\OrganizationUpdated;
use Danestves\LaravelPolar\Events\ProductCreated;
use Danestves\LaravelPolar\Events\ProductUpdated;
use Danestves\LaravelPolar\Events\RefundCreated;
use Danestves\LaravelPolar\Events\RefundUpdated;
use Danestves\LaravelPolar\Events\SubscriptionActive;
use Danestves\LaravelPolar\Events\SubscriptionCanceled;
use Danestves\LaravelPolar\Events\SubscriptionCreated;
use Danestves\LaravelPolar\Events\SubscriptionCycled;
use Danestves\LaravelPolar\Events\SubscriptionMigrated;
use Danestves\LaravelPolar\Events\SubscriptionPaused;
use Danestves\LaravelPolar\Events\SubscriptionPastDue;
use Danestves\LaravelPolar\Events\SubscriptionResumed;
use Danestves\LaravelPolar\Events\SubscriptionRevoked;
use Danestves\LaravelPolar\Events\SubscriptionUncanceled;
use Danestves\LaravelPolar\Events\SubscriptionUpdated;
use Danestves\LaravelPolar\Events\WebhookHandled;
use Danestves\LaravelPolar\Events\WebhookReceived;
use Danestves\LaravelPolar\Exceptions\InvalidMetadataPayload;
use Danestves\LaravelPolar\Handlers\ProcessWebhook;
use Danestves\LaravelPolar\Order;
use Danestves\LaravelPolar\Subscription;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Models\WebhookCall;

class TestProcessWebhook extends ProcessWebhook
{
    private string $jsonPayload;

    public function __construct($webhookCall, string $jsonPayload)
    {
        parent::__construct($webhookCall);
        $this->jsonPayload = $jsonPayload;
    }

    public function handle(): void
    {
        $decoded = json_decode($this->jsonPayload, true);
        $payload = $decoded['payload'];
        $type = $payload['type'];
        $data = $payload['data'];
        $timestamp = isset($payload['timestamp']) ? new \DateTime($payload['timestamp']) : new \DateTime();

        WebhookReceived::dispatch($payload);

        $reflection = new \ReflectionClass($this);

        match ($type) {
            'order.created' => $reflection->getMethod('handleOrderCreated')->invoke($this, $data, $timestamp, $type),
            'order.updated' => $reflection->getMethod('handleOrderUpdated')->invoke($this, $data, $timestamp, $type),
            'order.paid' => $reflection->getMethod('handleOrderPaid')->invoke($this, $data, $timestamp, $type),
            'order.refunded' => $reflection->getMethod('handleOrderRefunded')->invoke($this, $data, $timestamp, $type),
            'subscription.created' => $reflection->getMethod('handleSubscriptionCreated')->invoke($this, $data, $timestamp, $type),
            'subscription.updated' => $reflection->getMethod('handleSubscriptionUpdated')->invoke($this, $data, $timestamp, $type),
            'subscription.active' => $reflection->getMethod('handleSubscriptionActive')->invoke($this, $data, $timestamp, $type),
            'subscription.canceled' => $reflection->getMethod('handleSubscriptionCanceled')->invoke($this, $data, $timestamp, $type),
            'subscription.revoked' => $reflection->getMethod('handleSubscriptionRevoked')->invoke($this, $data, $timestamp, $type),
            'subscription.uncanceled' => $reflection->getMethod('handleSubscriptionUncanceled')->invoke($this, $data, $timestamp, $type),
            'subscription.cycled' => $reflection->getMethod('handleSubscriptionCycled')->invoke($this, $data, $timestamp, $type),
            'subscription.past_due' => $reflection->getMethod('handleSubscriptionPastDue')->invoke($this, $data, $timestamp, $type),
            'subscription.paused' => $reflection->getMethod('handleSubscriptionPaused')->invoke($this, $data, $timestamp, $type),
            'subscription.resumed' => $reflection->getMethod('handleSubscriptionResumed')->invoke($this, $data, $timestamp, $type),
            'subscription.migrated' => $reflection->getMethod('handleSubscriptionMigrated')->invoke($this, $data, $timestamp, $type, $payload),
            'benefit_grant.created' => $reflection->getMethod('handleBenefitGrantCreated')->invoke($this, $data, $timestamp, $type),
            'benefit_grant.updated' => $reflection->getMethod('handleBenefitGrantUpdated')->invoke($this, $data, $timestamp, $type),
            'benefit_grant.cycled' => $reflection->getMethod('handleBenefitGrantCycled')->invoke($this, $data, $timestamp, $type),
            'benefit_grant.revoked' => $reflection->getMethod('handleBenefitGrantRevoked')->invoke($this, $data, $timestamp, $type),
            'member.created' => $reflection->getMethod('handleMemberEvent')->invoke($this, $data, $timestamp, $type, \Danestves\LaravelPolar\Data\WebhookMemberCreatedPayload::class, \Danestves\LaravelPolar\Events\MemberCreated::class),
            'member.updated' => $reflection->getMethod('handleMemberEvent')->invoke($this, $data, $timestamp, $type, \Danestves\LaravelPolar\Data\WebhookMemberUpdatedPayload::class, \Danestves\LaravelPolar\Events\MemberUpdated::class),
            'member.deleted' => $reflection->getMethod('handleMemberEvent')->invoke($this, $data, $timestamp, $type, \Danestves\LaravelPolar\Data\WebhookMemberDeletedPayload::class, \Danestves\LaravelPolar\Events\MemberDeleted::class),
            'customer_seat.assigned' => $reflection->getMethod('handleCustomerSeatEvent')->invoke($this, $data, $timestamp, $type, \Danestves\LaravelPolar\Data\WebhookCustomerSeatAssignedPayload::class, \Danestves\LaravelPolar\Events\CustomerSeatAssigned::class),
            'customer_seat.claimed' => $reflection->getMethod('handleCustomerSeatEvent')->invoke($this, $data, $timestamp, $type, \Danestves\LaravelPolar\Data\WebhookCustomerSeatClaimedPayload::class, \Danestves\LaravelPolar\Events\CustomerSeatClaimed::class),
            'customer_seat.revoked' => $reflection->getMethod('handleCustomerSeatEvent')->invoke($this, $data, $timestamp, $type, \Danestves\LaravelPolar\Data\WebhookCustomerSeatRevokedPayload::class, \Danestves\LaravelPolar\Events\CustomerSeatRevoked::class),
            'checkout.created' => $reflection->getMethod('handleCheckoutCreated')->invoke($this, $data, $timestamp, $type),
            'checkout.updated' => $reflection->getMethod('handleCheckoutUpdated')->invoke($this, $data, $timestamp, $type),
            'customer.created' => $reflection->getMethod('handleCustomerCreated')->invoke($this, $data, $timestamp, $type),
            'customer.updated' => $reflection->getMethod('handleCustomerUpdated')->invoke($this, $data, $timestamp, $type),
            'customer.deleted' => $reflection->getMethod('handleCustomerDeleted')->invoke($this, $data, $timestamp, $type),
            'customer.state_changed' => $reflection->getMethod('handleCustomerStateChanged')->invoke($this, $data, $timestamp, $type),
            'product.created' => $reflection->getMethod('handleProductCreated')->invoke($this, $data, $timestamp, $type),
            'product.updated' => $reflection->getMethod('handleProductUpdated')->invoke($this, $data, $timestamp, $type),
            'benefit.created' => $reflection->getMethod('handleBenefitCreated')->invoke($this, $data, $timestamp, $type),
            'benefit.updated' => $reflection->getMethod('handleBenefitUpdated')->invoke($this, $data, $timestamp, $type),
            'refund.created' => $reflection->getMethod('handleRefundCreated')->invoke($this, $data, $timestamp, $type),
            'refund.updated' => $reflection->getMethod('handleRefundUpdated')->invoke($this, $data, $timestamp, $type),
            'discount.created' => $reflection->getMethod('handleDiscountCreated')->invoke($this, $data, $timestamp, $type),
            'discount.updated' => $reflection->getMethod('handleDiscountUpdated')->invoke($this, $data, $timestamp, $type),
            'discount.deleted' => $reflection->getMethod('handleDiscountDeleted')->invoke($this, $data, $timestamp, $type),
            'organization.updated' => $reflection->getMethod('handleOrganizationUpdated')->invoke($this, $data, $timestamp, $type),
            default => \Illuminate\Support\Facades\Log::info("Unknown event type: $type"),
        };

        WebhookHandled::dispatch($payload);
        http_response_code(200);
    }
}

function createWebhookCall(array $payload): ProcessWebhook
{
    $webhookCall = WebhookCall::create([
        'name' => 'polar',
        'url' => 'https://example.com/webhook',
        'payload' => ['payload' => $payload],
    ]);

    return new TestProcessWebhook($webhookCall, json_encode(['payload' => $payload]));
}

beforeEach(function () {
    Event::fake();

    \Illuminate\Database\Eloquent\Relations\Relation::enforceMorphMap([
        'users' => User::class,
    ]);
});

it('handles order.created webhook', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'order.created',
        'data' => polarFixture('Order', [
            'id' => 'order_123',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->toIso8601String(),
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $order = Order::where('polar_id', 'order_123')->first();
    expect($order)->not->toBeNull();
    expect($order->billable_id)->toBe($user->getKey());
    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->amount)->toBe(1000);
    expect($order->product_id)->toBe('product_123');

    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(OrderCreated::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles order.updated webhook', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'order_123',
    ]);

    $payload = [
        'type' => 'order.updated',
        'data' => polarFixture('Order', [
            'id' => 'order_123',
            'status' => OrderStatus::Refunded->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 1000,
            'refunded_tax_amount' => 100,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'refunded_at' => now()->toIso8601String(),
            'created_at' => now()->toIso8601String(),
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded);
    expect($order->refunded_amount)->toBe(1000);
    expect($order->refunded_at)->not->toBeNull();

    Event::assertDispatched(OrderUpdated::class);
});

it('handles subscription.created webhook', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'subscription.created',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                    'subscription_type' => 'premium',
                ],
            ],
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription = Subscription::where('polar_id', 'subscription_123')->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->billable_id)->toBe($user->getKey());
    expect($subscription->status)->toBe(SubscriptionStatus::Active);
    expect($subscription->product_id)->toBe('product_123');
    expect($subscription->type)->toBe('premium');

    $customer = Customer::where('billable_id', $user->getKey())->first();
    expect($customer->polar_id)->toBe('customer_123');

    Event::assertDispatched(SubscriptionCreated::class);
});

it('handles subscription.created webhook with trial data', function () {
    $user = User::factory()->create();
    $trialEnd = now()->addDays(14)->toIso8601String();

    $payload = [
        'type' => 'subscription.created',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_trial_123',
            'status' => SubscriptionStatus::Trialing->value,
            'product_id' => 'product_123',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'trial_end' => $trialEnd,
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription = Subscription::where('polar_id', 'subscription_trial_123')->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->trial_ends_at->toIso8601String())->toBe($trialEnd);

    Event::assertDispatched(SubscriptionCreated::class);
});

it('handles subscription.updated webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_123',
    ]);

    $payload = [
        'type' => 'subscription.updated',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::PastDue->value,
            'product_id' => 'product_456',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue);
    expect($subscription->product_id)->toBe('product_456');

    Event::assertDispatched(SubscriptionUpdated::class);
});

it('handles subscription.updated webhook with trial data', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->trialing()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_123',
    ]);

    $trialEnd = now()->addDays(30)->toIso8601String();

    $payload = [
        'type' => 'subscription.updated',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Trialing->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'trial_end' => $trialEnd,
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Trialing);
    expect($subscription->trial_ends_at)->not->toBeNull();
    expect($subscription->trial_ends_at->toIso8601String())->toBe($trialEnd);

    Event::assertDispatched(SubscriptionUpdated::class);
});

it('handles subscription.active webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->pastDue()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_123',
    ]);

    $payload = [
        'type' => 'subscription.active',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active);

    Event::assertDispatched(SubscriptionActive::class);
});

it('handles subscription.canceled webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_123',
    ]);

    $payload = [
        'type' => 'subscription.canceled',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Canceled->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => now()->addDays(30)->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Canceled);
    expect($subscription->ends_at)->not->toBeNull();

    Event::assertDispatched(SubscriptionCanceled::class);
});

it('handles subscription.revoked webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_123',
    ]);

    $payload = [
        'type' => 'subscription.revoked',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Canceled->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Canceled);

    Event::assertDispatched(SubscriptionRevoked::class);
});

it('handles benefit_grant.created webhook', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'benefit_grant.created',
        'data' => polarFixture('BenefitGrantCustomWebhook', [
            'id' => 'benefit_grant_123',
            'type' => 'custom',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'type' => 'individual',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(BenefitGrantCreated::class);
});

it('handles benefit_grant.updated webhook', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'benefit_grant.updated',
        'data' => polarFixture('BenefitGrantCustomWebhook', [
            'id' => 'benefit_grant_123',
            'type' => 'custom',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'type' => 'individual',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(BenefitGrantUpdated::class);
});

it('handles benefit_grant.revoked webhook', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'benefit_grant.revoked',
        'data' => polarFixture('BenefitGrantCustomWebhook', [
            'id' => 'benefit_grant_123',
            'type' => 'custom',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'type' => 'individual',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(BenefitGrantRevoked::class);
});

it('throws exception when metadata is missing', function () {
    $payload = [
        'type' => 'order.created',
        'data' => polarFixture('Order', [
            'id' => 'order_123',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->toIso8601String(),
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);

    expect(fn() => $job->handle())
        ->toThrow(InvalidMetadataPayload::class);
});

it('ignores unknown webhook event types', function () {
    $payload = [
        'type' => 'unknown.event',
        'data' => [],
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
    Event::assertNotDispatched(OrderCreated::class);
    Event::assertNotDispatched(SubscriptionCreated::class);
});

it('handles order.updated when order does not exist', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'order.updated',
        'data' => polarFixture('Order', [
            'id' => 'nonexistent_order',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->toIso8601String(),
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    expect(Order::where('polar_id', 'nonexistent_order')->exists())->toBeFalse();
    Event::assertNotDispatched(OrderUpdated::class);
});

it('handles subscription.updated when subscription does not exist', function () {
    $payload = [
        'type' => 'subscription.updated',
        'data' => polarFixture('Subscription', [
            'id' => 'nonexistent_subscription',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    expect(Subscription::where('polar_id', 'nonexistent_subscription')->exists())->toBeFalse();
    Event::assertNotDispatched(SubscriptionUpdated::class);
});

it('handles checkout.created webhook', function () {
    $payload = [
        'type' => 'checkout.created',
        'data' => polarFixture('Checkout', [
            'id' => 'checkout_123',
            'url' => 'https://polar.sh/checkout/checkout_123',
            'product_id' => 'product_123',
            'status' => 'open',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(CheckoutCreated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles checkout.updated webhook', function () {
    $payload = [
        'type' => 'checkout.updated',
        'data' => polarFixture('Checkout', [
            'id' => 'checkout_123',
            'url' => 'https://polar.sh/checkout/checkout_123',
            'product_id' => 'product_123',
            'status' => 'succeeded',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(CheckoutUpdated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles customer.created webhook', function () {
    $payload = [
        'type' => 'customer.created',
        'data' => polarFixture('CustomerIndividual', [
            'id' => 'customer_123',
            'type' => 'individual',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(CustomerCreated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles customer.updated webhook', function () {
    $payload = [
        'type' => 'customer.updated',
        'data' => polarFixture('CustomerIndividual', [
            'id' => 'customer_123',
            'type' => 'individual',
            'email' => 'updated@example.com',
            'name' => 'Updated User',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(CustomerUpdated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles customer.deleted webhook', function () {
    $payload = [
        'type' => 'customer.deleted',
        'data' => polarFixture('CustomerIndividual', [
            'id' => 'customer_123',
            'type' => 'individual',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(CustomerDeleted::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles customer.state_changed webhook', function () {
    $payload = [
        'type' => 'customer.state_changed',
        'data' => polarFixture('CustomerStateIndividual', [
            'id' => 'customer_123',
            'type' => 'individual',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'active_subscriptions' => [],
            'granted_benefits' => [],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(CustomerStateChanged::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles product.created webhook', function () {
    $payload = [
        'type' => 'product.created',
        'data' => polarFixture('Product', [
            'id' => 'product_123',
            'name' => 'Test Product',
            'description' => 'A test product',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(ProductCreated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles product.updated webhook', function () {
    $payload = [
        'type' => 'product.updated',
        'data' => polarFixture('Product', [
            'id' => 'product_123',
            'name' => 'Updated Product',
            'description' => 'An updated test product',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(ProductUpdated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles benefit.created webhook', function () {
    $payload = [
        'type' => 'benefit.created',
        'data' => polarFixture('BenefitCustom', [
            'id' => 'benefit_123',
            'type' => 'custom',
            'description' => 'Test Benefit',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(BenefitCreated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

it('handles benefit.updated webhook', function () {
    $payload = [
        'type' => 'benefit.updated',
        'data' => polarFixture('BenefitCustom', [
            'id' => 'benefit_123',
            'type' => 'custom',
            'description' => 'Updated Test Benefit',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(BenefitUpdated::class);
    Event::assertDispatched(WebhookReceived::class);
    Event::assertDispatched(WebhookHandled::class);
});

/**
 * A payload the package cannot type must never cost us the database sync.
 *
 * Polar can add a required field or a new enum value at any time; a package released before
 * that change still has to keep billing state correct.
 */
it('records an order that has no product, which Polar allows', function () {
    $user = User::factory()->create();

    $payload = [
        'type' => 'order.created',
        'data' => polarFixture('Order', [
            'id' => 'order_no_product',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'product_id' => null,
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $order = Order::where('polar_id', 'order_no_product')->first();

    expect($order)->not->toBeNull()
        ->and($order->product_id)->toBeNull()
        ->and($order->amount)->toBe(1000)
        ->and($order->hasProduct('product_123'))->toBeFalse()
        ->and($user->hasPurchasedProduct('product_123'))->toBeFalse();

    Event::assertDispatched(OrderCreated::class);
});

describe('payloads this package cannot yet parse', function () {
    beforeEach(function () {
        Log::spy();
    });

    it('still syncs the order when a nested field is missing from the payload', function () {
        $user = User::factory()->create();

        $data = polarFixture('Order', [
            'id' => 'order_unparseable',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'product_id' => 'product_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]);

        // A field the schema requires but the database sync never reads — the same shape as
        // Polar adding a required field this package predates.
        unset($data['subtotal_amount']);

        $job = createWebhookCall(['type' => 'order.created', 'data' => $data, 'timestamp' => now()->toIso8601String()]);
        $job->handle();

        $order = Order::where('polar_id', 'order_unparseable')->first();

        expect($order)->not->toBeNull()
            ->and($order->status)->toBe(OrderStatus::Paid)
            ->and($order->amount)->toBe(1000)
            ->and($order->billable_id)->toBe($user->getKey());
    });

    it('still syncs the order when a nested enum gains a value we do not know', function () {
        $user = User::factory()->create();

        $data = polarFixture('Order', [
            'id' => 'order_future_enum',
            'status' => OrderStatus::Paid->value,
            'product_id' => 'product_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
            'billing_reason' => 'some_future_billing_reason',
        ]);

        $job = createWebhookCall(['type' => 'order.created', 'data' => $data, 'timestamp' => now()->toIso8601String()]);
        $job->handle();

        expect(Order::where('polar_id', 'order_future_enum')->exists())->toBeTrue();
    });

    it('skips the typed event rather than dispatching a half-built payload', function () {
        $user = User::factory()->create();

        $data = polarFixture('Order', [
            'id' => 'order_unparseable',
            'status' => OrderStatus::Paid->value,
            'product_id' => 'product_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]);
        unset($data['subtotal_amount']);

        $job = createWebhookCall(['type' => 'order.created', 'data' => $data, 'timestamp' => now()->toIso8601String()]);
        $job->handle();

        Event::assertNotDispatched(OrderCreated::class);

        // The generic webhook events still fire, so listeners on the raw payload are unaffected.
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
    });

    it('logs the failure with the event type so it can be diagnosed', function () {
        $user = User::factory()->create();

        $data = polarFixture('Order', [
            'id' => 'order_unparseable',
            'status' => OrderStatus::Paid->value,
            'product_id' => 'product_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]);
        unset($data['subtotal_amount']);

        $job = createWebhookCall(['type' => 'order.created', 'data' => $data, 'timestamp' => now()->toIso8601String()]);
        $job->handle();

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'could not be parsed')
                    && $context['event_type'] === 'order.created'
                    && isset($context['reason']);
            });
    });

    it('does not dispatch an event when a payload-only webhook cannot be parsed', function () {
        $data = polarFixture('Product', ['id' => 'product_123']);
        unset($data['name']);

        $job = createWebhookCall(['type' => 'product.created', 'data' => $data, 'timestamp' => now()->toIso8601String()]);
        $job->handle();

        Event::assertNotDispatched(ProductCreated::class);
        Event::assertDispatched(WebhookHandled::class);
    });
});

it('handles subscription.uncanceled webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->cancelled()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_uncanceled',
    ]);

    $job = createWebhookCall([
        'type' => 'subscription.uncanceled',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_uncanceled',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'cancel_at_period_end' => false,
            'canceled_at' => null,
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->cancel_at_period_end)->toBeFalse()
        ->and($subscription->canceled_at)->toBeNull();

    Event::assertDispatched(SubscriptionUncanceled::class);
});

it('handles subscription.cycled webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_cycled',
    ]);
    $periodEnd = now()->addDays(30);

    $job = createWebhookCall([
        'type' => 'subscription.cycled',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_cycled',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'current_period_start' => now()->toIso8601String(),
            'current_period_end' => $periodEnd->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription->refresh();
    expect($subscription->current_period_end->toIso8601String())->toBe($periodEnd->toIso8601String());

    Event::assertDispatched(SubscriptionCycled::class);
});

it('handles subscription.past_due webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_past_due',
    ]);

    $job = createWebhookCall([
        'type' => 'subscription.past_due',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_past_due',
            'status' => SubscriptionStatus::PastDue->value,
            'product_id' => 'product_123',
            'past_due_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue)
        ->and($subscription->past_due_at)->not->toBeNull();

    Event::assertDispatched(SubscriptionPastDue::class);
});

it('handles subscription.paused webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_paused',
    ]);

    $job = createWebhookCall([
        'type' => 'subscription.paused',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_paused',
            'status' => SubscriptionStatus::Paused->value,
            'product_id' => 'product_123',
            'paused_at' => now()->toIso8601String(),
            'resumes_at' => now()->addDays(30)->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Paused)
        ->and($subscription->paused_at)->not->toBeNull()
        ->and($subscription->resumes_at)->not->toBeNull();

    Event::assertDispatched(SubscriptionPaused::class);
});

it('handles subscription.resumed webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->paused()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_resumed',
    ]);

    $job = createWebhookCall([
        'type' => 'subscription.resumed',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_resumed',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'paused_at' => null,
            'resumes_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->paused_at)->toBeNull();

    Event::assertDispatched(SubscriptionResumed::class);
});

it('handles subscription.migrated webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_migrated',
        'product_id' => 'product_123',
    ]);

    $job = createWebhookCall([
        'type' => 'subscription.migrated',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_migrated',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_456',
        ]),
        'provider' => 'stripe',
        'provider_subscription_id' => 'sub_1Sabc2Def3Ghi',
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription->refresh();
    expect($subscription->product_id)->toBe('product_456');

    Event::assertDispatched(SubscriptionMigrated::class, function (SubscriptionMigrated $event) {
        return $event->payload->provider === 'stripe'
            && $event->payload->providerSubscriptionId === 'sub_1Sabc2Def3Ghi';
    });
});

it('handles order.paid webhook and creates a missing order', function () {
    $user = User::factory()->create();

    $job = createWebhookCall([
        'type' => 'order.paid',
        'data' => polarFixture('Order', [
            'id' => 'order_paid',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->toIso8601String(),
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $order = Order::where('polar_id', 'order_paid')->first();
    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Paid)
        ->and($order->billable_id)->toBe($user->getKey());

    Event::assertDispatched(OrderPaid::class);
});

it('handles order.refunded webhook', function () {
    $user = User::factory()->create();
    $order = Order::factory()->paid()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'order_refunded',
    ]);

    $job = createWebhookCall([
        'type' => 'order.refunded',
        'data' => polarFixture('Order', [
            'id' => 'order_refunded',
            'status' => OrderStatus::Refunded->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 1000,
            'refunded_tax_amount' => 100,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Refunded)
        ->and($order->refunded_amount)->toBe(1000);

    Event::assertDispatched(OrderRefunded::class);
});

it('handles order.refunded webhook when order does not exist', function () {
    $job = createWebhookCall([
        'type' => 'order.refunded',
        'data' => polarFixture('Order', [
            'id' => 'missing_order',
            'status' => OrderStatus::Refunded->value,
            'net_amount' => 1000,
            'refunded_amount' => 1000,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    expect(Order::where('polar_id', 'missing_order')->exists())->toBeFalse();
    Event::assertNotDispatched(OrderRefunded::class);
});

it('handles refund.created webhook', function () {
    $job = createWebhookCall([
        'type' => 'refund.created',
        'data' => polarFixture('Refund', [
            'id' => 'refund_created',
            'status' => 'pending',
            'reason' => 'customer_request',
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched(RefundCreated::class);
});

it('handles refund.updated webhook', function () {
    $job = createWebhookCall([
        'type' => 'refund.updated',
        'data' => polarFixture('Refund', [
            'id' => 'refund_updated',
            'status' => 'succeeded',
            'reason' => 'customer_request',
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched(RefundUpdated::class);
});

it('handles discount.created webhook', function () {
    $job = createWebhookCall([
        'type' => 'discount.created',
        'data' => polarFixture('DiscountFixedOnceForeverDuration', ['id' => 'discount_created']),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched(DiscountCreated::class);
});

it('handles discount.updated webhook', function () {
    $job = createWebhookCall([
        'type' => 'discount.updated',
        'data' => polarFixture('DiscountFixedOnceForeverDuration', ['id' => 'discount_updated']),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched(DiscountUpdated::class);
});

it('handles discount.deleted webhook', function () {
    $job = createWebhookCall([
        'type' => 'discount.deleted',
        'data' => polarFixture('DiscountFixedOnceForeverDuration', ['id' => 'discount_deleted']),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched(DiscountDeleted::class);
});

it('handles organization.updated webhook', function () {
    $job = createWebhookCall([
        'type' => 'organization.updated',
        'data' => polarFixture('Organization', [
            'id' => 'organization_123',
            'name' => 'Acme',
            'slug' => 'acme',
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched(OrganizationUpdated::class);
});

it('does not create a duplicate order when order.created is re-delivered', function () {
    $user = User::factory()->create();

    $job = createWebhookCall([
        'type' => 'order.created',
        'data' => polarFixture('Order', [
            'id' => 'order_duplicate',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->toIso8601String(),
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);

    $job->handle();
    $job->handle();

    expect(Order::where('polar_id', 'order_duplicate')->count())->toBe(1);
});

it('does not create a duplicate subscription when subscription.created is re-delivered', function () {
    $user = User::factory()->create();

    $job = createWebhookCall([
        'type' => 'subscription.created',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_duplicate',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);

    $job->handle();
    $job->handle();

    expect(Subscription::where('polar_id', 'subscription_duplicate')->count())->toBe(1);
});

it('persists the full lifecycle state on a newly created subscription', function () {
    $user = User::factory()->create();
    $periodStart = now()->subDays(1);

    $job = createWebhookCall([
        'type' => 'subscription.created',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_created_lifecycle',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
            'discount_id' => 'discount_123',
            'current_period_start' => $periodStart->toIso8601String(),
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'cancel_at_period_end' => true,
            'canceled_at' => now()->toIso8601String(),
            'ends_at' => now()->addDays(30)->toIso8601String(),
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription = Subscription::where('polar_id', 'subscription_created_lifecycle')->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->discount_id)->toBe('discount_123')
        ->and($subscription->cancel_at_period_end)->toBeTrue()
        ->and($subscription->canceled_at)->not->toBeNull()
        ->and($subscription->current_period_start->toIso8601String())->toBe($periodStart->toIso8601String())
        ->and($subscription->polar_updated_at_us)->not->toBeNull();
});

it('does not use local time as the ordering watermark when modified_at is missing', function () {
    $order = Order::factory()->paid()->create([
        'polar_id' => 'order_no_modified_at',
        'polar_updated_at_us' => null,
    ]);

    $order->sync([
        'id' => 'order_no_modified_at',
        'status' => OrderStatus::Paid->value,
        'net_amount' => 1000,
        'tax_amount' => 100,
        'refunded_amount' => 0,
        'refunded_tax_amount' => 0,
        'currency' => 'USD',
        'billing_reason' => 'purchase',
        'customer_id' => 'customer_123',
        'product_id' => 'product_123',
        'created_at' => now()->toIso8601String(),
        'modified_at' => null,
    ]);

    expect($order->fresh()->polar_updated_at_us)->toBeNull();
});

it('does not drop a later event when created events have no modified_at', function () {
    $user = User::factory()->create();

    $created = createWebhookCall([
        'type' => 'order.created',
        'data' => polarFixture('Order', [
            'id' => 'order_ordering',
            'status' => OrderStatus::Pending->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->subSeconds(10)->toIso8601String(),
            'modified_at' => null,
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $created->handle();

    $paid = createWebhookCall([
        'type' => 'order.paid',
        'data' => polarFixture('Order', [
            'id' => 'order_ordering',
            'status' => OrderStatus::Paid->value,
            'net_amount' => 1000,
            'tax_amount' => 100,
            'refunded_amount' => 0,
            'refunded_tax_amount' => 0,
            'currency' => 'USD',
            'billing_reason' => 'purchase',
            'customer_id' => 'customer_123',
            'product_id' => 'product_123',
            'created_at' => now()->subSeconds(10)->toIso8601String(),
            'modified_at' => now()->subSecond()->toIso8601String(),
        ]),
        'timestamp' => now()->addSeconds(5)->toIso8601String(),
    ]);
    $paid->handle();

    expect(Order::where('polar_id', 'order_ordering')->first()->status)->toBe(OrderStatus::Paid);
});

it('creates a migrated subscription that never had a subscription.created event', function () {
    $user = User::factory()->create();

    $job = createWebhookCall([
        'type' => 'subscription.migrated',
        'data' => polarFixture('Subscription', [
            'id' => 'subscription_migrated_new',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_456',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                    'subscription_type' => 'premium',
                ],
            ],
        ]),
        'provider' => 'stripe',
        'provider_subscription_id' => 'sub_migrated',
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    $subscription = Subscription::where('polar_id', 'subscription_migrated_new')->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->billable_id)->toBe($user->getKey())
        ->and($subscription->type)->toBe('premium')
        ->and($subscription->product_id)->toBe('product_456');

    Event::assertDispatched(SubscriptionMigrated::class);
});

it('handles benefit_grant.cycled webhook', function () {
    $user = User::factory()->create();

    $job = createWebhookCall([
        'type' => 'benefit_grant.cycled',
        'data' => polarFixture('BenefitGrantCustomWebhook', [
            'id' => 'benefit_grant_cycled',
            'type' => 'custom',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'type' => 'individual',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ]),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched(\Danestves\LaravelPolar\Events\BenefitGrantCycled::class);
});

it('handles member webhooks', function (string $type, string $event) {
    $job = createWebhookCall([
        'type' => $type,
        'data' => polarFixture('Member', ['id' => 'member_123']),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched($event);
})->with([
    ['member.created', \Danestves\LaravelPolar\Events\MemberCreated::class],
    ['member.updated', \Danestves\LaravelPolar\Events\MemberUpdated::class],
    ['member.deleted', \Danestves\LaravelPolar\Events\MemberDeleted::class],
]);

it('handles customer seat webhooks', function (string $type, string $event) {
    $job = createWebhookCall([
        'type' => $type,
        'data' => polarFixture('CustomerSeat', ['id' => 'seat_123']),
        'timestamp' => now()->toIso8601String(),
    ]);
    $job->handle();

    Event::assertDispatched($event);
})->with([
    ['customer_seat.assigned', \Danestves\LaravelPolar\Events\CustomerSeatAssigned::class],
    ['customer_seat.claimed', \Danestves\LaravelPolar\Events\CustomerSeatClaimed::class],
    ['customer_seat.revoked', \Danestves\LaravelPolar\Events\CustomerSeatRevoked::class],
]);
