<?php

declare(strict_types=1);

namespace Danestves\LaravelPolar\Support;

use Danestves\LaravelPolar\Data\Filter;
use Danestves\LaravelPolar\Data\FilterClause;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

/**
 * Hydrates the recursive `FilterClause|Filter` union behind `Filter::$clauses`.
 *
 * Polar's meter filters nest: a clause is either a leaf comparison or another
 * filter holding its own clauses. The two halves share no discriminator field,
 * and laravel-data resolves a union-typed property to its first data class and
 * never tries the others, so every clause would be handed to `FilterClause` and
 * a nested group would fail with "the constructor requires 3 parameters".
 *
 * They are trivially distinguishable by shape instead: only a nested filter
 * carries `conjunction`. Recursion comes for free, since `Filter::from()`
 * applies this cast again to the nested clauses.
 */
final class FilterClausesCast implements Cast
{
    /**
     * @param  array<string, mixed>  $properties
     * @return list<Filter|FilterClause>
     */
    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): array
    {
        if (! is_iterable($value)) {
            return [];
        }

        $clauses = [];

        foreach ($value as $clause) {
            $clauses[] = match (true) {
                $clause instanceof Filter, $clause instanceof FilterClause => $clause,
                is_array($clause) && array_key_exists('conjunction', $clause) => Filter::from($clause),
                default => FilterClause::from($clause),
            };
        }

        return $clauses;
    }
}
