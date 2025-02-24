<?php

namespace Danestves\LaravelPolar;

use Danestves\LaravelPolar\Commands\ListProductsCommand;
use Danestves\LaravelPolar\View\Components\Button;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPolarServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-polar')
            ->hasConfigFile("polar")
            ->hasViews()
            ->hasViewComponent('polar', Button::class)
            ->discoversMigrations()
            ->hasRoute("web")
            ->hasCommands(
                ListProductsCommand::class,
            );
    }

    public function boot(): void
    {
        parent::boot();

        $this->bootDirectives();
    }

    protected function bootDirectives(): void
    {
        Blade::directive('polar', function () {
            return "<?php echo view('polar::js'); ?>";
        });
    }
}
