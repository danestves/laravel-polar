<?php

namespace Danestves\LaravelPolar\Commands;

use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Console\Command;
use Danestves\LaravelPolar\Data;
use Danestves\LaravelPolar\Enums\ProductSortProperty;
use Danestves\LaravelPolar\Http\Page;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;

#[AsCommand(name: 'polar:products')]
class ListProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'polar:products
                            {--id=* : Filter by a single product id or multiple product ids.}
                            {--organization-id=* : Filter by a single organization id or multiple organization ids.}
                            {--query= : Filter by product name.}
                            {--archived : Filter on archived products.}
                            {--recurring : Filter on recurring products.}
                            {--benefit-id=* : Filter by a single benefit id or multiple benefit ids.}
                            {--page= : Page number, defaults to 1.}
                            {--limit= : Size of a page, defaults to 10. Maximum is 100.}
                            {--sorting=* : Sorting criterion. Several criteria can be used simultaneously and will be applied in order. Add a minus sign - before the criteria name to sort by descending order. Available options: created_at, -created_at, name, -name, price_amount_type, -price_amount_type, price_amount, -price_amount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists all products';

    public function handle(): int
    {
        if (! $this->validate()) {
            return static::FAILURE;
        }

        $options = $this->options();

        return $this->handleProducts([
            'id' => $this->normalizeArrayOption($options['id'] ?? []),
            'organization_id' => $this->normalizeArrayOption($options['organization-id'] ?? []),
            'query' => $options['query'] ?? null,
            'is_archived' => ($options['archived'] ?? null) ? true : null,
            'is_recurring' => ($options['recurring'] ?? null) ? true : null,
            'benefit_id' => $this->normalizeArrayOption($options['benefit-id'] ?? []),
            'sorting' => empty($options['sorting']) ? null : $this->mapSorting($options['sorting']),
            'page' => isset($options['page']) && is_numeric($options['page']) ? (int) $options['page'] : null,
            'limit' => isset($options['limit']) && is_numeric($options['limit']) ? (int) $options['limit'] : null,
        ]);
    }

    protected function validate(): bool
    {
        $validator = Validator::make([
            ...config('polar'),
        ], [
            'access_token' => 'required',
        ], [
            'access_token.required' => 'Polar access token not set. You can add it to your .env file as POLAR_ACCESS_TOKEN.',
        ]);

        if ($validator->passes()) {
            return true;
        }

        $this->newLine();

        foreach ($validator->errors()->all() as $error) {
            error($error);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function handleProducts(array $query): int
    {
        /** @var Page<Data\Product> $products */
        $products = spin(
            fn() => LaravelPolar::listProducts($query),
            '⚪ Fetching products information...',
        );

        if (count($products) === 0) {
            $this->error('No products found.');

            return static::FAILURE;
        }

        $this->newLine();
        $this->displayTitle();
        $this->newLine();

        foreach ($products as $product) {
            $this->displayProduct($product);

            $this->newLine();
        }

        return static::SUCCESS;
    }

    protected function displayTitle(): void
    {
        $this->components->twoColumnDetail('<fg=gray>Product</>', '<fg=gray>ID</>');
    }

    protected function displayProduct(Data\Product $product): void
    {
        $this->components->twoColumnDetail(
            sprintf('<fg=green;options=bold>%s</>', $product->name),
            $product->id,
        );
    }

    /**
     * Normalize array option to single value or array.
     *
     * @param  array<string>  $values
     * @return string|array<string>|null
     */
    protected function normalizeArrayOption(array $values): string|array|null
    {
        if (empty($values)) {
            return null;
        }

        return count($values) === 1 ? $values[0] : $values;
    }

    /**
     * Map sorting strings to ProductSortProperty enum values.
     *
     * @param  array<string>  $sorting
     * @return list<ProductSortProperty>
     */
    protected function mapSorting(array $sorting): array
    {
        $mapped = [];

        foreach ($sorting as $sort) {
            $property = ProductSortProperty::tryFrom($sort);

            if ($property !== null) {
                $mapped[] = $property;
            } else {
                $this->components->warn("Unknown sorting criterion ignored: {$sort}");
            }
        }

        return $mapped;
    }

    /**
    * Get the console command options.
    *
    * @return array
    */
    protected function getOptions()
    {
        return [
            ['id', null, InputOption::VALUE_IS_ARRAY, 'Filter by a single product id or multiple product ids.'],
            ['organization-id', null, InputOption::VALUE_IS_ARRAY, 'Filter by a single organization id or multiple organization ids.'],
            ['query', null, InputOption::VALUE_REQUIRED, 'Filter by product name.'],
            ['archived', null, InputOption::VALUE_NONE, 'Filter on archived products.'],
            ['recurring', null, InputOption::VALUE_NONE, 'Filter on recurring products.'],
            ['benefit-id', null, InputOption::VALUE_IS_ARRAY, 'Filter by a single benefit id or multiple benefit ids.'],
            ['page', null, InputOption::VALUE_NONE, 'Page number, defaults to 1.'],
            ['limit', null, InputOption::VALUE_NONE, 'Size of a page, defaults to 10. Maximum is 100.'],
            ['sorting', null, InputOption::VALUE_IS_ARRAY, 'Sorting criterion. Several criteria can be used simultaneously and will be applied in order. Add a minus sign - before the criteria name to sort by descending order. Available options: created_at, -created_at, name, -name, price_amount_type, -price_amount_type, price_amount, -price_amount'],
        ];
    }
}
