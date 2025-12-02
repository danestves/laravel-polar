<?php

namespace Danestves\LaravelPolar\Handlers;

use Carbon\Carbon;
use Danestves\LaravelPolar\Events\BenefitCreated;
use Polar\Models\Components\OrderStatus;
use Polar\Models\Components\SubscriptionStatus;
use Danestves\LaravelPolar\Events\BenefitGrantCreated;
use Danestves\LaravelPolar\Events\BenefitGrantRevoked;
use Danestves\LaravelPolar\Events\BenefitGrantUpdated;
use Danestves\LaravelPolar\Events\BenefitUpdated;
use Danestves\LaravelPolar\Events\CheckoutCreated;
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
use Polar\Models\Components;
use Spatie\WebhookClient\Jobs\ProcessWebhookJob;

class ProcessWebhook extends ProcessWebhookJob
{
    private ?\Speakeasy\Serializer\Serializer $serializer = null;

    private function getSerializer(): \Speakeasy\Serializer\Serializer
    {
        if ($this->serializer === null) {
            $this->serializer = \Polar\Utils\JSON::createSerializer();
        }

        return $this->serializer;
    }

    public function handle(): void
    {
        $decoded = json_decode($this->webhookCall, true);
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
            'amount' => $data['amount'],
            'tax_amount' => $data['tax_amount'],
            'refunded_amount' => $data['refunded_amount'],
            'refunded_tax_amount' => $data['refunded_tax_amount'],
            'currency' => $data['currency'],
            'billing_reason' => $data['billing_reason'],
            'customer_id' => $data['customer_id'],
            'product_id' => $data['product_id'],
            'ordered_at' => Carbon::make($data['created_at']),
        ]);

        $payload = $this->createOrderCreatedPayload($data, $timestamp, $type);
        OrderCreated::dispatch($billable, $order, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $order->sync([
            ...$data,
            'status' => $status,
            'refunded_at' => $isRefunded ? Carbon::make($data['refunded_at']) : null,
        ]);

        $payload = $this->createOrderUpdatedPayload($data, $timestamp, $type);
        OrderUpdated::dispatch($billable, $order, $payload, $isRefunded); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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
            'ends_at' => $data['ends_at'] ? Carbon::make($data['ends_at']) : null,
        ]);

        if ($billable->customer->polar_id === null) { // @phpstan-ignore-line property.notFound - the property is found in the billable model
            $billable->customer->update(['polar_id' => $data['customer_id']]); // @phpstan-ignore-line property.notFound - the property is found in the billable model
        }

        $payload = $this->createSubscriptionCreatedPayload($data, $timestamp, $type);
        SubscriptionCreated::dispatch($billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $payload = $this->createSubscriptionUpdatedPayload($data, $timestamp, $type);
        SubscriptionUpdated::dispatch($subscription->billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $payload = $this->createSubscriptionActivePayload($data, $timestamp, $type);
        SubscriptionActive::dispatch($subscription->billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $payload = $this->createSubscriptionCanceledPayload($data, $timestamp, $type);
        SubscriptionCanceled::dispatch($subscription->billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $payload = $this->createSubscriptionRevokedPayload($data, $timestamp, $type);
        SubscriptionRevoked::dispatch($subscription->billable, $subscription, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitGrantCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $payload = $this->createBenefitGrantCreatedPayload($data, $timestamp, $type);
        BenefitGrantCreated::dispatch($billable, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitGrantUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $payload = $this->createBenefitGrantUpdatedPayload($data, $timestamp, $type);
        BenefitGrantUpdated::dispatch($billable, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
    }

    /**
     * Handle the benefit grant revoked event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitGrantRevoked(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $payload = $this->createBenefitGrantRevokedPayload($data, $timestamp, $type);
        BenefitGrantRevoked::dispatch($billable, $payload); // @phpstan-ignore-line argument.type - Billable is a instance of a model
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
     * Create WebhookOrderCreatedPayload from array data.
     */
    private function createOrderCreatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookOrderCreatedPayload
    {
        $order = $this->arrayToOrder($data);
        return new Components\WebhookOrderCreatedPayload($timestamp, $order, $type);
    }

    /**
     * Create WebhookOrderUpdatedPayload from array data.
     */
    private function createOrderUpdatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookOrderUpdatedPayload
    {
        $order = $this->arrayToOrder($data);
        return new Components\WebhookOrderUpdatedPayload($timestamp, $order, $type);
    }

    /**
     * Create WebhookSubscriptionCreatedPayload from array data.
     */
    private function createSubscriptionCreatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookSubscriptionCreatedPayload
    {
        $subscription = $this->arrayToSubscription($data);
        return new Components\WebhookSubscriptionCreatedPayload($timestamp, $subscription, $type);
    }

    /**
     * Create WebhookSubscriptionUpdatedPayload from array data.
     */
    private function createSubscriptionUpdatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookSubscriptionUpdatedPayload
    {
        $subscription = $this->arrayToSubscription($data);
        return new Components\WebhookSubscriptionUpdatedPayload($timestamp, $subscription, $type);
    }

    /**
     * Create WebhookSubscriptionActivePayload from array data.
     */
    private function createSubscriptionActivePayload(array $data, \DateTime $timestamp, string $type): Components\WebhookSubscriptionActivePayload
    {
        $subscription = $this->arrayToSubscription($data);
        return new Components\WebhookSubscriptionActivePayload($timestamp, $subscription, $type);
    }

    /**
     * Create WebhookSubscriptionCanceledPayload from array data.
     */
    private function createSubscriptionCanceledPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookSubscriptionCanceledPayload
    {
        $subscription = $this->arrayToSubscription($data);
        return new Components\WebhookSubscriptionCanceledPayload($timestamp, $subscription, $type);
    }

    /**
     * Create WebhookSubscriptionRevokedPayload from array data.
     */
    private function createSubscriptionRevokedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookSubscriptionRevokedPayload
    {
        $subscription = $this->arrayToSubscription($data);
        return new Components\WebhookSubscriptionRevokedPayload($timestamp, $subscription, $type);
    }

    /**
     * Create WebhookBenefitGrantCreatedPayload from array data.
     */
    private function createBenefitGrantCreatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookBenefitGrantCreatedPayload
    {
        $benefitGrant = $this->arrayToBenefitGrant($data);
        return new Components\WebhookBenefitGrantCreatedPayload($timestamp, $benefitGrant, $type);
    }

    /**
     * Create WebhookBenefitGrantUpdatedPayload from array data.
     */
    private function createBenefitGrantUpdatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookBenefitGrantUpdatedPayload
    {
        $benefitGrant = $this->arrayToBenefitGrant($data);
        return new Components\WebhookBenefitGrantUpdatedPayload($timestamp, $benefitGrant, $type);
    }

    /**
     * Create WebhookBenefitGrantRevokedPayload from array data.
     */
    private function createBenefitGrantRevokedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookBenefitGrantRevokedPayload
    {
        $benefitGrant = $this->arrayToBenefitGrant($data);
        return new Components\WebhookBenefitGrantRevokedPayload($timestamp, $benefitGrant, $type);
    }

    private function arrayToComponent(array $data, string $class): mixed
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException("Failed to encode data to JSON for {$class}: " . json_last_error_msg());
        }
        return $this->getSerializer()->deserialize($json, $class, 'json');
    }

    private function arrayToOrder(array $data): Components\Order
    {
        return $this->arrayToComponent($data, Components\Order::class);
    }

    private function arrayToSubscription(array $data): Components\Subscription
    {
        return $this->arrayToComponent($data, Components\Subscription::class);
    }

    private function arrayToBenefitGrant(array $data): Components\BenefitGrantDiscordWebhook|Components\BenefitGrantCustomWebhook|Components\BenefitGrantGitHubRepositoryWebhook|Components\BenefitGrantDownloadablesWebhook|Components\BenefitGrantLicenseKeysWebhook|Components\BenefitGrantMeterCreditWebhook
    {
        $type = $data['type'] ?? $data['benefit']['type'] ?? 'custom';
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode benefit grant data to JSON: ' . json_last_error_msg());
        }

        $serializer = $this->getSerializer();

        return match ($type) {
            'discord' => $serializer->deserialize($json, Components\BenefitGrantDiscordWebhook::class, 'json'),
            'custom' => $serializer->deserialize($json, Components\BenefitGrantCustomWebhook::class, 'json'),
            'github_repository' => $serializer->deserialize($json, Components\BenefitGrantGitHubRepositoryWebhook::class, 'json'),
            'downloadables' => $serializer->deserialize($json, Components\BenefitGrantDownloadablesWebhook::class, 'json'),
            'license_keys' => $serializer->deserialize($json, Components\BenefitGrantLicenseKeysWebhook::class, 'json'),
            'meter_credit' => $serializer->deserialize($json, Components\BenefitGrantMeterCreditWebhook::class, 'json'),
            default => $serializer->deserialize($json, Components\BenefitGrantCustomWebhook::class, 'json'),
        };
    }

    /**
     * Handle the checkout created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCheckoutCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createCheckoutCreatedPayload($data, $timestamp, $type);
        CheckoutCreated::dispatch($payload);
    }

    /**
     * Handle the checkout updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCheckoutUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createCheckoutUpdatedPayload($data, $timestamp, $type);
        CheckoutUpdated::dispatch($payload);
    }

    /**
     * Handle the customer created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createCustomerCreatedPayload($data, $timestamp, $type);
        CustomerCreated::dispatch($payload);
    }

    /**
     * Handle the customer updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createCustomerUpdatedPayload($data, $timestamp, $type);
        CustomerUpdated::dispatch($payload);
    }

    /**
     * Handle the customer deleted event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerDeleted(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createCustomerDeletedPayload($data, $timestamp, $type);
        CustomerDeleted::dispatch($payload);
    }

    /**
     * Handle the customer state changed event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleCustomerStateChanged(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createCustomerStateChangedPayload($data, $timestamp, $type);
        CustomerStateChanged::dispatch($payload);
    }

    /**
     * Handle the product created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleProductCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createProductCreatedPayload($data, $timestamp, $type);
        ProductCreated::dispatch($payload);
    }

    /**
     * Handle the product updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleProductUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createProductUpdatedPayload($data, $timestamp, $type);
        ProductUpdated::dispatch($payload);
    }

    /**
     * Handle the benefit created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createBenefitCreatedPayload($data, $timestamp, $type);
        BenefitCreated::dispatch($payload);
    }

    /**
     * Handle the benefit updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $payload = $this->createBenefitUpdatedPayload($data, $timestamp, $type);
        BenefitUpdated::dispatch($payload);
    }

    /**
     * Create WebhookCheckoutCreatedPayload from array data.
     */
    private function createCheckoutCreatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookCheckoutCreatedPayload
    {
        $checkout = $this->arrayToCheckout($data);
        return new Components\WebhookCheckoutCreatedPayload($timestamp, $checkout, $type);
    }

    /**
     * Create WebhookCheckoutUpdatedPayload from array data.
     */
    private function createCheckoutUpdatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookCheckoutUpdatedPayload
    {
        $checkout = $this->arrayToCheckout($data);
        return new Components\WebhookCheckoutUpdatedPayload($timestamp, $checkout, $type);
    }

    /**
     * Create WebhookCustomerCreatedPayload from array data.
     */
    private function createCustomerCreatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookCustomerCreatedPayload
    {
        $customer = $this->arrayToCustomer($data);
        return new Components\WebhookCustomerCreatedPayload($timestamp, $customer, $type);
    }

    /**
     * Create WebhookCustomerUpdatedPayload from array data.
     */
    private function createCustomerUpdatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookCustomerUpdatedPayload
    {
        $customer = $this->arrayToCustomer($data);
        return new Components\WebhookCustomerUpdatedPayload($timestamp, $customer, $type);
    }

    /**
     * Create WebhookCustomerDeletedPayload from array data.
     */
    private function createCustomerDeletedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookCustomerDeletedPayload
    {
        $customer = $this->arrayToCustomer($data);
        return new Components\WebhookCustomerDeletedPayload($timestamp, $customer, $type);
    }

    /**
     * Create WebhookCustomerStateChangedPayload from array data.
     */
    private function createCustomerStateChangedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookCustomerStateChangedPayload
    {
        $customerState = $this->arrayToCustomerState($data);
        return new Components\WebhookCustomerStateChangedPayload($timestamp, $customerState, $type);
    }

    /**
     * Create WebhookProductCreatedPayload from array data.
     */
    private function createProductCreatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookProductCreatedPayload
    {
        $product = $this->arrayToProduct($data);
        return new Components\WebhookProductCreatedPayload($timestamp, $product, $type);
    }

    /**
     * Create WebhookProductUpdatedPayload from array data.
     */
    private function createProductUpdatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookProductUpdatedPayload
    {
        $product = $this->arrayToProduct($data);
        return new Components\WebhookProductUpdatedPayload($timestamp, $product, $type);
    }

    /**
     * Create WebhookBenefitCreatedPayload from array data.
     */
    private function createBenefitCreatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookBenefitCreatedPayload
    {
        $benefit = $this->arrayToBenefit($data);
        return new Components\WebhookBenefitCreatedPayload($timestamp, $benefit, $type);
    }

    /**
     * Create WebhookBenefitUpdatedPayload from array data.
     */
    private function createBenefitUpdatedPayload(array $data, \DateTime $timestamp, string $type): Components\WebhookBenefitUpdatedPayload
    {
        $benefit = $this->arrayToBenefit($data);
        return new Components\WebhookBenefitUpdatedPayload($timestamp, $benefit, $type);
    }

    private function arrayToCheckout(array $data): Components\Checkout
    {
        return $this->arrayToComponent($data, Components\Checkout::class);
    }

    private function arrayToCustomer(array $data): Components\Customer
    {
        return $this->arrayToComponent($data, Components\Customer::class);
    }

    private function arrayToCustomerState(array $data): Components\CustomerState
    {
        return $this->arrayToComponent($data, Components\CustomerState::class);
    }

    private function arrayToProduct(array $data): Components\Product
    {
        return $this->arrayToComponent($data, Components\Product::class);
    }

    private function arrayToBenefit(array $data): Components\BenefitCustom|Components\BenefitDiscord|Components\BenefitGitHubRepository|Components\BenefitDownloadables|Components\BenefitLicenseKeys|Components\BenefitMeterCredit
    {
        $type = $data['type'] ?? 'custom';
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode benefit data to JSON: ' . json_last_error_msg());
        }

        $serializer = $this->getSerializer();

        return match ($type) {
            'discord' => $serializer->deserialize($json, Components\BenefitDiscord::class, 'json'),
            'custom' => $serializer->deserialize($json, Components\BenefitCustom::class, 'json'),
            'github_repository' => $serializer->deserialize($json, Components\BenefitGitHubRepository::class, 'json'),
            'downloadables' => $serializer->deserialize($json, Components\BenefitDownloadables::class, 'json'),
            'license_keys' => $serializer->deserialize($json, Components\BenefitLicenseKeys::class, 'json'),
            'meter_credit' => $serializer->deserialize($json, Components\BenefitMeterCredit::class, 'json'),
            default => $serializer->deserialize($json, Components\BenefitCustom::class, 'json'),
        };
    }
}
