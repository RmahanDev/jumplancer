<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalizes text typed on Persian keyboards: Persian/Arabic digits, Arabic "ي/ك" and mobile formats.
 */
class PersianText
{
    private const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    private const ARABIC_DIGITS = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    private const LATIN_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * "۰۹۱۲" or "٠٩١٢" becomes "0912".
     */
    public static function toLatinDigits(string $value): string
    {
        return str_replace(
            [...self::PERSIAN_DIGITS, ...self::ARABIC_DIGITS],
            [...self::LATIN_DIGITS, ...self::LATIN_DIGITS],
            $value,
        );
    }

    /**
     * Canonical text for storing and searching: Persian "ی/ک" instead of the Arabic letters,
     * Latin digits and single spaces. The zero-width non-joiner ("می‌خواهم") is part of Persian
     * spelling, so it is kept.
     */
    public static function normalize(?string $value): string
    {
        $value = str_replace(['ي', 'ك', 'ة'], ['ی', 'ک', 'ه'], (string) $value);

        return Str::squish(self::toLatinDigits($value));
    }

    /**
     * A number for a message: "۱٬۲۵۰٬۰۰۰" when the app speaks Persian, "1,250,000" otherwise.
     */
    public static function number(int|float $value, int $decimals = 0): string
    {
        $formatted = number_format($value, $decimals);

        if (app()->getLocale() !== 'fa') {
            return $formatted;
        }

        return strtr($formatted, [...array_combine(self::LATIN_DIGITS, self::PERSIAN_DIGITS), ',' => '٬', '.' => '٫']);
    }

    /**
     * A percentage without needless decimals: 20 -> "۲۰", 3.5 -> "۳٫۵".
     */
    public static function percent(int|float $value): string
    {
        $decimals = fmod((float) $value, 1.0) === 0.0 ? 0 : (fmod((float) $value * 10, 1.0) === 0.0 ? 1 : 2);

        return self::number($value, $decimals);
    }

    /**
     * Login identifier (username, email or mobile) in its canonical, lower-case form.
     */
    public static function normalizeIdentifier(?string $value): string
    {
        $value = mb_strtolower(trim(self::toLatinDigits((string) $value)));

        return self::normalizeMobile($value) ?? $value;
    }

    /**
     * An Iranian mobile number as "09xxxxxxxxx", or null when the value is not one.
     */
    public static function normalizeMobile(?string $value): ?string
    {
        $digits = preg_replace('/[\s\-()]/', '', self::toLatinDigits((string) $value));

        if (preg_match('/^(?:\+98|0098|98|0)?(9\d{9})$/', $digits, $matches) !== 1) {
            return null;
        }

        return '0'.$matches[1];
    }
}
