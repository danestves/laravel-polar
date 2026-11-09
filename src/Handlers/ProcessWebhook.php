<?php

namespace Danestves\LaravelPolar\Handlers;

use Carbon\Carbon;
use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Enums\SubscriptionStatus;
use Danestves\LaravelPolar\Events\BenefitCreated;
use Danestves\LaravelPolar\Events\BenefitGrantCreated;
use Danestves\LaravelPolar\Events\BenefitGrantCycled;
use Danestves\LaravelPolar\Events\BenefitGrantRevoked;
use Danestves\LaravelPolar\Events\BenefitGrantUpdated;
use Danestves\LaravelPolar\Events\BenefitUpdated;
use Danestves\LaravelPolar\Events\CheckoutCreated;
use Danestves\LaravelPolar\Events\CheckoutExpired;
use Danestves\LaravelPolar\Events\CheckoutUpdated;
use Danestves\LaravelPolar\Events\CustomerCreated;
use Danestves\LaravelPolar\Events\CustomerDeleted;
use Danestves\LaravelPolar\Events\CustomerSeatAssigned;
use Danestves\LaravelPolar\Events\CustomerSeatClaimed;
use Danestves\LaravelPolar\Events\CustomerSeatRevoked;
use Danestves\LaravelPolar\Events\CustomerStateChanged;
use Danestves\LaravelPolar\Events\CustomerUpdated;
use Danestves\LaravelPolar\Events\MemberCreated;
use Danestves\LaravelPolar\Events\MemberDeleted;
use Danestves\LaravelPolar\Events\MemberUpdated;
use Danestves\LaravelPolar\Events\DiscountCreated;
use Danestves\LaravelPolar\Events\DiscountDeleted;
use Danestves\LaravelPolar\Events\DiscountUpdated;
use Danestves\LaravelPolar\Events\OrderCreated;
use Danestves\LaravelPolar\Events\OrderPaid;
use Danestves\LaravelPolar\Events\OrderRefunded;
use Danestves\LaravelPolar\Events\OrderUpdated;
use Danestves\LaravelPolar\Events\OrganizationUpdated;
use Danestves\LaravelPolar\Events\ProductCreated;
use Danestves\LaravelPolar\Events\ProductUpdated;
use Danestves\LaravelPolar\Events\RefundCreated;
use Danestves\LaravelPolar\Events\RefundUpdated;
use Danestves\LaravelPolar\Events\SubscriptionActive;
use Danestves\LaravelPolar\Events\SubscriptionCanceled;
use Danestves\LaravelPolar\Events\SubscriptionCreated;
use Danestves\LaravelPolar\Events\SubscriptionCycled;
use Danestves\LaravelPolar\Events\SubscriptionMigrated;
use Danestves\LaravelPolar\Events\SubscriptionPaused;
use Danestves\LaravelPolar\Events\SubscriptionPastDue;
use Danestves\LaravelPolar\Events\SubscriptionResumed;
use Danestves\LaravelPolar\Events\SubscriptionRevoked;
use Danestves\LaravelPolar\Events\SubscriptionUncanceled;
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
            'order.paid' => $this->handleOrderPaid($data, $timestamp, $type),
            'order.refunded' => $this->handleOrderRefunded($data, $timestamp, $type),
            'subscription.created' => $this->handleSubscriptionCreated($data, $timestamp, $type),
            'subscription.updated' => $this->handleSubscriptionUpdated($data, $timestamp, $type),
            'subscription.active' => $this->handleSubscriptionActive($data, $timestamp, $type),
            'subscription.canceled' => $this->handleSubscriptionCanceled($data, $timestamp, $type),
            'subscription.revoked' => $this->handleSubscriptionRevoked($data, $timestamp, $type),
            'subscription.uncanceled' => $this->handleSubscriptionUncanceled($data, $timestamp, $type),
            'subscription.cycled' => $this->handleSubscriptionCycled($data, $timestamp, $type),
            'subscription.past_due' => $this->handleSubscriptionPastDue($data, $timestamp, $type),
            'subscription.paused' => $this->handleSubscriptionPaused($data, $timestamp, $type),
            'subscription.resumed' => $this->handleSubscriptionResumed($data, $timestamp, $type),
            'subscription.migrated' => $this->handleSubscriptionMigrated($data, $timestamp, $type, $payload),
            'benefit_grant.created' => $this->handleBenefitGrantCreated($data, $timestamp, $type),
            'benefit_grant.updated' => $this->handleBenefitGrantUpdated($data, $timestamp, $type),
            'benefit_grant.cycled' => $this->handleBenefitGrantCycled($data, $timestamp, $type),
            'benefit_grant.revoked' => $this->handleBenefitGrantRevoked($data, $timestamp, $type),
            'member.created' => $this->handleMemberEvent($data, $timestamp, $type, Data\WebhookMemberCreatedPayload::class, MemberCreated::class),
            'member.updated' => $this->handleMemberEvent($data, $timestamp, $type, Data\WebhookMemberUpdatedPayload::class, MemberUpdated::class),
            'member.deleted' => $this->handleMemberEvent($data, $timestamp, $type, Data\WebhookMemberDeletedPayload::class, MemberDeleted::class),
            'customer_seat.assigned' => $this->handleCustomerSeatEvent($data, $timestamp, $type, Data\WebhookCustomerSeatAssignedPayload::class, CustomerSeatAssigned::class),
            'customer_seat.claimed' => $this->handleCustomerSeatEvent($data, $timestamp, $type, Data\WebhookCustomerSeatClaimedPayload::class, CustomerSeatClaimed::class),
            'customer_seat.revoked' => $this->handleCustomerSeatEvent($data, $timestamp, $type, Data\WebhookCustomerSeatRevokedPayload::class, CustomerSeatRevoked::class),
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
            'refund.created' => $this->handleRefundCreated($data, $timestamp, $type),
            'refund.updated' => $this->handleRefundUpdated($data, $timestamp, $type),
            'discount.created' => $this->handleDiscountCreated($data, $timestamp, $type),
            'discount.updated' => $this->handleDiscountUpdated($data, $timestamp, $type),
            'discount.deleted' => $this->handleDiscountDeleted($data, $timestamp, $type),
            'organization.updated' => $this->handleOrganizationUpdated($data, $timestamp, $type),
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

        $order = $this->findOrder($data['id']);

        if (! $order instanceof EloquentOrder) {
            $order = $billable->orders()->updateOrCreate(['polar_id' => $data['id']], [ // @phpstan-ignore-line class.notFound - the property is found in the billable model
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
        }

        // A re-delivered event must not create a duplicate order, and if the order already
        // exists its state still has to reflect this delivery.
        $order->sync($data, $timestamp);

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

        $order->sync([...$data, 'status' => $status], $timestamp);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookOrderUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => OrderUpdated::dispatch($billable, $order, $payload, $isRefunded), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the order paid event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleOrderPaid(array $data, \DateTime $timestamp, string $type): void
    {
        $order = $this->findOrder($data['id']);

        if (! $order instanceof EloquentOrder) {
            // Some sequences deliver `order.paid` without a preceding `order.created`, so the
            // order is created here from the same shape used by handleOrderCreated().
            $billable = $this->resolveBillable($data);

            $order = $billable->orders()->updateOrCreate(['polar_id' => $data['id']], [ // @phpstan-ignore-line class.notFound - the property is found in the billable model
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
        }

        $order->sync($data, $timestamp);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookOrderPaidPayload::class, $data, $timestamp, $type),
            fn($payload) => OrderPaid::dispatch($order->billable, $order, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle the order refunded event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleOrderRefunded(array $data, \DateTime $timestamp, string $type): void
    {
        if (!($order = $this->findOrder($data['id'])) instanceof EloquentOrder) {
            Log::warning('Order not found for webhook refunded event', [
                'order_id' => $data['id'],
                'event_type' => $type,
            ]);
            return;
        }

        $order->sync($data, $timestamp);

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookOrderRefundedPayload::class, $data, $timestamp, $type),
            fn($payload) => OrderRefunded::dispatch($order->billable, $order, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
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

        $subscription = $this->findSubscription($data['id']);

        if ($subscription instanceof EloquentSubscription) {
            // A re-delivered event must not create a duplicate subscription.
            $subscription->sync($data, $timestamp);
        } else {
            $subscription = $billable->subscriptions()->create([ // @phpstan-ignore-line class.notFound - the property is found in the billable model
                'type' => $customerMetadata['subscription_type'] ?? 'default',
                'polar_id' => $data['id'],
                'status' => \is_string($data['status']) ? SubscriptionStatus::from($data['status']) : $data['status'],
                'product_id' => $data['product_id'],
                'current_period_end' => $data['current_period_end'] ? Carbon::make($data['current_period_end']) : null,
                'trial_ends_at' => isset($data['trial_end']) ? Carbon::make($data['trial_end']) : null,
                'ends_at' => $data['ends_at'] ? Carbon::make($data['ends_at']) : null,
            ]);

            // The create array only carries the fields needed to identify the subscription; the
            // full lifecycle state comes from the payload.
            $subscription->sync($data, $timestamp);
        }

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
        $subscription = $this->prepareSubscription($data, $timestamp);

        if (! $subscription instanceof EloquentSubscription) {
            return;
        }

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
        $subscription = $this->prepareSubscription($data, $timestamp);

        if (! $subscription instanceof EloquentSubscription) {
            return;
        }

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
        $subscription = $this->prepareSubscription($data, $timestamp);

        if (! $subscription instanceof EloquentSubscription) {
            return;
        }

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
        $subscription = $this->prepareSubscription($data, $timestamp);

        if (! $subscription instanceof EloquentSubscription) {
            return;
        }

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookSubscriptionRevokedPayload::class, $data, $timestamp, $type),
            fn($payload) => SubscriptionRevoked::dispatch($subscription->billable, $subscription, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle a subscription lifecycle event that only needs the local record synced.
     *
     * @param  array<string, mixed>  $data
     * @param  class-string<\Danestves\LaravelPolar\Data\WebhookSubscriptionUncanceledPayload>|class-string<\Danestves\LaravelPolar\Data\WebhookSubscriptionCycledPayload>|class-string<\Danestves\LaravelPolar\Data\WebhookSubscriptionPastDuePayload>|class-string<\Danestves\LaravelPolar\Data\WebhookSubscriptionPausedPayload>|class-string<\Danestves\LaravelPolar\Data\WebhookSubscriptionResumedPayload>|class-string<\Danestves\LaravelPolar\Data\WebhookSubscriptionMigratedPayload>  $payloadClass
     * @param  class-string  $eventClass
     */
    private function handleSubscriptionLifecycle(array $data, \DateTime $timestamp, string $type, string $payloadClass, string $eventClass): void
    {
        $subscription = $this->prepareSubscription($data, $timestamp);

        if (! $subscription instanceof EloquentSubscription) {
            return;
        }

        $this->dispatchEvent(
            $type,
            fn() => $this->payload($payloadClass, $data, $timestamp, $type),
            fn($payload) => $eventClass::dispatch($subscription->billable, $subscription, $payload),
        );
    }

    /**
     * Handle the subscription uncanceled event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionUncanceled(array $data, \DateTime $timestamp, string $type): void
    {
        $this->handleSubscriptionLifecycle($data, $timestamp, $type, Data\WebhookSubscriptionUncanceledPayload::class, SubscriptionUncanceled::class);
    }

    /**
     * Handle the subscription cycled event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionCycled(array $data, \DateTime $timestamp, string $type): void
    {
        $this->handleSubscriptionLifecycle($data, $timestamp, $type, Data\WebhookSubscriptionCycledPayload::class, SubscriptionCycled::class);
    }

    /**
     * Handle the subscription past due event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionPastDue(array $data, \DateTime $timestamp, string $type): void
    {
        $this->handleSubscriptionLifecycle($data, $timestamp, $type, Data\WebhookSubscriptionPastDuePayload::class, SubscriptionPastDue::class);
    }

    /**
     * Handle the subscription paused event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionPaused(array $data, \DateTime $timestamp, string $type): void
    {
        $this->handleSubscriptionLifecycle($data, $timestamp, $type, Data\WebhookSubscriptionPausedPayload::class, SubscriptionPaused::class);
    }

    /**
     * Handle the subscription resumed event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionResumed(array $data, \DateTime $timestamp, string $type): void
    {
        $this->handleSubscriptionLifecycle($data, $timestamp, $type, Data\WebhookSubscriptionResumedPayload::class, SubscriptionResumed::class);
    }

    /**
     * Handle the subscription migrated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleSubscriptionMigrated(array $data, \DateTime $timestamp, string $type, array $envelope = []): void
    {
        $subscription = $this->prepareSubscription($data, $timestamp);

        if (! $subscription instanceof EloquentSubscription) {
            return;
        }

        $this->dispatchEvent(
            $type,
            fn() => $this->payload(
                Data\WebhookSubscriptionMigratedPayload::class,
                $data,
                $timestamp,
                $type,
                [
                    'provider' => (string) ($envelope['provider'] ?? ''),
                    'provider_subscription_id' => (string) ($envelope['provider_subscription_id'] ?? ''),
                ],
            ),
            fn($payload) => SubscriptionMigrated::dispatch($subscription->billable, $subscription, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
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
     * Handle the benefit grant cycled event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleBenefitGrantCycled(array $data, \DateTime $timestamp, string $type): void
    {
        $billable = $this->resolveBillable($data);

        $this->dispatchEvent(
            $type,
            fn() => $this->benefitGrantPayload(Data\WebhookBenefitGrantCycledPayload::class, $data, $timestamp, $type),
            fn($payload) => BenefitGrantCycled::dispatch($billable, $payload), // @phpstan-ignore-line argument.type - Billable is a instance of a model
        );
    }

    /**
     * Handle a member event, which carries no billable metadata of its own.
     *
     * @param  array<string, mixed>  $data
     * @param  class-string  $payloadClass
     * @param  class-string  $eventClass
     */
    private function handleMemberEvent(array $data, \DateTime $timestamp, string $type, string $payloadClass, string $eventClass): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload($payloadClass, $data, $timestamp, $type),
            fn($payload) => $eventClass::dispatch($payload),
        );
    }

    /**
     * Handle a customer seat event.
     *
     * @param  array<string, mixed>  $data
     * @param  class-string  $payloadClass
     * @param  class-string  $eventClass
     */
    private function handleCustomerSeatEvent(array $data, \DateTime $timestamp, string $type, string $payloadClass, string $eventClass): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload($payloadClass, $data, $timestamp, $type),
            fn($payload) => $eventClass::dispatch($payload),
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

        $externalId = $payload['customer']['external_id'] ?? null;

        return $this->findOrCreateCustomer(
            $customerMetadata['billable_id'],
            (string) $customerMetadata['billable_type'],
            (string) $payload['customer_id'],
            is_string($externalId) ? $externalId : null,
        );
    }

    /**
     * Find or create a customer.
     *
     * @return \Danestves\LaravelPolar\Billable
     */
    private function findOrCreateCustomer(int|string $billableId, string $billableType, string $customerId, ?string $externalId = null) // @phpstan-ignore-line return.trait - Billable is used in the user final code
    {
        $customer = LaravelPolar::$customerModel::firstOrCreate([
            'billable_id' => $billableId,
            'billable_type' => $billableType,
        ], [
            'polar_id' => $customerId,
        ]);

        $updates = [];

        if ($customer->polar_id === null) {
            $updates['polar_id'] = $customerId;
        }

        if ($externalId !== null && $externalId !== '') {
            $updates['external_id'] = $externalId;
        }

        if ($updates !== []) {
            $customer->update($updates);
        }

        return $customer->billable;
    }

    private function findSubscription(string $subscriptionId): ?EloquentSubscription
    {
        return LaravelPolar::$subscriptionModel::firstWhere('polar_id', $subscriptionId);
    }

    /**
     * Resolve the local subscription for a webhook, creating it when Polar delivered an event
     * without a preceding `subscription.created`.
     *
     * This matters most for `subscription.migrated`, which Polar emits for a subscription it
     * took over from another provider without ever sending `subscription.created`. A paused,
     * cycled, or resumed event delivered before its created event is handled the same way.
     *
     * @param  array<string, mixed>  $data
     */
    private function prepareSubscription(array $data, \DateTime $timestamp): ?EloquentSubscription
    {
        $subscription = $this->findSubscription($data['id']);

        if ($subscription instanceof EloquentSubscription) {
            $subscription->sync($data, $timestamp);

            return $subscription;
        }

        try {
            $billable = $this->resolveBillable($data);
        } catch (InvalidMetadataPayload) {
            Log::warning('Subscription event could not resolve a local billable', [
                'subscription_id' => $data['id'],
            ]);

            return null;
        }

        $metadata = $data['customer']['metadata'] ?? [];

        $subscription = $billable->subscriptions()->create([ // @phpstan-ignore-line class.notFound - the property is found in the billable model
            'type' => $metadata['subscription_type'] ?? 'default',
            'polar_id' => $data['id'],
            'status' => \is_string($data['status']) ? SubscriptionStatus::from($data['status']) : $data['status'],
            'product_id' => $data['product_id'],
        ]);

        $subscription->sync($data, $timestamp);

        if ($billable->customer !== null && $billable->customer->polar_id === null) { // @phpstan-ignore-line property.notFound - the property is found in the billable model
            $billable->customer->update(['polar_id' => $data['customer_id']]); // @phpstan-ignore-line property.notFound - the property is found in the billable model
        }

        return $subscription;
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

        $utc = new \DateTimeZone('UTC');

        $parsed = \DateTime::createFromFormat(\DateTime::ATOM, $timestampValue, $utc);
        if ($parsed !== false) {
            return $parsed;
        }

        // Polar's timestamp is always UTC; pass the zone explicitly so a non-UTC app
        // timezone does not shift the ordering watermark.
        $parsed = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $timestampValue, $utc);
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
     * @param  array<string, mixed>  $envelope  extra envelope-level fields a specific event carries
     * @return TPayload
     */
    private function payload(string $payloadClass, array $data, \DateTimeInterface $timestamp, string $type, array $envelope = []): mixed
    {
        return $payloadClass::from([
            'type' => $type,
            'timestamp' => $timestamp->format(\DateTimeInterface::ATOM),
            'data' => $data,
            ...$envelope,
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

    /**
     * Handle the refund created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleRefundCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookRefundCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => RefundCreated::dispatch($payload),
        );
    }

    /**
     * Handle the refund updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleRefundUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookRefundUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => RefundUpdated::dispatch($payload),
        );
    }

    /**
     * Handle the discount created event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleDiscountCreated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookDiscountCreatedPayload::class, $data, $timestamp, $type),
            fn($payload) => DiscountCreated::dispatch($payload),
        );
    }

    /**
     * Handle the discount updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleDiscountUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookDiscountUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => DiscountUpdated::dispatch($payload),
        );
    }

    /**
     * Handle the discount deleted event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleDiscountDeleted(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookDiscountDeletedPayload::class, $data, $timestamp, $type),
            fn($payload) => DiscountDeleted::dispatch($payload),
        );
    }

    /**
     * Handle the organization updated event.
     *
     * @param  array<string, mixed>  $data
     */
    private function handleOrganizationUpdated(array $data, \DateTime $timestamp, string $type): void
    {
        $this->dispatchEvent(
            $type,
            fn() => $this->payload(Data\WebhookOrganizationUpdatedPayload::class, $data, $timestamp, $type),
            fn($payload) => OrganizationUpdated::dispatch($payload),
        );
    }
}
