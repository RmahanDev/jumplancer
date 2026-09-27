<?php

namespace App\Enums;

/**
 * How a project or a category budget range is priced.
 */
enum BudgetType: string
{
    case Fixed = 'fixed';
    case Hourly = 'hourly';
}
