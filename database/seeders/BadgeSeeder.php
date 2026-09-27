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
            'first_proposal' => ['First Step', 'Sent your first proposal'],
            'first_contract' => ['First Deal', 'Won your first contract'],
            'first_five_star' => ['Five Stars', 'Received your first 5-star review'],
            'market_ready' => ['Market Ready', 'Reached a readiness score of 70+'],
            'mentorship_graduate' => ['Graduate', 'Completed a mentorship program'],
        ];

        foreach ($badges as $code => [$name, $description]) {
            Badge::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $description]);
        }
    }
}
