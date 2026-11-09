<?php

use Danestves\LaravelPolar\Data\Pagination;
use Danestves\LaravelPolar\Http\Page;

/**
 * Build a page with just enough pagination metadata for traversal tests.
 *
 * @param  list<mixed>  $items
 */
function pageWith(array $items, int $totalCount, int $maxPage): Page
{
    return new Page($items, new Pagination($totalCount, $maxPage));
}

it('collects items from a single page', function () {
    $calls = [];

    $all = pageWith(['a'], 2, 1)->all(function (int $pageNumber) use (&$calls) {
        $calls[] = $pageNumber;

        return pageWith(['a', 'b'], 2, 1);
    });

    expect($all->all())->toBe(['a', 'b'])
        ->and($calls)->toBe([1]);
});

it('collects and merges items across every page', function () {
    $calls = [];

    $all = pageWith([], 5, 3)->all(function (int $pageNumber) use (&$calls) {
        $calls[] = $pageNumber;

        return pageWith([$pageNumber], 5, 3);
    });

    expect($all->all())->toBe([1, 2, 3])
        ->and($calls)->toBe([1, 2, 3]);
});

it('starts collecting from the requested page', function () {
    $calls = [];

    $all = pageWith([], 5, 3)->all(function (int $pageNumber) use (&$calls) {
        $calls[] = $pageNumber;

        return pageWith([$pageNumber], 5, 3);
    }, startPage: 2);

    expect($all->all())->toBe([2, 3])
        ->and($calls)->toBe([2, 3]);
});

it('rejects a fetch callback that does not return a page', function () {
    expect(fn() => pageWith([], 1, 1)->all(fn() => ['not a page']))
        ->toThrow(InvalidArgumentException::class);
});

it('yields items lazily without fetching later pages up front', function () {
    $calls = [];

    $items = pageWith([], 5, 3)->lazy(function (int $pageNumber) use (&$calls) {
        $calls[] = $pageNumber;

        return pageWith([$pageNumber, $pageNumber * 10], 5, 3);
    });

    // Building the generator must not fetch anything yet.
    expect($calls)->toBe([]);

    expect($items->current())->toBe(1)
        ->and($calls)->toBe([1]);

    $items->next();

    expect($items->current())->toBe(10)
        ->and($calls)->toBe([1]);
});

it('lazily yields every item across pages', function () {
    $items = pageWith([], 4, 2)->lazy(function (int $pageNumber) {
        return pageWith([$pageNumber], 4, 2);
    });

    expect(iterator_to_array($items, false))->toBe([1, 2]);
});
