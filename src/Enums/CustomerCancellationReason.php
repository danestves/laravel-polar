<?php

namespace Danestves\LaravelPolar\Enums;

enum CustomerCancellationReason: string
{
    case TooExpensive = "too_expensive";
    case MissingFeatures = "missing_features";
    case SwitchedService = "switched_service";
    case Unused = "unused";
    case CustomerService = "customer_service";
    case LowQuality = "low_quality";
    case TooComplex = "too_complex";
    case Other = "other";
}
