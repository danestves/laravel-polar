<?php

namespace Danestves\LaravelPolar\Enums;

enum ListProductsSorting: string
{
    case CreatedAtAsc = 'created_at';
    case CreatedAtDesc = '-created_at';
    case NameAsc = 'name';
    case NameDesc = '-name';
    case PriceAmountTypeAsc = 'price_amount_type';
    case PriceAmountTypeDesc = '-price_amount_type';
    case PriceAmountAsc = 'price_amount';
    case PriceAmountDesc = '-price_amount';
}
