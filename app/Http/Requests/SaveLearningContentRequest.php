<?php

namespace App\Http\Requests;

use App\Enums\ContentAudience;
use App\Enums\ContentPurpose;
use App\Enums\LearningContentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Articles, videos, checklists and roadmaps written by mentors or staff.
 */
class SaveLearningContentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'content_type' => ['required', Rule::enum(LearningContentType::class)],
            'audience' => ['required', Rule::enum(ContentAudience::class)],
            'purpose' => ['required', Rule::enum(ContentPurpose::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'body' => ['nullable', 'required_without:media_url', 'string', 'max:20000'],
            'media_url' => ['nullable', 'url', 'max:255'],
            'is_published' => ['boolean'],
        ];
    }

    /**
     * Validated data with the publication date set the first time it goes live.
     *
     * @return array<string, mixed>
     */
    public function contentAttributes(?string $publishedAt = null): array
    {
        $published = $this->boolean('is_published');

        return [
            ...$this->safe()->except('is_published'),
            'is_published' => $published,
            'published_at' => $published ? ($publishedAt ?? now()) : null,
        ];
    }
}
