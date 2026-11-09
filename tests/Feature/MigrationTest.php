<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('applies both migrations on a fresh install without conflicting', function () {
    Schema::dropIfExists('polar_subscriptions');

    $create = require __DIR__ . '/../../database/migrations/create_polar_subscriptions_table.php.stub';
    $create->up();

    $add = require __DIR__ . '/../../database/migrations/update_polar_tables_with_lifecycle_columns.php.stub';
    $add->up();

    foreach ([
        'discount_id',
        'current_period_start',
        'cancel_at_period_end',
        'canceled_at',
        'pause_at_period_end',
        'paused_at',
        'resumes_at',
        'started_at',
        'past_due_at',
        'polar_updated_at_us',
    ] as $column) {
        expect(Schema::hasColumn('polar_subscriptions', $column))->toBeTrue();
    }
});

it('upgrades a legacy subscriptions table in place', function () {
    Schema::dropIfExists('polar_subscriptions');

    Schema::create('polar_subscriptions', function (Blueprint $table) {
        $table->id();
        $table->morphs('billable');
        $table->string('type');
        $table->string('polar_id')->unique();
        $table->string('status');
        $table->string('product_id');
        $table->timestamp('current_period_end')->nullable();
        $table->timestamp('trial_ends_at')->nullable();
        $table->timestamp('ends_at')->nullable();
        $table->timestamps();
    });

    $add = require __DIR__ . '/../../database/migrations/update_polar_tables_with_lifecycle_columns.php.stub';
    $add->up();

    expect(Schema::hasColumn('polar_subscriptions', 'pause_at_period_end'))->toBeTrue()
        ->and(Schema::hasColumn('polar_subscriptions', 'resumes_at'))->toBeTrue()
        ->and(Schema::hasColumn('polar_subscriptions', 'polar_updated_at_us'))->toBeTrue()
        ->and(Schema::hasColumn('polar_orders', 'polar_updated_at_us'))->toBeTrue();
});

it('adds a unique index to polar_orders.polar_id', function () {
    $migration = require __DIR__ . '/../../database/migrations/unique_polar_orders_polar_id.php.stub';
    $migration->up();

    $indexes = collect(Schema::getIndexes('polar_orders'));
    $unique = $indexes->first(fn($index) => str_contains($index['name'], 'polar_id') && $index['unique']);

    expect($unique)->not->toBeNull();

    $migration->down();

    $afterDown = collect(Schema::getIndexes('polar_orders'));

    expect($afterDown->first(fn($index) => str_contains($index['name'], 'polar_id') && $index['unique']))->toBeNull();
});

it('aborts the unique order index when duplicates already exist', function () {
    $row = [
        'billable_type' => 'users',
        'billable_id' => 1,
        'polar_id' => 'duplicate_order',
        'status' => 'paid',
        'amount' => 100,
        'tax_amount' => 0,
        'refunded_amount' => 0,
        'refunded_tax_amount' => 0,
        'currency' => 'USD',
        'billing_reason' => 'purchase',
        'customer_id' => 'customer_123',
        'product_id' => 'product_123',
        'ordered_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ];

    \Illuminate\Support\Facades\DB::table('polar_orders')->insert([$row, $row]);

    $migration = require __DIR__ . '/../../database/migrations/unique_polar_orders_polar_id.php.stub';

    expect(fn() => $migration->up())->toThrow(\RuntimeException::class, 'duplicate polar_id values');

    $indexes = collect(Schema::getIndexes('polar_orders'));
    $unique = $indexes->first(fn($index) => str_contains($index['name'], 'polar_id') && $index['unique']);

    expect($unique)->toBeNull();
});

it('adds external_id to a legacy polar_customers table and reverses it', function () {
    Schema::dropIfExists('polar_customers');

    Schema::create('polar_customers', function (Blueprint $table) {
        $table->id();
        $table->morphs('billable');
        $table->string('polar_id')->nullable()->unique();
        $table->timestamp('trial_ends_at')->nullable();
        $table->timestamps();
    });

    $migration = require __DIR__ . '/../../database/migrations/store_external_id_on_polar_customers.php.stub';
    $migration->up();

    expect(Schema::hasColumn('polar_customers', 'external_id'))->toBeTrue();

    $migration->down();

    expect(Schema::hasColumn('polar_customers', 'external_id'))->toBeFalse();
});
