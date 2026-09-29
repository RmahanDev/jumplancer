<?php

namespace App\Http\Resources;

use App\Models\Assessment;
use App\Models\AssessmentOption;
use App\Models\AssessmentQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An exam for the exam builder (staff): everything, including which option is correct.
 *
 * @mixin Assessment
 */
class ExamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category_id' => $this->category_id,
            'skill_id' => $this->skill_id,
            'time_limit_minutes' => $this->time_limit_minutes,
            'total_score' => $this->total_score,
            'pass_score' => $this->pass_score,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'skill' => $this->whenLoaded('skill', fn () => ['id' => $this->skill->id, 'name' => $this->skill->name]),
            'author' => $this->whenLoaded('author', fn () => UserResource::summary($this->author)),
            'questions_count' => $this->whenCounted('questions'),
            'attempts_count' => $this->whenCounted('attempts'),
            'passed_count' => $this->when(isset($this->passed_count), fn (): int => (int) $this->passed_count),
            'in_progress_count' => $this->when(isset($this->in_progress_count), fn (): int => (int) $this->in_progress_count),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn (AssessmentQuestion $question): array => [
                'id' => $question->id,
                'body' => $question->body,
                'hint' => $question->hint,
                'options' => $question->options->map(fn (AssessmentOption $option): array => ['id' => $option->id, 'body' => $option->body])->values(),
                'correct' => $question->options->search(fn (AssessmentOption $option): bool => $option->is_correct),
            ])->values()),
        ];
    }
}
