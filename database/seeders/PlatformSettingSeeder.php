<?php

namespace Database\Seeders;

use App\Enums\SettingValueType;
use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class PlatformSettingSeeder extends Seeder
{
    /**
     * Default business rules. Existing rows are left untouched so values changed
     * from the admin dashboard are never overwritten by a re-seed.
     */
    public function run(): void
    {
        $settings = [
            ['employer_free_projects', '2', SettingValueType::Int, 'Free posts per employer: the 1st always, the 2nd only inside the window below'],
            ['second_free_project_window_days', '30', SettingValueType::Int, 'Days after the 1st project during which the 2nd project is free'],
            ['beginner_free_mentorships', '2', SettingValueType::Int, 'Free mentorships for freelancers at beginner level'],
            ['exam_required_from_level', 'intermediate', SettingValueType::Text, 'Registering a field at this level or higher requires passing the field exam'],
            ['primary_field_exam_fee', '0', SettingValueType::Money, 'Exam for the first field of a freelancer is free'],
            ['extra_field_exam_fee', null, SettingValueType::Money, 'Fee (Toman) for the exam of every additional field - SET FROM ADMIN DASHBOARD'],
            ['contact_violation_action', 'suspend', SettingValueType::Text, 'What happens when phone/email/link is shared in chat'],
            ['platform_fee_percent', '20', SettingValueType::Percent, 'Platform fee (percent) taken from every payment to the freelancer'],
            ['mentorship_fee_percent', '5', SettingValueType::Percent, 'Extra fee (percent) when the freelancer asked for a mentor on the project'],
            ['mentor_share_percent', '3.5', SettingValueType::Percent, 'Part of each payment (percent) paid to the mentor of the contract; the rest of the fees stays with the platform'],
            ['hire_deposit_percent', '45', SettingValueType::Int, 'Share of the proposal price the employer puts in escrow as a good-faith deposit when hiring'],
            ['withdrawal_min_amount', '50000', SettingValueType::Money, 'Smallest amount (Toman) a user can ask to be paid out to their bank card'],
        ];

        foreach ($settings as [$key, $value, $type, $description]) {
            PlatformSetting::firstOrCreate(
                ['setting_key' => $key],
                ['setting_value' => $value, 'value_type' => $type, 'description' => $description],
            );
        }
    }
}
