<?php

namespace Danestves\LaravelPolar\Concerns;

use Danestves\LaravelPolar\LaravelPolar;
use Polar\Models\Components;
use Polar\Models\Operations;

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
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function ingestUsageEvent(string $eventName, array $metadata = []): void
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            return;
        }

        $event = new Components\EventCreateCustomer(
            name: $eventName,
            customerId: $this->customer->polar_id,
            timestamp: new \DateTime(),
            metadata: empty($metadata) ? null : $metadata,
        );

        $request = new Components\EventsIngest(
            events: [$event],
        );

        LaravelPolar::ingestEvents($request);
    }

    /**
     * Track multiple usage events for this customer in a batch.
     *
     * Note: Silently returns if customer is not yet created in Polar.
     * This allows fire-and-forget usage tracking without requiring customer setup.
     *
     * @param  array<int, array{eventName: string, metadata?: array<string, mixed>, timestamp?: \DateTime}>  $events
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
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
            $eventObjects[] = new Components\EventCreateCustomer(
                name: $event['eventName'],
                customerId: $this->customer->polar_id,
                timestamp: $event['timestamp'] ?? new \DateTime(),
                metadata: $event['metadata'] ?? null,
            );
        }

        $request = new Components\EventsIngest(
            events: $eventObjects,
        );

        LaravelPolar::ingestEvents($request);
    }

    /**
     * List customer meters for this customer.
     *
     * @throws \Polar\Models\Errors\APIException
     * @throws \Exception
     */
    public function listCustomerMeters(?string $meterId = null): Operations\CustomerMetersListResponse
    {
        if ($this->customer === null || $this->customer->polar_id === null) {
            throw new \Exception('Customer not yet created in Polar.');
        }

        $request = new Operations\CustomerMetersListRequest(
            customerId: $this->customer->polar_id,
            meterId: $meterId !== null ? [$meterId] : null,
        );

        return LaravelPolar::listCustomerMeters($request);
    }
}
