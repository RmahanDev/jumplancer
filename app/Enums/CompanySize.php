<?php

namespace App\Enums;

/**
 * Employer company size bucket.
 */
enum CompanySize: string
{
    case Solo = 'solo';
    case TwoToTen = '2-10';
    case ElevenToFifty = '11-50';
    case FiftyOneToTwoHundred = '51-200';
    case MoreThanTwoHundred = '200+';
}
