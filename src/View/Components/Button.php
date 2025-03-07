<?php

namespace Danestves\LaravelPolar\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class Button extends Component
{
    public function __construct(
        public string $href,
        public string $theme = 'light',
    ) {}

    public function render(): View
    {
        return view('polar::components.button'); // @phpstan-ignore-line
    }
}
