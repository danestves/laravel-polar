<?php

namespace Danestves\LaravelPolar\Http;

use Danestves\LaravelPolar\Data\Pagination;
use Illuminate\Support\Collection;
use InvalidArgumentException;
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
     * Walk every page from `$startPage` through `pagination.maxPage` and merge the items.
     *
     * `$fetchPage` is called with the page number and must return a Page. Each page is fetched
     * on demand, so this trades memory for a full copy of the result set; use `lazy()` when the
     * collection would be large.
     *
     * @param  callable(int): mixed  $fetchPage
     * @return Collection<int, TItem>
     *
     * @throws InvalidArgumentException when `$fetchPage` returns something other than a Page
     */
    public function all(callable $fetchPage, int $startPage = 1): Collection
    {
        $items = [];

        foreach ($this->pages($fetchPage, $startPage) as $page) {
            foreach ($page->items as $item) {
                $items[] = $item;
            }
        }

        return new Collection($items);
    }

    /**
     * Yield every item across pages without holding the whole result set in memory.
     *
     * @param  callable(int): mixed  $fetchPage
     * @return \Generator<int, TItem>
     *
     * @throws InvalidArgumentException when `$fetchPage` returns something other than a Page
     */
    public function lazy(callable $fetchPage, int $startPage = 1): \Generator
    {
        foreach ($this->pages($fetchPage, $startPage) as $page) {
            foreach ($page->items as $item) {
                yield $item;
            }
        }
    }

    /**
     * Walk pages starting at `$startPage`, ending after `pagination.maxPage`.
     *
     * @param  callable(int): mixed  $fetchPage
     * @return \Generator<int, self>
     */
    private function pages(callable $fetchPage, int $startPage): \Generator
    {
        $currentPage = $startPage;

        while (true) {
            $page = $fetchPage($currentPage);

            if (! $page instanceof self) {
                throw new InvalidArgumentException(
                    'The page callback must return an instance of ' . self::class . '.',
                );
            }

            yield $page;

            if (! $page->hasMorePages($currentPage)) {
                return;
            }

            $currentPage++;
        }
    }

    /**
     * @return Traversable<int, TItem>
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }
}
