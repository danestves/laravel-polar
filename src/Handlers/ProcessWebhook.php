<?php

namespace Danestves\LaravelPolar\Handlers;

use Carbon\Carbon;
use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Events\BenefitCreated;
use Danestves\LaravelPolar\Events\BenefitGrantCreated;
use Danestves\LaravelPolar\Events\BenefitGrantRevoked;
use Danestves\LaravelPolar\Events\BenefitGrantUpdated;
use Danestves\LaravelPolar\Events\BenefitUpdated;
use Danestves\LaravelPolar\Events\CheckoutCreated;
use Danestves\LaravelPolar\Events\CheckoutExpired;
use Danestves\LaravelPolar\Events\CheckoutUpdated;
use Danestves\LaravelPolar\Events\CustomerCreated;
use Danestves\LaravelPolar\Events\CustomerDeleted;
use Danestves\LaravelPolar\Events\CustomerStateChanged;
use Danestves\LaravelPolar\Events\CustomerUpdated;
use Danestves\LaravelPolar\Events\OrderCreated;
use Danestves\LaravelPolar\Events\OrderUpdated;
use Danestves\LaravelPolar\Events\ProductCreated;
use Danestves\LaravelPolar\Events\ProductUpdated;
use Danestves\LaravelPolar\Events\SubscriptionActive;
use Danestves\LaravelPolar\Events\SubscriptionCanceled;
use Danestves\LaravelPolar\Events\SubscriptionCreated;
use Danestves\LaravelPolar\Events\SubscriptionRevoked;
use Danestves\LaravelPolar\Events\SubscriptionUpdated;
use Danestves\LaravelPolar\Events\WebhookHandled;
use Danestves\LaravelPolar\Events\WebhookReceived;
use Danestves\LaravelPolar\Exceptions\InvalidMetadataPayload;
use Danestves\LaravelPolar\LaravelPolar;
use Danestves\LaravelPolar\Order as EloquentOrder;
use Danestves\LaravelPolar\Subscription as EloquentSubscription;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessWebhook extends ProcessWebhookJob
{
    public function handle(): void
    {
        $decoded = json_decode($this->webhookCall, true);
        if ($decoded === null || !isset($decoded['payload'])) {
            Log::error('Invalid webhook payload: failed to decode JSON or missing payload');
            return;
        }
        $payload = $decoded['payload'];
        $type = $payload['type'];
        $data = $payload['data'];
        $timestamp = $this->parseTimestamp($payload['timestamp'] ?? null);

        WebhookReceived::dispatch($payload);

        match ($type) {
            'order.created' => $this->handleOrderCreated($data, $timestamp, $type),
            'order.updated' => $this->handleOrderUpdated($data, $timestamp, $type),
            'subscription.created' => $this->handleSubscriptionCreated($data, $timestamp, $type),
            'subscription.updated' => $this->handleSubscriptionUpdated($data, $timestamp, $type),
            'subscription.active' => $this->handleSubscriptionActive($data, $timestamp, $type),
            'subscription.canceled' => $this->handleSubscriptionCanceled($data, $timestamp, $type),
            'subscription.revoked' => $this->handleSubscriptionRevoked($data, $timestamp, $type),
            'benefit_grant.created' => $this->handleBenefitGrantCreated($data, $timestamp, $type),
            'benefit_grant.updated' => $this->handleBenefitGrantUpdated($data, $timestamp, $type),
            'benefit_grant.revoked' => $this->handleBenefitGrantRevoked($data, $timestamp, $type),
            'checkout.created' => $this->handleCheckoutCreated($data, $timestamp, $type),
            'checkout.updated' => $this->handleCheckoutUpdated($data, $timestamp, $type),
            'checkout.expired' => $this->handleCheckoutExpired($data, $timestamp, $type),
            'customer.created' => $this->handleCustomerCreated($data, $timestamp, $type),
            'customer.updated' => $this->handleCustomerUpdated($data, $timestamp, $type),
            'customer.deleted' => $this->handleCustomerDeleted($data, $timestamp, $type),
            'customer.state_changed' => $this->handleCustomerStateChanged($data, $timestamp, $type),
            'product.created' => $this->handleProductCreated($data, $timestamp, $type),
            'product.updated' => $this->handleProductUpdated($data, $timestamp, $type),
            'benefit.created' => $this->handleBenefitCreated($data, $timestamp, $type),
            'benefit.updated' => $this->handleBenefitUpdated($data, $timestamp, $type),
            default => Log::info("Unknown event type: $type"),
        };

        WebhookHandled::dispatch($payload);
    }

    /**
     * Handle the order created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleOrderCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $order = $billable->orders()->create([ // @phpstan-ignore-line class.notFound - the property is found in the billable model
            'polar_id' => $data['id'],
            'status' => \is_string($data['status']) ? OrderStatus::from($data['status']) : $data['status'],
            'amount' => EloquentOrder::netAmount($data),
            'tax_amount' => $data['tax_amount'],
            'refunded_amount' => $data['refunded_amount'],
            'refunded_tax_amount' => $data['refunded_tax_amount'],
            'currency' => $data['currency'],
            'billing_reason' => $data['billing_reason'],
            'customer_id' => $data['customer_id'],
            'product_id' => $data['product_id'],
            'ordered_at' => Carbon::make($data['created_at']),
        ]);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookOrderCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => OrderCreated::dispatch($billable, $order, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the order updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleOrderUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        if (!($order = $this->findOrder($data['id'])) instanceof EloquentOrder) {
            Log::warning('Order not found for webhook update', [
                'order_id' => $data['id'],
                'event_type' => $type,
            ]);
            return;
        }

        $status = $data['status'];
        $isRefunded = $status === OrderStatus::Refunded->value || $status === OrderStatus::PartiallyRefunded->value;

        $order->sync([...$data, 'status' => $status]);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookOrderUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => OrderUpdated::dispatch($billable, $order, $payload, $isRefunded), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the subscription created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $customerMetadata = $data['customer']['metadata'];
        $billable = $this->resolveBillable($data);

        $subscription = $billable->subscriptions()->create([ // @phpstan-ignore-line class.notFound - the property is found in the billable model
            'type' => $customerMetadata['subscription_type'] ?? 'default',
            'polar_id' => $data['id'],
            'status' => \is_string($data['status']) ? SubscriptionStatus::from($data['status']) : $data['status'],
            'product_id' => $data['product_id'],
            'current_period_end' => $data['current_period_end'] ? Carbon::make($data['current_period_end']) : null,
            'trial_ends_at' => isset($data['trial_end']) ? Carbon::make($data['trial_end']) : null,
            'ends_at' => $data['ends_at'] ? Carbon::make($data['ends_at']) : null,
        ]);

        if ($billable->customer->polar_id === null) { // @phpstan-ignore-line property.notFound - the property is found in the billable model
            $billable->customer->update(['polar_id' => $data['customer_id']]); // @phpstan-ignore-line property.notFound - the property is found in the billable model
        }

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookSubscriptionCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => SubscriptionCreated::dispatch($billable, $subscription, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the subscription updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        if (!($subscription = $this->findSubscription($data['id'])) instanceof EloquentSubscription) {
            Log::warning('Subscription not found for webhook update', [
                'subscription_id' => $data['id'],
                'event_type' => $type,
            ]);
            return;
        }

        $subscription->sync($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookSubscriptionUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => SubscriptionUpdated::dispatch($subscription->billable, $subscription, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the subscription active event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionActive(array $data, \DateTime $timestamp, string $type): void
    {
        if (!($subscription = $this->findSubscription($data['id'])) instanceof EloquentSubscription) {
            Log::warning('Subscription not found for webhook active event', [
                'subscription_id' => $data['id'],
                'event_type' => $type,
            ]);
            return;
        }

        $subscription->sync($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookSubscriptionActivePayload::class, $data, $timestamp, $type),
            fn($payload) => SubscriptionActive::dispatch($subscription->billable, $subscription, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the subscription canceled event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionCanceled(array $data, \DateTime $timestamp, string $type): void
    {
        if (!($subscription = $this->findSubscription($data['id'])) instanceof EloquentSubscription) {
            Log::warning('Subscription not found for webhook canceled event', [
                'subscription_id' => $data['id'],
                'event_type' => $type,
            ]);
            return;
        }

        $subscription->sync($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookSubscriptionCanceledPayload::class, $data, $timestamp, $type),
            fn($payload) => SubscriptionCanceled::dispatch($subscription->billable, $subscription, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the subscription revoked event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionRevoked(array $data, \DateTime $timestamp, string $type): void
    {
        if (!($subscription = $this->findSubscription($data['id'])) instanceof EloquentSubscription) {
            Log::warning('Subscription not found for webhook revoked event', [
                'subscription_id' => $data['id'],
                'event_type' => $type,
            ]);
            return;
        }

        $subscription->sync($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookSubscriptionRevokedPayload::class, $data, $timestamp, $type),
            fn($payload) => SubscriptionRevoked::dispatch($subscription->billable, $subscription, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the benefit grant created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitGrantCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->benefitGrantPayload(Data\WebhookBenefitGrantCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => BenefitGrantCreated::dispatch($billable, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the benefit grant updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitGrantUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->benefitGrantPayload(Data\WebhookBenefitGrantUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => BenefitGrantUpdated::dispatch($billable, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the benefit grant revoked event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitGrantRevoked(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->benefitGrantPayload(Data\WebhookBenefitGrantRevokedPayload::class, $data, $timestamp, $type),
            fn($payload) => BenefitGrantRevoked::dispatch($billable, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Resolve the billable from the payload.
     *
     * @param  array<string, mixed>  $payload
     * @return \Danestves\LaravelPolar\Billable
     *
     * @throws InvalidMetadataPayload
     */
    private function resolveBillable(array $payload) // @phpstan-ignore-line return.trait - Billable is used in the user final code
    {
        $customerMetadata = $payload['customer']['metadata'] ?? null;

        if (!isset($customerMetadata) || !is_array($customerMetadata) || !isset($customerMetadata['billable_id'], $customerMetadata['billable_type'])) {
            throw new InvalidMetadataPayload();
        }

        return $this->findOrCreateCustomer(
            $customerMetadata['billable_id'],
            (string) $customerMetadata['billable_type'],
            (string) $payload['customer_id'],
        );
    }

    /**
     * Find or create a customer.
     *
     * @return \Danestves\LaravelPolar\Billable
     */
    private function findOrCreateCustomer(int|string $billableId, string $billableType, string $customerId) // @phpstan-ignore-line return.trait - Billable is used in the user final code
    {
        return LaravelPolar::$customerModel::firstOrCreate([
            'billable_id' => $billableId,
            'billable_type' => $billableType,
        ], [
            'polar_id' => $customerId,
        ])->billable;
    }

    private function findSubscription(string $subscriptionId): ?EloquentSubscription
    {
        return LaravelPolar::$subscriptionModel::firstWhere('polar_id', $subscriptionId);
    }

    private function findOrder(string $orderId): ?EloquentOrder
    {
        return LaravelPolar::$orderModel::firstWhere('polar_id', $orderId);
    }

    private function parseTimestamp($timestampValue): \DateTime
    {
        if ($timestampValue === null) {
            return new \DateTime();
        }

        $parsed = \DateTime::createFromFormat(\DateTime::ATOM, $timestampValue);
        if ($parsed !== false) {
            return $parsed;
        }

        $parsed = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $timestampValue);
        if ($parsed !== false) {
            return $parsed;
        }

        $timestamp = strtotime($timestampValue);
        if ($timestamp !== false) {
            $dateTime = new \DateTime();
            $dateTime->setTimestamp($timestamp);
            return $dateTime;
        }

        try {
            return new \DateTime($timestampValue);
        } catch (\Exception $e) {
            Log::warning('Failed to parse webhook timestamp', [
                'timestamp' => $timestampValue,
                'error' => $e->getMessage(),
            ]);

            return new \DateTime();
        }
    }

    /**
     * Build a typed payload and hand it to the event dispatcher, tolerating payloads this
     * package cannot yet parse.
     *
     * Local records are synced from the raw webhook array before this runs, and that sync is the
     * part your application cannot afford to lose. Typing the payload is strict by design — it is
     * what makes listeners safe to write — but Polar can add a required field or a new enum value
     * at any time, and a package that predates the change should not take billing sync down with
     * it. So a payload that will not build is logged loudly and its event is skipped; the record
     * is already correct.
     *
     * The event is not retried, because retrying cannot help: the fix is to regenerate the data
     * objects (`composer generate-data`) against Polar's current schema.
     *
     * @template TPayload of \Spatie\LaravelData\Data
     *
     * @param  callable(): TPayload  $build
     * @param  callable(TPayload): void  $dispatch
     */
    private function dispatchEvent(string $type, callable $build, callable $dispatch): void
    {
        try {
            $payload = $build();
        } catch (\Throwable $e) {
            Log::error('Polar webhook payload could not be parsed, so its event was not dispatched. Any local record was still synced. Run "composer generate-data" to refresh the data objects against Polar\'s current schema.', [
                'event_type' => $type,
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        $dispatch($payload);
    }

    /**
     * Build a typed webhook payload from the raw event.
     *
     * Polar's webhook envelope is `{type, timestamp, data}`, which is exactly the shape of the
     * generated payload classes, so the whole event hydrates in one step.
     *
     * @template TPayload of \Spatie\LaravelData\Data
     *
     * @param  class-string<TPayload>  $payloadClass
     * @param  array<string, mixed>  $data
     * @return TPayload
     */
    private function payload(string $payloadClass, array $data, \DateTimeInterface $timestamp, string $type): mixed
    {
        return $payloadClass::from([
            'type' => $type,
            'timestamp' => $timestamp->format(\DateTimeInterface::ATOM),
            'data' => $data,
        ]);
    }

    /**
     * Benefit grants are discriminated by their nested benefit type, so the grant is resolved
     * before the envelope is assembled.
     *
     * @template TPayload of \Spatie\LaravelData\Data
     *
     * @param  class-string<TPayload>  $payloadClass
     * @param  array<string, mixed>  $data
     * @return TPayload
     */
    private function benefitGrantPayload(string $payloadClass, array $data, \DateTimeInterface $timestamp, string $type): mixed
    {
        return $payloadClass::from([
            'type' => $type,
            'timestamp' => $timestamp->format(\DateTimeInterface::ATOM),
            'data' => Data\BenefitGrantWebhook::resolve($data),
        ]);
    }

    /**
     * Handle the checkout created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCheckoutCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookCheckoutCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => CheckoutCreated::dispatch($payload),
        );
    }

    /**
     * Handle the checkout updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCheckoutUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookCheckoutUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => CheckoutUpdated::dispatch($payload),
        );
    }

    /**
     * Handle the checkout expired event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCheckoutExpired(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookCheckoutExpiredPayload::class, $data, $timestamp, $type),
            fn($payload) => CheckoutExpired::dispatch($payload),
        );
    }

    /**
     * Handle the customer created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookCustomerCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => CustomerCreated::dispatch($payload),
        );
    }

    /**
     * Handle the customer updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookCustomerUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => CustomerUpdated::dispatch($payload),
        );
    }

    /**
     * Handle the customer deleted event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerDeleted(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookCustomerDeletedPayload::class, $data, $timestamp, $type),
            fn($payload) => CustomerDeleted::dispatch($payload),
        );
    }

    /**
     * Handle the customer state changed event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerStateChanged(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookCustomerStateChangedPayload::class, $data, $timestamp, $type),
            fn($payload) => CustomerStateChanged::dispatch($payload),
        );
    }

    /**
     * Handle the product created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleProductCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookProductCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => ProductCreated::dispatch($payload),
        );
    }

    /**
     * Handle the product updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleProductUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookProductUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => ProductUpdated::dispatch($payload),
        );
    }

    /**
     * Handle the benefit created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookBenefitCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => BenefitCreated::dispatch($payload),
        );
    }

    /**
     * Handle the benefit updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookBenefitUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => BenefitUpdated::dispatch($payload),
        );
    }
}
