<?php

namespace Danestves\LaravelPolar\Tests;

use Danestves\LaravelPolar\LaravelPolarServiceProvider;
use Illuminate\Foundation\Testing\Concerns\InteractsWithViews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Illuminate\Database\Eloquent\Factories\Factory;

class TestCase extends Orchestra
{
    use RefreshDatabase;
    use InteractsWithViews;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn(string $modelName) => 'Danestves\\LaravelPolar\\Database\\Factories\\' . class_basename($modelName) . 'Factory',
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelPolarServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('app.key', 'base64:EWcFBKBT8lGDNE8nQhTHY+wg19QlfmbhtO9Qnn3NfcA=');
        config()->set('database.default', 'testing');

        $schema = $app['db']->connection()->getSchemaBuilder();

        if (!$schema->hasTable('users')) {
            $schema->create('users', function ($table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->rememberToken();
                $table->timestamps();
            });
        }

        $migrations = require __DIR__ . '/../database/migrations/create_polar_customers_table.php.stub';
        $migrations->up();

        $migrations = require __DIR__ . '/../database/migrations/create_polar_orders_table.php.stub';
        $migrations->up();

        $migrations = require __DIR__ . '/../database/migrations/create_polar_subscriptions_table.php.stub';
        $migrations->up();

        $migrations = require __DIR__ . '/../database/migrations/create_webhook_calls_table.php.stub';
        $migrations->up();
    }
}
