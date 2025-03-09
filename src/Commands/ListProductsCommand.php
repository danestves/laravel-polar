<?php

namespace Danestves\LaravelPolar\Commands;

use Danestves\LaravelPolar\Data\Products\ListProductsRequestData;
use Danestves\LaravelPolar\Data\Products\ProductData;
use Danestves\LaravelPolar\Enums\ListProductsSorting;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;

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
                            {--query? : Filter by product name.}
                            {--archived=false : Filter on archived products..}
                            {--recurring=false : Filter on recurring products.}
                            {--benefit-id=* : Filter by a single benefit id or multiple benefit ids.}
                            {--page=1 : Page number, defaults to 1.}
                            {--limit=10 : Size of a page, defaults to 10. Maximum is 100.}
                            {--sorting? : Sorting criterion. Several criteria can be used simultaneously and will be applied in order. Add a minus sign - before the criteria name to sort by descending order. Available options: created_at, -created_at, name, -name, price_amount_type, -price_amount_type, price_amount, -price_amount}';

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

        $request = new ListProductsRequestData(
            id: $this->option('id'),
            organizationId: $this->option('organization-id'),
            query: $this->option('query'),
            isArchived: $this->option('archived'),
            isRecurring: $this->option('recurring'),
            benefitId: $this->option('benefit-id'),
            page: (int) $this->option('page'),
            limit: (int) $this->option('limit'),
            sorting: $this->option('sorting'),
        );

        return $this->handleProducts($request);
    }

    protected function validate(): bool
    {
        $validator = Validator::make([
            ...config('polar'),
            'id' => $this->option('id'),
            'organization_id' => $this->option('organization-id'),
            'query' => $this->option('query'),
            'archived' => $this->option('archived'),
            'recurring' => $this->option('recurring'),
            'benefit_id' => $this->option('benefit-id'),
            'page' => $this->option('page'),
            'limit' => $this->option('limit'),
            'sorting' => $this->option('sorting'),
        ], [
            'access_token' => 'required',
            'id' => ['sometimes', 'string', 'array'],
            'organization_id' => ['sometimes', 'string', 'array'],
            'query' => ['sometimes', 'string'],
            'archived' => ['sometimes', 'boolean'],
            'recurring' => ['sometimes', 'boolean'],
            'benefit_id' => ['sometimes', 'string', 'array'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sorting' => ['sometimes', 'array', Rule::enum(ListProductsSorting::class)],
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

    protected function handleProducts(ListProductsRequestData $request): int
    {
        $this->validate();

        $productsResponse = spin(
            fn() => LaravelPolar::listProducts($request),
            '⚪ Fetching products information...',
        );

        $products = collect($productsResponse->items);

        $this->newLine();
        $this->displayTitle();
        $this->newLine();

        $products->each(function ($product) {
            $this->displayProduct($product);

            $this->newLine();
        });

        return static::SUCCESS;
    }

    protected function displayTitle(): void
    {
        $this->components->twoColumnDetail('<fg=gray>Product</>', '<fg=gray>ID</>');
    }

    protected function displayProduct(ProductData $product): void
    {
        $this->components->twoColumnDetail(
            sprintf('<fg=green;options=bold>%s</>', $product->name),
            $product->id,
        );
    }
}
