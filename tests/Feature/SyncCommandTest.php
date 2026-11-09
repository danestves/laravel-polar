<?php

use Danestves\LaravelPolar\Commands\SyncCommand;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Order;
use Danestves\LaravelPolar\Subscription;
use Danestves\LaravelPolar\Tests\Fixtures\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;

function syncCommandOrderPayload(string $id, User $user, array $overrides = []): array
{
    return polarFixture('Order', array_merge([
        'id' => $id,
        'status' => OrderStatus::Paid->value,
        'net_amount' => 1500,
        'tax_amount' => 150,
        'refunded_amount' => 0,
        'refunded_tax_amount' => 0,
        'currency' => 'USD',
        'billing_reason' => 'purchase',
        'customer_id' => 'customer_123',
        'product_id' => 'product_123',
        'created_at' => '2026-01-01T00:00:00Z',
        'modified_at' => '2026-01-02T00:00:00Z',
        'customer' => [
            'id' => 'customer_123',
            'metadata' => [
                'billable_id' => (string) $user->getKey(),
                'billable_type' => $user->getMorphClass(),
            ],
        ],
    ], $overrides));
}

beforeEach(function () {
    Relation::enforceMorphMap([
        'users' => User::class,
    ]);

    // The package provider registers the command in real applications. Register it here so the
    // test does not depend on that wiring landing first.
    $kernel = $this->app->make(Kernel::class);

    if ($kernel->findCommand('polar:sync') === null) {
        $kernel->registerCommand($this->app->make(SyncCommand::class));
    }
});

it('syncs orders across every page', function () {
    $user = User::factory()->create();

    fakePolarList('v1/orders/?*page=1*', [syncCommandOrderPayload('order_page_1', $user)], 2, 2);
    fakePolarList('v1/orders/?*page=2*', [syncCommandOrderPayload('order_page_2', $user)], 2, 2);

    $this->artisan('polar:sync', ['--resource' => 'orders'])->assertExitCode(0);

    expect(Order::query()->count())->toBe(2);

    $first = Order::query()->where('polar_id', 'order_page_1')->first();

    expect($first)->not->toBeNull()
        ->and($first->billable_id)->toBe($user->getKey())
        ->and($first->billable_type)->toBe('users')
        ->and($first->status)->toBe(OrderStatus::Paid)
        ->and($first->amount)->toBe(1500)
        ->and($first->tax_amount)->toBe(150)
        ->and($first->refunded_amount)->toBe(0)
        ->and($first->currency)->toBe('USD')
        ->and($first->billing_reason)->toBe('purchase')
        ->and($first->customer_id)->toBe('customer_123')
        ->and($first->product_id)->toBe('product_123')
        ->and($first->ordered_at)->not->toBeNull();

    expect(Order::query()->where('polar_id', 'order_page_2')->exists())->toBeTrue();
});

it('updates an existing order instead of duplicating it', function () {
    $user = User::factory()->create();

    Order::factory()->create([
        'billable_id' => $user->getKey(),
        'billable_type' => $user->getMorphClass(),
        'polar_id' => 'order_existing',
        'amount' => 1,
    ]);

    fakePolarList('v1/orders/?*page=1*', [syncCommandOrderPayload('order_existing', $user)], 1, 1);

    $this->artisan('polar:sync', ['--resource' => 'orders'])->assertExitCode(0);

    expect(Order::query()->count())->toBe(1);

    $order = Order::query()->where('polar_id', 'order_existing')->first();

    expect($order->amount)->toBe(1500)
        ->and($order->status)->toBe(OrderStatus::Paid);
});

it('syncs subscriptions and keeps the subscription type from customer metadata', function () {
    $user = User::factory()->create();

    fakePolarList('v1/subscriptions/?*page=1*', [
        polarFixture('Subscription', [
            'id' => 'subscription_123',
            'status' => SubscriptionStatus::Active->value,
            'product_id' => 'product_123',
            'customer_id' => 'customer_123',
            'current_period_end' => '2026-02-01T00:00:00Z',
            'modified_at' => '2026-01-02T00:00:00Z',
            'customer' => [
                'id' => 'customer_123',
                'metadata' => [
                    'billable_id' => (string) $user->getKey(),
                    'billable_type' => $user->getMorphClass(),
                    'subscription_type' => 'premium',
                ],
            ],
        ]),
    ], 1, 1);

    $this->artisan('polar:sync', ['--resource' => 'subscriptions'])->assertExitCode(0);

    $subscription = Subscription::query()->where('polar_id', 'subscription_123')->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->billable_id)->toBe($user->getKey())
        ->and($subscription->billable_type)->toBe('users')
        ->and($subscription->type)->toBe('premium')
        ->and($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->product_id)->toBe('product_123')
        ->and($subscription->current_period_end)->not->toBeNull();
});

it('does not write anything during a dry run', function () {
    $user = User::factory()->create();

    fakePolarList('v1/orders/?*page=1*', [syncCommandOrderPayload('order_dry_run', $user)], 1, 1);

    $this->artisan('polar:sync', ['--resource' => 'orders', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertExitCode(0);

    expect(Order::query()->count())->toBe(0);
});

it('skips orders it cannot resolve to a local billable', function () {
    fakePolarList('v1/orders/?*page=1*', [
        polarFixture('Order', [
            'id' => 'order_without_billable',
            'customer' => [
                'id' => 'customer_orphan',
                'metadata' => [],
            ],
        ]),
    ], 1, 1);

    $this->artisan('polar:sync', ['--resource' => 'orders'])
        ->expectsOutputToContain('order_without_billable')
        ->assertExitCode(0);

    expect(Order::query()->count())->toBe(0);
});

it('rejects an unknown resource', function () {
    $this->artisan('polar:sync', ['--resource' => 'invoices'])->assertExitCode(1);
});
