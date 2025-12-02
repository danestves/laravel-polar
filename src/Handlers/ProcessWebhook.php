<?php

namespace Danestves\LaravelPolar\Handlers;

use Carbon\Carbon;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Events\BenefitGrantCreated;
use Danestves\LaravelPolar\Events\BenefitGrantRevoked;
use Danestves\LaravelPolar\Events\BenefitGrantUpdated;
use Danestves\LaravelPolar\Events\OrderCreated;
use Danestves\LaravelPolar\Events\OrderUpdated;
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
    public function handle(): void
    {
        $decoded = json_decode($this->webhookCall, true);
        $payload = $decoded['payload'];
        $type = $payload['type'];
        $data = $payload['data'];
        $timestamp = isset($payload['timestamp']) ? new \DateTime($payload['timestamp']) : new \DateTime();

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
            default => Log::info("Unknown event type: $type"),
        };

        WebhookHandled::dispatch($payload);

        // Acknowledge you received the response
        http_response_code(200);
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
            'status' => $data['status'],
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
            'status' => $data['status'],
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

    /**
     * Convert array to SDK Order object using SDK's JSON serializer.
     */
    private function arrayToOrder(array $data): Components\Order
    {
        $serializer = \Polar\Utils\JSON::createSerializer();
        $json = json_encode($data);
        return $serializer->deserialize($json, Components\Order::class, 'json');
    }

    /**
     * Convert array to SDK Subscription object using SDK's JSON serializer.
     */
    private function arrayToSubscription(array $data): Components\Subscription
    {
        $serializer = \Polar\Utils\JSON::createSerializer();
        $json = json_encode($data);
        return $serializer->deserialize($json, Components\Subscription::class, 'json');
    }

    /**
     * Convert array to SDK BenefitGrant object (union type) using SDK's JSON serializer.
     */
    private function arrayToBenefitGrant(array $data): Components\BenefitGrantDiscordWebhook|Components\BenefitGrantCustomWebhook|Components\BenefitGrantGitHubRepositoryWebhook|Components\BenefitGrantDownloadablesWebhook|Components\BenefitGrantLicenseKeysWebhook|Components\BenefitGrantMeterCreditWebhook
    {
        $type = $data['type'] ?? $data['benefit']['type'] ?? 'custom';
        $serializer = \Polar\Utils\JSON::createSerializer();
        $json = json_encode($data);

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
}
