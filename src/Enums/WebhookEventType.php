<?php

// Hand-written because the published spec omits subscription.migrated from this enum.

declare(strict_types=1);

namespace Danestves\LaravelPolar\Enums;

enum WebhookEventType: string
{
    case CheckoutCreated = 'checkout.created';
    case CheckoutUpdated = 'checkout.updated';
    case CheckoutExpired = 'checkout.expired';
    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case CustomerDeleted = 'customer.deleted';
    case CustomerStateChanged = 'customer.state_changed';
    case CustomerSeatAssigned = 'customer_seat.assigned';
    case CustomerSeatClaimed = 'customer_seat.claimed';
    case CustomerSeatRevoked = 'customer_seat.revoked';
    case MemberCreated = 'member.created';
    case MemberUpdated = 'member.updated';
    case MemberDeleted = 'member.deleted';
    case OrderCreated = 'order.created';
    case OrderUpdated = 'order.updated';
    case OrderPaid = 'order.paid';
    case OrderRefunded = 'order.refunded';
    case SubscriptionCreated = 'subscription.created';
    case SubscriptionUpdated = 'subscription.updated';
    case SubscriptionActive = 'subscription.active';
    case SubscriptionCanceled = 'subscription.canceled';
    case SubscriptionUncanceled = 'subscription.uncanceled';
    case SubscriptionCycled = 'subscription.cycled';
    case SubscriptionRevoked = 'subscription.revoked';
    case SubscriptionPastDue = 'subscription.past_due';
    case SubscriptionPaused = 'subscription.paused';
    case SubscriptionResumed = 'subscription.resumed';
    case SubscriptionMigrated = 'subscription.migrated';
    case RefundCreated = 'refund.created';
    case RefundUpdated = 'refund.updated';
    case ProductCreated = 'product.created';
    case ProductUpdated = 'product.updated';
    case DiscountCreated = 'discount.created';
    case DiscountUpdated = 'discount.updated';
    case DiscountDeleted = 'discount.deleted';
    case BenefitCreated = 'benefit.created';
    case BenefitUpdated = 'benefit.updated';
    case BenefitGrantCreated = 'benefit_grant.created';
    case BenefitGrantCycled = 'benefit_grant.cycled';
    case BenefitGrantUpdated = 'benefit_grant.updated';
    case BenefitGrantRevoked = 'benefit_grant.revoked';
    case OrganizationUpdated = 'organization.updated';
}
