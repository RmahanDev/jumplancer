<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Achievements that motivate beginners. Safe to run more than once (matched by code).
     */
    public function run(): void
    {
        $badges = [
            'first_proposal' => ['قدم اول', 'اولین پیشنهادت را فرستادی'],
            'first_contract' => ['اولین قرارداد', 'اولین قراردادت را گرفتی'],
            'first_five_star' => ['پنج ستاره', 'اولین نظر پنج‌ستاره را گرفتی'],
            'market_ready' => ['آماده‌ی بازار', 'امتیاز آمادگی‌ات به ۷۰ رسید'],
            'mentorship_graduate' => ['فارغ‌التحصیل منتورینگ', 'یک برنامه‌ی منتورینگ را کامل کردی'],
        ];

        foreach ($badges as $code => [$name, $description]) {
            Badge::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $description]);
        }
    }
}
