<?php

namespace Danestves\LaravelPolar\Database\Factories;

use Carbon\CarbonInterface;
use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Subscription>
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array{
     *     billable_id: int,
     *     billable_type: string,
     *     type: string,
     *     polar_id: string,
     *     status: SubscriptionStatus,
     *     product_id: string,
     *     price_id: string,
     *     current_period_end: CarbonInterface
     * }
     */
    public function definition(): array
    {
        return [
            'billable_id' => $this->faker->randomNumber(),
            'billable_type' => 'App\\Models\\User',
            'type' => 'default',
            'polar_id' => $this->faker->uuid,
            'status' => SubscriptionStatus::Active,
            'product_id' => $this->faker->uuid,
            'price_id' => $this->faker->uuid,
            'current_period_end' => now()->addDays(30),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): self
    {
        return $this->afterCreating(function ($subscription) {
            Customer::factory()->create([
                'billable_id' => $subscription->billable_id,
                'billable_type' => $subscription->billable_type,
            ]);
        });
    }

    /**
     * Mark the subscription as active.
     */
    public function active(): self
    {
        return $this->state([
            'status' => SubscriptionStatus::Active,
        ]);
    }

    /**
     * Mark the subscription as past due.
     */
    public function pastDue(): self
    {
        return $this->state([
            'status' => SubscriptionStatus::PastDue,
        ]);
    }

    /**
     * Mark the subscription as unpaid.
     */
    public function unpaid(): self
    {
        return $this->state([
            'status' => SubscriptionStatus::Unpaid,
        ]);
    }

    /**
     * Mark the subscription as cancelled.
     */
    public function cancelled(): self
    {
        return $this->state([
            'status' => SubscriptionStatus::Canceled,
            'ends_at' => now(),
        ]);
    }

    /**
     * Mark the subscription as expired
     */
    public function incompleteExpired(): self
    {
        return $this->state([
            'status' => SubscriptionStatus::IncompleteExpired,
        ]);
    }
}
