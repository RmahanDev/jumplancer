<?php

namespace App\Http\Requests;

use App\Enums\Availability;
use App\Enums\CompanySize;
use App\Enums\MentoringStyle;
use App\Enums\RoleName;
use App\Http\Requests\Concerns\NormalizesAccountFields;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    use NormalizesAccountFields;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAccountFields();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            ...$this->accountRules($user),
            'bio' => ['nullable', 'string', 'max:1000'],

            'freelancer' => [Rule::prohibitedIf(! $user->hasRole(RoleName::Freelancer)), 'nullable', 'array'],
            'freelancer.headline' => ['nullable', 'string', 'max:150'],
            'freelancer.hourly_rate' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'freelancer.availability' => ['required_with:freelancer', Rule::enum(Availability::class)],

            'employer' => [Rule::prohibitedIf(! $user->hasRole(RoleName::Employer)), 'nullable', 'array'],
            'employer.company_name' => ['nullable', 'string', 'max:150'],
            'employer.company_size' => ['nullable', Rule::enum(CompanySize::class)],
            'employer.industry' => ['nullable', 'string', 'max:100'],
            'employer.website' => ['nullable', 'url', 'max:255'],
            'employer.open_to_beginners' => ['boolean'],

            'mentor' => [Rule::prohibitedIf(! $user->hasRole(RoleName::Mentor)), 'nullable', 'array'],
            'mentor.expertise_summary' => ['nullable', 'string', 'max:2000'],
            'mentor.years_experience' => ['required_with:mentor', 'integer', 'min:0', 'max:60'],
            'mentor.mentoring_style' => ['required_with:mentor', Rule::enum(MentoringStyle::class)],
            'mentor.max_mentees' => ['required_with:mentor', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'freelancer.headline' => __('validation.attributes.headline'),
            'freelancer.hourly_rate' => __('validation.attributes.hourly_rate'),
            'freelancer.availability' => __('validation.attributes.availability'),
            'employer.company_name' => __('validation.attributes.company_name'),
            'employer.company_size' => __('validation.attributes.company_size'),
            'employer.industry' => __('validation.attributes.industry'),
            'employer.website' => __('validation.attributes.website'),
            'employer.open_to_beginners' => __('validation.attributes.open_to_beginners'),
            'mentor.expertise_summary' => __('validation.attributes.expertise_summary'),
            'mentor.years_experience' => __('validation.attributes.years_experience'),
            'mentor.mentoring_style' => __('validation.attributes.mentoring_style'),
            'mentor.max_mentees' => __('validation.attributes.max_mentees'),
        ];
    }
}
