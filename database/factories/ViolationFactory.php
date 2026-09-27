<?php

namespace Database\Factories;

use App\Enums\ViolationAction;
use App\Enums\ViolationSource;
use App\Enums\ViolationType;
use App\Models\Message;
use App\Models\User;
use App\Models\Violation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Violation>
 */
class ViolationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'violatable_type' => (new Message)->getMorphClass(),
            'violatable_id' => Message::factory(),
            'violation_type' => ViolationType::Phone,
            'detected_content' => '0912*******',
            'detected_by' => ViolationSource::AutoFilter,
            'action_taken' => ViolationAction::Suspended,
        ];
    }
}
