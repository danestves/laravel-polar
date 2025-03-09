<?php

namespace Danestves\LaravelPolar\Commands;

use Danestves\LaravelPolar\Data\Products\ListProductsRequestData;
use Danestves\LaravelPolar\Data\Products\ProductData;
use Danestves\LaravelPolar\LaravelPolar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\error;
use function Laravel\Prompts\spin;

class ListProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'polar:products';

    /**
     * The console command description.
     */
    protected $description = 'Lists all products';

    public function handle(): int
    {
        if (! $this->validate()) {
            return static::FAILURE;
        }

        return $this->handleProducts();
    }

    protected function validate(): bool
    {
        $validator = Validator::make(config('polar'), [
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

    protected function handleProducts(): int
    {
        $this->validate();

        $productsResponse = spin(
            fn() => LaravelPolar::listProducts(
                ListProductsRequestData::from([]),
            ),
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
