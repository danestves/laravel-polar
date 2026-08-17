<?php

namespace Danestves\LaravelPolar\Http;

use Danestves\LaravelPolar\Data\Pagination;
use Illuminate\Support\Collection;
use IteratorAggregate;
use Traversable;

/**
 * One page of a Polar list endpoint.
 *
 * Polar's list responses are `{items, pagination}`; this keeps that shape while being directly
 * iterable, so `foreach ($page as $product)` reads naturally and `$page->pagination->maxPage`
 * is there when you need to walk further.
 *
 * @template TItem
 *
 * @implements IteratorAggregate<int, TItem>
 */
final class Page implements \Countable, IteratorAggregate
{
    /**
     * @param  list<TItem>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly Pagination $pagination,
    ) {}

    /**
     * @return Collection<int, TItem>
     */
    public function collect(): Collection
    {
        return new Collection($this->items);
    }

    /**
     * @return TItem|null
     */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Whether another page exists after this one, given the page number just requested.
     */
    public function hasMorePages(int $currentPage = 1): bool
    {
        return $currentPage < $this->pagination->maxPage;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
