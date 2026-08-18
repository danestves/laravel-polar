<?php

namespace Danestves\LaravelPolar\Concerns;

use Carbon\CarbonImmutable;
use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Http\Page;
use Danestves\LaravelPolar\LaravelPolar;

trait ManagesCustomerMeters // @phpstan-ignore-line trait.unused - ManagesCustomerMeters is used in Billable trait
{
    /**
     * Track a single usage event for this customer.
     *
     * Note: Silently returns if customer is not yet created in Polar.
     * This allows fire-and-forget usage tracking without requiring customer setup.
     *
     * @param  array<string, mixed>  $metadata
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function ingestUsageEvent(string $eventName, array $metadata = []): void
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            return;
        }

        LaravelPolar::ingestEvents(new Data\EventsIngest(events: [
            new Data\EventCreateCustomer(
                name: $eventName,
                customerId: $this->customer->polar_id,
                timestamp: CarbonImmutable::now(),
                metadata: $metadata === [] ? null : $metadata,
            ),
        ]));
    }

    /**
     * Track multiple usage events for this customer in a batch.
     *
     * Note: Silently returns if customer is not yet created in Polar.
     * This allows fire-and-forget usage tracking without requiring customer setup.
     *
     * @param  array<int, array{eventName: string, metadata?: array<string, mixed>, timestamp?: \DateTimeInterface}>  $events
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function ingestUsageEvents(array $events): void
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            return;
        }

        if (empty($events)) {
            return;
        }

        $eventObjects = [];

        foreach ($events as $event) {
            $eventObjects[] = new Data\EventCreateCustomer(
                name: $event['eventName'],
                customerId: $this->customer->polar_id,
                timestamp: isset($event['timestamp'])
                    ? CarbonImmutable::instance($event['timestamp'])
                    : CarbonImmutable::now(),
                metadata: $event['metadata'] ?? null,
            );
        }

        LaravelPolar::ingestEvents(new Data\EventsIngest(events: $eventObjects));
    }

    /**
     * List customer meters for this customer.
     *
     * @return Page<Data\CustomerMeter>
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function listCustomerMeters(?string $meterId = null): Page
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw new \Exception('Customer not yet created in Polar.');
        }

        return LaravelPolar::listCustomerMeters([
            'customer_id' => $this->customer->polar_id,
            'meter_id' => $meterId,
        ]);
    }
}
