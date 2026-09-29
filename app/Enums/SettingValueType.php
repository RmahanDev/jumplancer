<?php

namespace App\Enums;

/**
 * How a platform setting value is cast.
 */
enum SettingValueType: string
{
    case Int = 'int';
    case Money = 'money';
    case Text = 'text';
    case Bool = 'bool';
    case Percent = 'percent';
}
