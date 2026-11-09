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
