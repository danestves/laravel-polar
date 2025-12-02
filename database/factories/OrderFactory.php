<?php

namespace Danestves\LaravelPolar\Database\Factories;

use Danestves\LaravelPolar\Customer;
use Danestves\LaravelPolar\Order;
use Polar\Models\Components\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Order>
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array{
     *     billable_id: int,
     *     billable_type: string,
     *     polar_id: string,
     *     status: OrderStatus,
     *     amount: int,
     *     tax_amount: int,
     *     refunded_amount: int,
     *     refunded_tax_amount: int,
     *     currency: string,
     *     billing_reason: string,
     *     customer_id: string,
     *     product_id: string,
     *     refunded_at: \Illuminate\Support\Carbon|null,
     *     ordered_at: \Illuminate\Support\Carbon,
     * }
     */
    public function definition(): array
    {
        return [
            'billable_id' => $this->faker->randomNumber(),
            'billable_type' => 'App\\Models\\User',
            'polar_id' => $this->faker->uuid,
            'status' => OrderStatus::Paid,
            'amount' => $this->faker->randomNumber(),
            'tax_amount' => $this->faker->randomNumber(),
            'refunded_amount' => $this->faker->randomNumber(),
            'refunded_tax_amount' => $this->faker->randomNumber(),
            'currency' => 'USD',
            'billing_reason' => $this->faker->randomElement(['purchase', 'subscription_create', 'subscription_cycle', 'subscription_update']),
            'customer_id' => $this->faker->uuid,
            'product_id' => $this->faker->uuid,
            'refunded_at' => null,
            'ordered_at' => now(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): self
    {
        return $this->afterCreating(function ($order) {
            Customer::factory()->create([
                'billable_id' => $order->billable_id,
                'billable_type' => $order->billable_type,
            ]);
        });
    }

    /**
     * Mark the order as paid.
     */
    public function paid(): self
    {
        return $this->state([
            'status' => OrderStatus::Paid,
        ]);
    }

    /**
     * Mark the order as refunded.
     */
    public function refunded(): self
    {
        return $this->state([
            'status' => OrderStatus::Refunded,
        ]);
    }

    /**
     * Mark the order as partially refunded.
     */
    public function partiallyRefunded(): self
    {
        return $this->state([
            'status' => OrderStatus::PartiallyRefunded,
        ]);
    }
}
