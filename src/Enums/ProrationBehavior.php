<?php

namespace Danestves\LaravelPolar\Enums;

enum ProrationBehavior: string
{
    case Invoice = "invoice";
    case Prorate = "prorate";
}
