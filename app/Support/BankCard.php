<?php

namespace App\Support;

/**
 * Iranian debit card numbers (Shetab): normalizing, the Luhn check and the issuing bank.
 */
final class BankCard
{
    /**
     * Issuer prefixes (first six digits) of Iranian banks.
     *
     * @var array<string, string>
     */
    private const BANKS = [
        '603799' => 'بانک ملی ایران',
        '589210' => 'بانک سپه',
        '627381' => 'بانک سپه',
        '627648' => 'بانک توسعه صادرات',
        '207177' => 'بانک توسعه صادرات',
        '627961' => 'بانک صنعت و معدن',
        '603770' => 'بانک کشاورزی',
        '639217' => 'بانک کشاورزی',
        '628023' => 'بانک مسکن',
        '627760' => 'پست بانک ایران',
        '502908' => 'بانک توسعه تعاون',
        '627412' => 'بانک اقتصاد نوین',
        '622106' => 'بانک پارسیان',
        '639194' => 'بانک پارسیان',
        '627884' => 'بانک پارسیان',
        '502229' => 'بانک پاسارگاد',
        '639347' => 'بانک پاسارگاد',
        '627488' => 'بانک کارآفرین',
        '502910' => 'بانک کارآفرین',
        '621986' => 'بانک سامان',
        '639346' => 'بانک سینا',
        '639607' => 'بانک سرمایه',
        '636214' => 'بانک آینده',
        '502806' => 'بانک شهر',
        '504706' => 'بانک شهر',
        '502938' => 'بانک دی',
        '603769' => 'بانک صادرات ایران',
        '610433' => 'بانک ملت',
        '991975' => 'بانک ملت',
        '585983' => 'بانک تجارت',
        '627353' => 'بانک تجارت',
        '589463' => 'بانک رفاه کارگران',
        '505785' => 'بانک ایران زمین',
        '636795' => 'بانک مرکزی',
        '606373' => 'بانک قرض‌الحسنه مهر ایران',
        '505416' => 'بانک گردشگری',
        '505426' => 'بانک گردشگری',
        '504172' => 'بانک قرض‌الحسنه رسالت',
        '507677' => 'موسسه اعتباری نور',
        '606256' => 'موسسه اعتباری ملل',
        '628157' => 'موسسه اعتباری توسعه',
        '505801' => 'موسسه اعتباری کوثر',
        '639599' => 'بانک قوامین',
        '639370' => 'بانک مهر اقتصاد',
        '585947' => 'بانک خاورمیانه',
    ];

    /**
     * "۶۰۳۷-۹۹۷۵ 1234 ..." -> "6037997512345678" (digits only).
     */
    public static function normalize(?string $number): string
    {
        return preg_replace('/\D+/', '', PersianText::toLatinDigits((string) $number)) ?? '';
    }

    /**
     * 16 digits that pass the Luhn checksum.
     */
    public static function isValid(string $number): bool
    {
        if (preg_match('/^\d{16}$/', $number) !== 1 || preg_match('/^(\d)\1{15}$/', $number) === 1) {
            return false;
        }

        $sum = 0;

        foreach (str_split($number) as $index => $digit) {
            $value = (int) $digit * ($index % 2 === 0 ? 2 : 1);
            $sum += $value > 9 ? $value - 9 : $value;
        }

        return $sum % 10 === 0;
    }

    public static function bankName(string $number): ?string
    {
        return self::BANKS[substr($number, 0, 6)] ?? null;
    }

    /**
     * "6037997512345678" -> "6037-99**-****-5678" for lists and receipts.
     */
    public static function mask(string $number): string
    {
        if (strlen($number) !== 16) {
            return $number;
        }

        return substr($number, 0, 4).'-'.substr($number, 4, 2).'**-****-'.substr($number, 12, 4);
    }
}
