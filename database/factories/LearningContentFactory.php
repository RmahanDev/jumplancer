<?php

namespace Database\Factories;

use App\Enums\ContentAudience;
use App\Enums\ContentPurpose;
use App\Enums\LearningContentType;
use App\Models\LearningContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningContent>
 */
class LearningContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'title' => fake()->sentence(4),
            'content_type' => LearningContentType::Article,
            'audience' => ContentAudience::All,
            'purpose' => ContentPurpose::Technical,
            'body' => fake()->paragraphs(3, true),
            'is_published' => true,
            'published_at' => now(),
        ];
    }
}
