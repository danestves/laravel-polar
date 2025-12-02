<?php

namespace Tests\Feature;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Events\BenefitGrantCreated;
use Danestves\LaravelPolar\Events\BenefitGrantRevoked;
use Danestves\LaravelPolar\Events\BenefitGrantUpdated;
use Danestves\LaravelPolar\Events\OrderCreated;
use Danestves\LaravelPolar\Events\OrderUpdated;
use Danestves\LaravelPolar\Events\SubscriptionActive;
use Danestves\LaravelPolar\Events\SubscriptionCanceled;
use Danestves\LaravelPolar\Events\SubscriptionCreated;
use Danestves\LaravelPolar\Events\SubscriptionRevoked;
use Danestves\LaravelPolar\Events\SubscriptionUpdated;
use Danestves\LaravelPolar\Events\WebhookHandled;
use Danestves\LaravelPolar\Events\WebhookReceived;
use Danestves\LaravelPolar\Exceptions\InvalidMetadataPayload;
use Danestves\LaravelPolar\Handlers\ProcessWebhook;
use Danestves\LaravelPolar\Order;
use Danestves\LaravelPolar\Subscription;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Support\Facades\Event;
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
            'subscription.created' => $reflection->getMethod('handleSubscriptionCreated')->invoke($this, $data, $timestamp, $type),
            'subscription.updated' => $reflection->getMethod('handleSubscriptionUpdated')->invoke($this, $data, $timestamp, $type),
            'subscription.active' => $reflection->getMethod('handleSubscriptionActive')->invoke($this, $data, $timestamp, $type),
            'subscription.canceled' => $reflection->getMethod('handleSubscriptionCanceled')->invoke($this, $data, $timestamp, $type),
            'subscription.revoked' => $reflection->getMethod('handleSubscriptionRevoked')->invoke($this, $data, $timestamp, $type),
            'benefit_grant.created' => $reflection->getMethod('handleBenefitGrantCreated')->invoke($this, $data, $timestamp, $type),
            'benefit_grant.updated' => $reflection->getMethod('handleBenefitGrantUpdated')->invoke($this, $data, $timestamp, $type),
            'benefit_grant.revoked' => $reflection->getMethod('handleBenefitGrantRevoked')->invoke($this, $data, $timestamp, $type),
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
        'data' => [
            'id' => 'order_123',
            'status' => OrderStatus::Paid->value,
            'amount' => 1000,
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
        ],
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
        'data' => [
            'id' => 'order_123',
            'status' => OrderStatus::Refunded->value,
            'amount' => 1000,
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
        ],
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
        'data' => [
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
        ],
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

it('handles subscription.updated webhook', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->active()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'subscription_123',
    ]);

    $payload = [
        'type' => 'subscription.updated',
        'data' => [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::PastDue->value,
            'product_id' => 'product_456',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ],
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::PastDue);
    expect($subscription->product_id)->toBe('product_456');

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
        'data' => [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ],
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
        'data' => [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Canceled->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => now()->addDays(30)->toIso8601String(),
        ],
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
        'data' => [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Canceled->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => now()->toIso8601String(),
        ],
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
        'data' => [
            'id' => 'benefit_grant_123',
            'type' => 'custom',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ],
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
        'data' => [
            'id' => 'benefit_grant_123',
            'type' => 'custom',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ],
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
        'data' => [
            'id' => 'benefit_grant_123',
            'type' => 'custom',
            'customer_id' => 'customer_123',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                ],
            ],
        ],
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    Event::assertDispatched(BenefitGrantRevoked::class);
});

it('throws exception when metadata is missing', function () {
    $payload = [
        'type' => 'order.created',
        'data' => [
            'id' => 'order_123',
            'status' => OrderStatus::Paid->value,
            'amount' => 1000,
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
        ],
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
        'data' => [
            'id' => 'nonexistent_order',
            'status' => OrderStatus::Paid->value,
            'amount' => 1000,
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
        ],
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
        'data' => [
            'id' => 'nonexistent_subscription',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'current_period_end' => now()->addDays(30)->toIso8601String(),
            'ends_at' => null,
        ],
        'timestamp' => now()->toIso8601String(),
    ];

    $job = createWebhookCall($payload);
    $job->handle();

    expect(Subscription::where('polar_id', 'nonexistent_subscription')->exists())->toBeFalse();
    Event::assertNotDispatched(SubscriptionUpdated::class);
});
