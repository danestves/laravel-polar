<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Database\Factories\OrderFactory;
use Danestves\LaravelPolar\Enums\OrderStatus;
use Danestves\LaravelPolar\Enums\RefundCreateReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $billable_type
 * @property int $billable_id
 * @property string|null $polar_id
 * @property OrderStatus $status
 * @property int $amount
 * @property int $tax_amount
 * @property int $refunded_amount
 * @property int $refunded_tax_amount
 * @property string $currency
 * @property string $billing_reason
 * @property string $customer_id
 * @property string $product_id
 * @property \Carbon\CarbonInterface|null $refunded_at
 * @property \Carbon\CarbonInterface $ordered_at
 * @property \Carbon\CarbonInterface|null $created_at
 * @property \Carbon\CarbonInterface|null $updated_at
 * @property \Danestves\LaravelPolar\Billable $billable
 *
 * @mixin \Eloquent
 */
class Order extends Model // @phpstan-ignore-line propertyTag.trait - Billable is used in the user final code
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'polar_orders';

    /**
    * The attributes that are not mass assignable.
    *
    * @var array<string>
    */
    protected $guarded = [];

    /**
     * Get the billable model related to the customer.
     *
     * @return MorphTo<Model, covariant $this>
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Check if the order is paid.
     */
    public function paid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    /**
     * Filter query by paid.
     *
     * @param  Builder<Order>  $query
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', OrderStatus::Paid);
    }

    /**
     * Cached custom-field data fetched from Polar.
     *
     * @var array<string, string|int|bool|\DateTime|null>|null
     */
    protected ?array $cachedCustomFieldData = null;

    /**
     * Fetch the custom-field data captured at checkout for this order.
     *
     * Data is not persisted in `polar_orders`; this method fetches the order
     * from Polar on demand and memoizes the result for the lifetime of this
     * Order instance.
     *
     * @return array<string, mixed>
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function customFieldData(): array
    {
        if ($this->cachedCustomFieldData !== null) {
            return $this->cachedCustomFieldData;
        }

        if ($this->polar_id === null) {
            return $this->cachedCustomFieldData = [];
        }

        return $this->cachedCustomFieldData = LaravelPolar::getOrder($this->polar_id)->customFieldData ?? [];
    }

    /**
     * Memoized invoice/receipt URL for this order.
     */
    protected ?string $cachedReceiptUrl = null;

    /**
     * Get the invoice/receipt URL for this order. Triggers invoice generation
     * on Polar (the result is asynchronous on their side), then returns the
     * URL of the generated PDF. Memoized per Order instance.
     *
     * Returns null when the order has no `polar_id` or no customer association, or while Polar
     * is still generating the document.
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function receiptUrl(): ?string
    {
        if ($this->cachedReceiptUrl !== null) {
            return $this->cachedReceiptUrl;
        }

        if ($this->polar_id === null || $this->customer_id === '') {
            return null;
        }

        $session = LaravelPolar::createCustomerSession(
            new Data\CustomerSessionCustomerIDCreate(customerId: $this->customer_id),
        );

        return $this->cachedReceiptUrl = LaravelPolar::getOrderInvoiceUrl($session->token, $this->polar_id);
    }

    /**
     * Redirect the user's browser to the invoice/receipt URL for this order.
     *
     * @throws \RuntimeException when no URL is available (e.g. unsynced order)
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function downloadInvoice(): \Illuminate\Http\RedirectResponse
    {
        $url = $this->receiptUrl();

        if ($url === null) {
            throw new \RuntimeException('No receipt URL available for this order.');
        }

        return new \Illuminate\Http\RedirectResponse($url);
    }

    /**
     * Issue a refund for this order. Defaults to refunding the remaining
     * unrefunded amount with reason "customer_request".
     *
     * @param  array<string, mixed>|null  $metadata
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function refund(
        ?int $amount = null,
        ?RefundCreateReason $reason = null,
        ?string $comment = null,
        ?array $metadata = null,
    ): Data\Refund {
        if ($this->polar_id === null) {
            throw new \RuntimeException('Order has no polar_id; cannot refund.');
        }

        return LaravelPolar::createRefund(new Data\RefundCreate(
            orderId: $this->polar_id,
            reason: $reason ?? RefundCreateReason::CustomerRequest,
            amount: $amount ?? max(0, $this->amount - $this->refunded_amount),
            metadata: $metadata,
            comment: $comment,
        ));
    }

    /**
     * List refunds for this order.
     *
     * @return \Illuminate\Support\Collection<int, Data\Refund>
     *
     * @throws \Danestves\LaravelPolar\Exceptions\PolarApiError
     */
    public function refunds(): \Illuminate\Support\Collection
    {
        if ($this->polar_id === null) {
            return collect();
        }

        return LaravelPolar::listRefunds(['order_id' => $this->polar_id])->collect();
    }

    /**
     * Check if the order is refunded.
     */
    public function refunded(): bool
    {
        return $this->status === OrderStatus::Refunded;
    }

    /**
     * Filter query by refunded.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeRefunded(Builder $query): void
    {
        $query->where('status', OrderStatus::Refunded);
    }

    /**
     * Check if the order is partially refunded.
     */
    public function partiallyRefunded(): bool
    {
        return $this->status === OrderStatus::PartiallyRefunded;
    }

    /**
     * Filter query by partially refunded.
     *
     * @param  Builder<Order>  $query
     */
    public function scopePartiallyRefunded(Builder $query): void
    {
        $query->where('status', OrderStatus::PartiallyRefunded);
    }

    /**
     * Check if the order is void.
     */
    public function void(): bool
    {
        return $this->status === OrderStatus::Void;
    }

    /**
     * Filter query by void.
     *
     * @param  Builder<Order>  $query
     */
    public function scopeVoid(Builder $query): void
    {
        $query->where('status', OrderStatus::Void);
    }

    /**
     * Determine if the order is for a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Sync the order with the given attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function sync(array $attributes): self
    {
        $this->update([
            'polar_id' => $attributes['id'],
            'status' => \is_string($attributes['status']) ? OrderStatus::from($attributes['status']) : $attributes['status'],
            'amount' => $attributes['amount'],
            'tax_amount' => $attributes['tax_amount'],
            'refunded_amount' => $attributes['refunded_amount'],
            'refunded_tax_amount' => $attributes['refunded_tax_amount'],
            'currency' => $attributes['currency'],
            'billing_reason' => $attributes['billing_reason'],
            'customer_id' => $attributes['customer_id'],
            'product_id' => $attributes['product_id'],
            'refunded_at' => $attributes['refunded_at'],
            'ordered_at' => $attributes['created_at'],
        ]);

        return $this;
    }

    /**
     * The attributes that should be cast to native types.
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'ordered_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
